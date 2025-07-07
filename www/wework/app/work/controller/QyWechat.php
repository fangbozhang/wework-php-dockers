<?php

namespace app\work\controller;

use app\service\ChatTableCreator;
use think\facade\Db;
use think\facade\Log;
use think\swoole\facade\Task;
use think\swoole\facade\Redis;
use QyWechat\Message;

class QyWechat {
    // 回调验证入口
    public function callback() {
        $companyId = input('cid'); // URL中携带公司ID

        // 获取企业配置
        $company = \app\common\model\CompanyConfig::where('id', $companyId)->findOrEmpty();
        if ($company->isEmpty()) return 'Invalid company';

        // 初始化SDK
        $wechat = new Message([
            'corpid' => $company->corp_id,
            'secret' => $company->corp_secret,
            'token' => $company->token,
            'aeskey' => $company->aes_key
        ]);

        // 验证URL
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            return $wechat->verifyUrl();
        }

        // 处理消息
        $msg = $wechat->getMessage();
        $msgId = $msg['MsgId'] ?? uniqid();

        // 去重检查 (5分钟内的重复消息)
        if (!Redis::setnx("chat_msg:{$msgId}", 1)) {
            return 'success';
        }
        Redis::expire("chat_msg:{$msgId}", 300);

        // 投递异步任务存储消息
        Task::async(function () use ($msg, $companyId) {
            $this->saveChatMessage($msg, $companyId);
        });

        return 'success';
    }

    // 保存消息到数据库
    private function saveChatMessage($msg, $companyId) {
        try {
            $company = CompanyConfig::find($companyId);

            // 自动创建表(首次)
            if (empty($company->chat_table)) {
                $tableName = ChatTableCreator::createTable($companyId);
                $company->chat_table = $tableName;
                $company->save();
            }

            // 消息处理
            $data = [
                'msgid' => $msg['MsgId'],
                'action' => $msg['Action'] ?? 'send',
                'from' => $msg['From'],
                'tolist' => json_encode($msg['ToList'] ?? []),
                'roomid' => $msg['ChatId'] ?? null,
                'msgtime' => $msg['MsgTime'],
                'msgtype' => $msg['MsgType'],
                'content' => $this->parseContent($msg),
                'media_id' => $msg['MediaId'] ?? null,
                'created_at' => date('Y-m-d H:i:s')
            ];

            // 动态表名插入
            Db::name($company->chat_table)->insert($data);

        } catch (\Exception $e) {
            // 日志记录异常
            Log::error("保存消息失败: " . $e->getMessage());
        }
    }

    // 解析不同消息类型的内容
    private function parseContent($msg) {
        switch ($msg['MsgType']) {
            case 'text':
                return $msg['Content'];
            case 'image':
                return '[图片] ' . $msg['ImageInfo']['Size'];
            case 'voice':
                return '[语音] ' . $msg['VoiceInfo']['Duration'] . '秒';
            case 'video':
                return '[视频] ' . $msg['VideoInfo']['Duration'] . '秒';
            case 'location':
                return '[位置] ' . $msg['LocationInfo']['Label'];
            default:
                return json_encode($msg, JSON_UNESCAPED_UNICODE);
        }
    }
}