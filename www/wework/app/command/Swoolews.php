<?php
declare (strict_types=1);

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;

class Swoolews extends Command {
    protected function configure() {
        // 指令配置
        $this->setName('swoolews')
            ->setDescription('the swoolews command');
    }

    protected function execute(Input $input, Output $output) {
        try {
            // 指令输出
            $output->writeln('swoolews');
            echo 'ceshi';
            //创建WebSocket Server对象，监听0.0.0.0:2345端口
            $ws = new \Swoole\WebSocket\Server('0.0.0.0', 2345);

            $ws->on('Start', function ($server) use ($output) {
                $output->writeln("[SUCCESS] Server started at ws://0.0.0.0:2345");
            });
            //监听WebSocket连接打开事件
            $ws->on('Open', function ($ws, $request) {
                $ws->push($request->fd, "hello, welcome\n");
            });

            //监听WebSocket消息事件
            $ws->on('Message', function ($ws, $frame) {
                echo "Message: {$frame->data}\n";
                $ws->push($frame->fd, "server: {$frame->data}");
            });

            //监听WebSocket连接关闭事件
            $ws->on('Close', function ($ws, $fd) {
                echo "client-{$fd} is closed\n";
            });

            $ws->start();
        } catch (\Throwable $e) {
            $output->writeln("[ERROR] " . $e->getMessage());
        }
    }
}