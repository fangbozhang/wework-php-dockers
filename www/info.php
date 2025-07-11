<?php

namespace app\process;

use app\common\model\CompanyConfig;
use Swoole\Coroutine;
use Swoole\Http\Server;
use Swoole\Process;
use Swoole\Server\Task;
use think\console\Output;
use think\facade\Db;
use think\swoole\facade\Redis;
use WxworkFinanceSdk;
use app\common\service\ChatTableCreator;

class WechatFinanceWorker extends Process {
    protected $companyId;
    protected $company;
    protected $sdk;
    protected $server;
    protected $redis;

    // 内存阈值 (MB)
    const MEMORY_LIMIT = 100;
    // 最大重试次数
    const MAX_RETRY = 5;

    public function __construct($companyId, $server = null) {
        $this->companyId = $companyId;
        $this->server = $server;
        $this->output = new Output();
        $this->redis = Redis::connect(); // 初始化 Redis 连接
        parent::__construct([$this, 'run']);
    }

    public static function startAllWorkers(Server $server)
    {
        $companyIds = CompanyConfig::where('status', 1)->column('id');
        foreach ($companyIds as $companyId) {
            $server->addProcess(new self($companyId));
        }
    }

    public function run() {
        $this->output->info("Starting worker for company: {$this->companyId}");
        // 使用协程方式初始化
        go(function () {
            $this->initialize();

            while (true) {
                try {
                    $this->syncMessages();
                    $this->updateStats();
                    $this->freeMemory();
                    Coroutine::sleep(1);
                } catch (\Throwable $e) {
                    $this->handleException($e);
                }
            }
        });
    }

    protected function initialize() {
        $this->checkDbConnection();
        // 从 Redis 中获取公司配置
        $companyConfigKey = "wework:company:config:{$this->companyId}";
        $companyConfig = $this->redis->get($companyConfigKey);

        if (!$companyConfig) {
            // 如果 Redis 中不存在，则从数据库中获取
            $this->company = CompanyConfig::find($this->companyId);
            if (!$this->company) {
                $this->output->error("Company config not found: {$this->companyId}");
                exit(1);
            }
            // 将公司配置存储到 Redis 中
            $this->redis->set($companyConfigKey, json_encode($this->company->toArray()));
        } else {
            // 如果 Redis 中存在，则将其转换为对象
            $this->company = new CompanyConfig(json_decode($companyConfig, true));
        }

        // 检查私钥
        if (empty($this->company->aes_key)) {
            $this->output->error("aes_key missing for company: {$this->company->corp_id}");
            exit(1);
        }

        // 初始化 SDK
        $this->sdk = new WxworkFinanceSdk(
            $this->company->corp_id,
            $this->company->corp_secret
        );

        $this->output->info("Worker started for company: {$this->company->corp_id}");
    }

    protected function checkDbConnection() {
        try {
            // 执行简单查询测试连接（如 SELECT 1）
            Db::query('SELECT 1');
        } catch (\Exception $e) {
            // 连接失效，关闭旧连接并重建
            Db::connect()->close(); // 关闭无效连接
            Db::connect(); // 重建连接
            echo "MySQL connection reconnected (error: {$e->getMessage()})\n";
        }
    }

    protected function syncMessages() {
        $seqKey = "wework:company:seq:{$this->company->id}";
        $seq = $this->redis->get($seqKey);
        if (!$seq) {
            $seq = 0;
        }
        $this->output->info("Fetching messages from seq: {$seq}");

        // 拉取聊天数据
        $data = $this->sdk->getChatData($seq, 100);
        $result = json_decode($data, true);
        $messages = $result['chatdata'] ?? [];

        if (empty($messages)) {
            $this->output->info("No new messages");
            return;
        }

        $this->output->info("Fetched " . count($messages) . " messages");

        // 处理每条消息
        foreach ($messages as $msg) {
            $this->processMessage($msg);
            // 更新 Redis 中的 seq
            $this->redis->set($seqKey, $msg['seq']);
        }
    }

    protected function processMessage($encryptedMsg) {
        // 消息去重
//        $msgKey = "msg:{$this->company->id}:{$encryptedMsg['msgid']}";
//        if ($this->redis->get($msgKey)) {
//            $this->output->info("Duplicate message: {$encryptedMsg['msgid']}");
//            return;
//        }
//        $this->redis->setex($msgKey, 86400, 1); // 24小时去重
        // 解密随机密钥
        $decryptRandKey = null;
        $ok = openssl_private_decrypt(
            base64_decode($encryptedMsg['encrypt_random_key']),
            $decryptRandKey,
            $this->company->private_key,
            OPENSSL_PKCS1_PADDING
        );

        if (!$ok) {
            $this->output->error("Decrypt key failed: " . openssl_error_string());
            return;
        }

        // 解密聊天内容
        $plainText = $this->sdk->decryptData($decryptRandKey, $encryptedMsg['encrypt_chat_msg']);
        $msgData = json_decode($plainText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->output->error("JSON decode error: " . json_last_error_msg());
            return;
        }

        // 保存消息
        $this->saveMessage($msgData, $encryptedMsg['seq']);

        // 处理媒体文件
        $this->handleMedia($msgData, $encryptedMsg['seq']);
    }

