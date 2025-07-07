<?php

//����WebSocket Server���󣬼���0.0.0.0:9502�˿ڡ�
$ws = new Swoole\WebSocket\Server('0.0.0.0', 9502);

//����WebSocket���Ӵ��¼���
$ws->on('Open', function ($ws, $request) {
    $ws->push($request->fd, "hello, welcome\n");
});

//����WebSocket��Ϣ�¼���
$ws->on('Message', function ($ws, $frame) {
    echo "Message: {$frame->data}\n";
    $ws->push($frame->fd, "server: {$frame->data}");
});

//����WebSocket���ӹر��¼���
$ws->on('Close', function ($ws, $fd) {
    echo "client-{$fd} is closed\n";
});

$ws->start();

