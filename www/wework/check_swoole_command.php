<?php
// 引入自动加载文件
require __DIR__ . '/vendor/autoload.php';

// 检查命令类是否存在
if (class_exists(think\swoole\command\Server::class)) {
    echo "Swoole command class exists.";
} else {
    echo "Swoole command class does not exist.";
}