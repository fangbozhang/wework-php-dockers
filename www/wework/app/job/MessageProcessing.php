<?php

namespace app\job;

use think\facade\Db;
use think\queue\Job;
use app\common\model\CompanyConfig;
use app\common\service\KnowledgeBaseService;
use app\common\service\WechatService;
use app\common\service\MediaService;

/**
 * 企业微信消息处理
 *
 */
class MessageProcessing {
    public function fire(Job $job, $data) {
        try {
            $companyId = $data['company_id'];
            $msgData = $data['encrypted_msg'];

            // 获取公司配置
            $company = CompanyConfig::find($companyId);
            if (!$company) {
                $job->delete();
                return;
            }

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("JSON decode error");
            }

            // 检查是否为机器人消息（避免循环）
            if ($this->isRobotMessage($msgData)) {
                $job->delete();
                return;
            }

            // 保存消息到数据库
            $this->saveMessageToDb($companyId, $msgData, $msgData['seq']);

            // 处理不同类型的消息
            switch ($msgData['msgtype']) {
                case 'text':
                    // 文本消息：进行知识库匹配并回复
                    $answer = KnowledgeBaseService::match($msgData);
                    if ($answer) {
                        WechatService::sendMessage(
                            $company->corp_id,
                            $company->corp_secret,
                            $msgData['roomid'] ?? '',
                            $answer
                        );
                    }
                    break;

                case 'image':
                case 'file':
                    // 文件/图片消息：异步处理媒体文件
                    \think\facade\Queue::push(
                        \app\job\MediaProcessing::class,
                        [
                            'company_id' => $companyId,
                            'msgData' => $msgData,
                        ],
                        'WechatMediaQueue'
                    );
                    break;

                default:
                    // 其他类型消息：可以添加更多处理逻辑
                    break;
            }

            // 任务完成
            $job->delete();
        } catch (\Exception $e) {
            // 记录错误并重试
            \think\facade\Log::error("处理微信消息失败: {$e->getMessage()}");
            $job->release(30); // 30秒后重试
        }
    }

    private function isRobotMessage($msgData) {
        // 判断是否为机器人自己发送的消息（避免循环）
        $senderId = $msgData['from'] ?? '';
        return str_starts_with($senderId, 'robot_'); // 根据实际情况调整
    }

    private function saveMessageToDb($companyId, $msgData, $seq) {
        // 创建动态表（如果不存在）
        $tableName = \app\common\service\ChatTableCreator::createTable($companyId);

        // 准备消息数据
        $messageData = [
            'msgid' => $msgData['msgid'] ?? '',
            'publickey_ver' => $msgData['publickey_ver'] ?? 0,
            'seq' => $seq,
            'action' => $msgData['action'] ?? 'send',
            'msgfrom' => $msgData['from'] ?? '',
            'tolist' => implode(',', $msgData['tolist'] ?? []),
            'msgtype' => $msgData['msgtype'] ?? '',
            'msgtime' => $msgData['msgtime'] ?? 0,
            'text' => $msgData['text']['content'] ?? '',
            'sdkfield' => $msgData[$msgData['msgtype']]['sdkfield'] ?? '',
            'msgdata' => json_encode($msgData),
            'roomid' => $msgData['roomid'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ];

        // 保存消息到数据库
        Db::table($tableName)->insert($messageData);
    }
}