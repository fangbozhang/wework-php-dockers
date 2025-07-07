<?php

namespace app\common\service;

use think\facade\Db;

/**
 * 创建动态表服务
 */
class ChatTableCreator {
    public static function createTable($corpId)
    {
        $tableName = "we_message_{$corpId}";

        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `{$tableName}` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `msgid` varchar(64) NOT NULL COMMENT '消息ID',
  `publickey_ver` int(11) NOT NULL DEFAULT '0' COMMENT '密钥版本',
  `seq` bigint(20) UNSIGNED NOT NULL COMMENT '消息序号',
  `action` varchar(20) NOT NULL COMMENT '消息动作(send/recall/switch)',
  `msgfrom` varchar(64) NOT NULL COMMENT '发送者ID',
  `tolist` text NOT NULL COMMENT '接收者ID列表(逗号分隔)',
  `msgtype` varchar(20) NOT NULL COMMENT '消息类型',
  `msgtime` bigint(13) UNSIGNED NOT NULL COMMENT '消息时间戳(ms)',
  `text` text COMMENT '文本消息内容',
  `sdkfield` varchar(256) DEFAULT NULL COMMENT '附件ID',
  `msgdata` json NOT NULL COMMENT '原始消息数据',
  `status` tinyint(4) NOT NULL DEFAULT '1' COMMENT '1:未加载媒体 2:加载中 3:加载完成 4:加载失败',
  `media_code` int(11) DEFAULT NULL COMMENT '媒体错误码',
  `media_path` varchar(255) DEFAULT NULL COMMENT '媒体文件路径',
  `roomid` varchar(64) DEFAULT NULL COMMENT '群聊ID',
  `created_at` datetime NOT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `msgid` (`msgid`),
  KEY `msgtime` (`msgtime`),
  KEY `roomid` (`roomid`),
  KEY `seq` (`seq`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='企业会话内容表';
SQL;

        Db::execute($sql);
        return $tableName;
    }
}