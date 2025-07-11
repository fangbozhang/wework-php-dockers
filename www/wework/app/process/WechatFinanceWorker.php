<?php

namespace app\process;

use app\common\model\CompanyConfig;
use app\job\MessageProcessing;
use Swoole\Coroutine;
use Swoole\Http\Server;
use Swoole\Process;
use think\console\Output;
use think\facade\Db;
use Redis;
use think\facade\Queue;
use WxworkFinanceSdk;

/**
 * 获取企业微信消息
 */
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
        $this->redis = new Redis();
        $this->redis->connect('118.178.230.188', 6379);
        parent::__construct([$this, 'run']);
    }

    public static function startAllWorkers(Server $server) {
        $companyIds = CompanyConfig::where('status', 1)->column('id');
        foreach ($companyIds as $companyId) {
            $server->addProcess(new self($companyId));
        }
    }

    /**
     * @return void
     * @throws \RedisException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
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
                    $this->output->error("Process failed: " . $e->getMessage());
                    $this->handleException($e);
                }
            }
        });
    }

    /**
     * 初始化配置
     * @return void
     * @throws \RedisException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\DbException
     * @throws \think\db\exception\ModelNotFoundException
     */
    protected function initialize() {
        $companyConfigKey = "wework:company:config:{$this->companyId}";
        $companyConfig = $this->redis->get($companyConfigKey);

        if (!$companyConfig) {
            $this->company = CompanyConfig::find($this->companyId);
            if (!$this->company) {
                $this->output->error("Company config not found: {$this->companyId}");
                exit(1);
            }
            $this->redis->set($companyConfigKey, json_encode($this->company->toArray()));
        } else {
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

    /**
     * 获取消息
     * @return void
     */
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
            $this->redis->set($seqKey, $msg['seq']);
        }
    }

    /**
     * 处理消息
     * @param $encryptedMsg
     * @return void
     * @throws \RedisException
     */
    protected function processMessage($encryptedMsg) {

        // 解密随机密钥
        $decryptRandKey = null;
        $ok = openssl_private_decrypt(
            base64_decode($encryptedMsg['encrypt_random_key']),
            $decryptRandKey,
            $this->company->aes_key,
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

        // 直接将消息入队，避免在接收进程中处理复杂逻辑
        Queue::push(
            MessageProcessing::class,
            [
                'company_id' => $this->company->id,
                'encrypted_msg' => $msgData,
            ],
            'WechatMessageQueue'
        );

//        // 保存消息
//        $this->saveMessage($msgData, $encryptedMsg['seq']);
//
//        // 处理媒体文件
//        $this->handleMedia($msgData, $encryptedMsg['seq']);
    }

    /**
     * php垃圾回收 清楚废物内存
     * @return void
     */
    protected function freeMemory() {
        $usage = memory_get_usage(true) / 1024 / 1024; // MB
        if ($usage > self::MEMORY_LIMIT) {
            gc_collect_cycles();
            gc_mem_caches();
            $this->output->info("GC collected, memory: " . round($usage, 2) . "MB");
        }
    }

    /**
     *  更新进程状态到Swoole Table
     * @return void
     */
    protected function updateStats() {
        $pid = getmypid();
        $memory = memory_get_usage() / 1024; // KB
        $this->server->processStatsTable->set("we_process_stats", [
            'company_id' => $this->company->id,
            'pid' => $pid,
            'last_seq' => $this->redis->get("wework:company:seq:{$this->company->id}"),
            'memory' => $memory,
            'update_time' => time()
        ]);
    }

    /**
     * 记录异常
     * @param \Throwable $e
     * @return void
     */
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
        Db::name('we_process_errors')->insert([
            'company_id' => $this->company->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'occurre_time' => date('Y-m-d H:i:s')
        ]);

        // 等待重试
        Coroutine::sleep($delay);

        // 达到最大重试次数时重启进程
        if ($retryCount >= self::MAX_RETRY) {
            $this->output->error("Max retries reached, restarting");
            $this->restart();
        }
    }

    protected function restart() {
        // 实际部署中应该由Supervisor重启
        posix_kill(getmypid(), SIGTERM);
    }


