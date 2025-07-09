<?php
// +----------------------------------------------------------------------
// | 控制台配置
// +----------------------------------------------------------------------
return [
    // 指令定义
    'commands' => [
//        \think\swoole\command\Server::class,
        'swoolews' => 'app\command\Swoolews',
        'FinanceServer' => 'app\command\FinanceServer',
    ],
];