    protected function saveMessage($msgData, $seq) {
        // 确保表存在
        if (!$this->company->chat_table) {
            $this->company->chat_table = ChatTableCreator::create($this->company);
            $this->company->save();
        }

        // 构建数据
        $data = [
            'msgid' => $msgData['msgid'] ?? '',
            'publickey_ver' => $msgData['publickey_ver'] ?? 0,
            'seq' => $seq,
            'action' => $msgData['action'] ?? 'send',
            'msgfrom' => $msgData['from'] ?? '',
            'tolist' => is_array($msgData['tolist']) ?
                implode(',', $msgData['tolist']) :
                ($msgData['tolist'] ?? ''),
            'msgtype' => $msgData['msgtype'] ?? '',
            'msgtime' => $msgData['msgtime'] ?? 0,
            'text' => $this->getTextContent($msgData),
            'sdkfield' => $this->getSdkField($msgData),
            'msgdata' => json_encode($msgData, JSON_UNESCAPED_UNICODE),
            'status' => 1, // 默认未下载媒体
            'media_code' => 0,
            'media_path' => '',
            'roomid' => $msgData['roomid'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ];

        // 插入数据库
        Db::name($this->company->chat_table)->insert($data);
        $this->output->info("Message saved: {$data['msgid']}");
    }

    protected function getTextContent($msgData) {
        return ($msgData['msgtype'] === 'text') ?
            ($msgData['text']['content'] ?? '') :
            '';
    }

    protected function getSdkField($msgData) {
        $type = $msgData['msgtype'] ?? '';
        $mediaTypes = ['image', 'video', 'voice', 'file'];

        return in_array($type, $mediaTypes) ?
            ($msgData[$type]['sdkfileid'] ?? '') :
            '';
    }

    protected function handleMedia($msgData, $seq) {
        $type = $msgData['msgtype'] ?? '';
        $mediaTypes = ['image', 'video', 'voice', 'file'];

        if (!in_array($type, $mediaTypes)) {
            return;
        }

        $sdkFileId = $msgData[$type]['sdkfileid'] ?? '';
        if (!$sdkFileId) {
            return;
        }

        // 投递媒体下载任务
        Task::async(function () use ($sdkFileId, $seq, $type) {
            $this->dispatchMediaTask($sdkFileId, $seq, $type);
        });
    }

    protected function dispatchMediaTask($sdkFileId, $seq, $msgType) {
        // 通过队列异步下载
        $taskData = [
            'company_id' => $this->company->id,
            'sdkfileid' => $sdkFileId,
            'seq' => $seq,
            'msgtype' => $msgType
        ];

        // 使用 ThinkPHP 的队列系统
        \think\facade\Queue::push(
            \app\job\WechatMediaDownload::class,
            $taskData,
            'WechatMediaDownload'
        );
    }

    protected function freeMemory() {
        $usage = memory_get_usage(true) / 1024 / 1024; // MB
        if ($usage > self::MEMORY_LIMIT) {
            gc_collect_cycles();
            gc_mem_caches();
            $this->output->info("GC collected, memory: " . round($usage, 2) . "MB");
        }
    }

    protected function updateStats() {
        // 更新进程状态到 Swoole Table
        $pid = getmypid();
        $memory = memory_get_usage() / 1024; // KB
        $seqKey = "wework:company:seq:{$this->company->id}";
        $lastSeq = $this->redis->get($seqKey);

        $this->server->processStatsTable->set("company_{$this->company->id}", [
            'company_id' => $this->company->id,
            'pid' => $pid,
            'last_seq' => $lastSeq,
            'memory' => $memory,
            'updated_at' => time()
        ]);
    }

    protected function handleException(\Throwable $e) {
        static $retryCount = 0;
        $retryCount++;

        $delay = min(30, pow(2, $retryCount)); // 指数退避
        $this->output->error(sprintf(
            "Error: %s in %s:%d, retry %d/%d in %ds",
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $retryCount,
            self::MAX_RETRY,
            $delay
        ));

        // 记录异常日志
    }
}