//    protected function saveMessage($msgData, $seq) {
//        // 确保表存在
//        if (!$this->company->chat_table) {
//            $this->company->chat_table = ChatTableCreator::create($this->company);
//            $this->company->save();
//        }
//
//        // 构建数据
//        $data = [
//            'msgid' => $msgData['msgid'] ?? '',
//            'publickey_ver' => $msgData['publickey_ver'] ?? 0,
//            'seq' => $seq,
//            'action' => $msgData['action'] ?? 'send',
//            'msgfrom' => $msgData['from'] ?? '',
//            'tolist' => is_array($msgData['tolist']) ?
//                implode(',', $msgData['tolist']) :
//                ($msgData['tolist'] ?? ''),
//            'msgtype' => $msgData['msgtype'] ?? '',
//            'msgtime' => $msgData['msgtime'] ?? 0,
//            'text' => $this->getTextContent($msgData),
//            'sdkfield' => $this->getSdkField($msgData),
//            'msgdata' => json_encode($msgData, JSON_UNESCAPED_UNICODE),
//            'status' => 1, // 默认未下载媒体
//            'media_code' => 0,
//            'media_path' => '',
//            'roomid' => $msgData['roomid'] ?? '',
//            'created_at' => date('Y-m-d H:i:s')
//        ];
//
//        // 插入数据库
//        Db::name($this->company->chat_table)->insert($data);
//        $this->output->info("Message saved: {$data['msgid']}");
//    }

//    protected function getTextContent($msgData) {
//        return ($msgData['msgtype'] === 'text') ?
//            ($msgData['text']['content'] ?? '') :
//            '';
//    }

//    protected function getSdkField($msgData) {
//        $type = $msgData['msgtype'] ?? '';
//        $mediaTypes = ['image', 'video', 'voice', 'file'];
//
//        return in_array($type, $mediaTypes) ?
//            ($msgData[$type]['sdkfileid'] ?? '') :
//            '';
//    }

//    protected function handleMedia($msgData, $seq) {
//        $type = $msgData['msgtype'] ?? '';
//        $mediaTypes = ['image', 'video', 'voice', 'file'];
//
//        if (!in_array($type, $mediaTypes)) {
//            return;
//        }
//
//        $sdkFileId = $msgData[$type]['sdkfileid'] ?? '';
//        if (!$sdkFileId) {
//            return;
//        }
//
//        // 投递媒体下载任务
//        Task::async(function () use ($sdkFileId, $seq, $type) {
//            $this->dispatchMediaTask($sdkFileId, $seq, $type);
//        });
//    }

//    protected function dispatchMediaTask($sdkFileId, $seq, $msgType) {
//        // 通过队列异步下载
//        $taskData = [
//            'company_id' => $this->company->id,
//            'sdkfileid' => $sdkFileId,
//            'seq' => $seq,
//            'msgtype' => $msgType
//        ];
//
//        // 使用ThinkPHP的队列系统
//        \think\facade\Queue::push(
//            \app\job\WechatMediaDownload::class,
//            $taskData,
//            'WechatMediaDownload'
//        );
//    }

//    /**
//     * 检查数据库连接是否有效，无效则重建
//     */
//    protected function checkDbConnection() {
//        try {
//            // 执行简单查询测试连接（如 SELECT 1）
//            Db::query('SELECT 1');
//        } catch (\Exception $e) {
//            // 连接失效，关闭旧连接并重建
//            Db::connect()->close(); // 关闭无效连接
//            Db::connect(); // 重建连接
//            echo "MySQL connection reconnected (error: {$e->getMessage()})\n";
//        }
//    }

}
