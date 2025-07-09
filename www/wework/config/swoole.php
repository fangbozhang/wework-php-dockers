<?php

return [
    'http' => [
        'enable' => true,
        'host' => '0.0.0.0',
        'port' => 9501, // 修改为9501端口
        'worker_num' => swoole_cpu_num() * 2, // 增加worker数量
        'options' => [
            'daemonize' => false, // 调试模式关闭守护进程
            'log_file' => runtime_path('logs/swoole.log'), // 指定日志文件
            'pid_file' => runtime_path('swoole.pid'), // PID文件
            'max_request' => 0, // 常驻进程不限制请求数
            'heartbeat_idle_time' => 600, // 心跳检测
            'heartbeat_check_interval' => 60,
            'enable_coroutine' => true, // 开启协程
            'max_coroutine' => 10000, // 最大协程数
            'task_worker_num' => swoole_cpu_num() * 2, // 增加任务worker
            'task_enable_coroutine' => true, // 任务协程支持
        ],
    ],
    'websocket' => [
        'enable' => false,
        'handler' => \think\swoole\websocket\socketio\Handler::class,
        'ping_interval' => 25000,
        'ping_timeout' => 60000,
        'room' => [
            'type' => 'table',
            'table' => [
                'room_rows' => 8192,
                'room_size' => 2048,
                'client_rows' => 4096,
                'client_size' => 2048,
            ],
            'redis' => [
                'host' => '127.0.0.1',
                'port' => 6379,
                'max_active' => 3,
                'max_wait_time' => 5,
            ],
        ],
        'listen' => [],
        'subscribe' => [],
    ],
    'rpc' => [
        'server' => [
            'enable' => false,
            'host' => '0.0.0.0',
            'port' => 9000,
            'worker_num' => swoole_cpu_num(),
            'services' => [],
        ],
        'client' => [],
    ],
    //队列
    'queue' => [
        'enable' => true, // 启用队列
        'workers' => [
            'WechatMediaDownload' => [
                'class' => \app\job\WechatMediaDownload::class,
                'memory' => '512M',
                'timeout' => 3600,
                'nums' => swoole_cpu_num() * 2,
            ],
        ],
    ],
    'hot_update' => [
        'enable' => env('APP_DEBUG', false),
        'name' => ['*.php'],
        'include' => [app_path()],
        'exclude' => [],
    ],
    //连接池
    'pool' => [
        'db' => [
            'enable' => true,
            'max_active' => 20, // 增加连接池大小
            'max_wait_time' => 5,
            'min_active' => 5, // 最小连接数
            'max_idle_time' => 60, // 最大空闲时间
        ],
        'cache' => [
            'enable' => true,
            'max_active' => 20, // 增加连接池大小
            'max_wait_time' => 5,
            'min_active' => 5,
            'max_idle_time' => 60,
        ],
        //自定义连接池
        'wechat_sdk' => [
            'enable' => true,
            'max_active' => 10,
            'max_wait_time' => 3,
            'min_active' => 3,
            'max_idle_time' => 30,
        ],
    ],
    'ipc' => [
        'type' => 'unix_socket',
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'max_active' => 3,
            'max_wait_time' => 5,
        ],
    ],
    'wechat_finance' => [
        'enable' => true,
        'companies' => env('WEWORK_FINANCE_COMPANIES', null), // 支持环境变量配置
        'max_memory' => 100,
        'max_retry' => 5,
        'base_sleep' => 1,
        'sync_interval' => 1,
        'max_workers' => 20,
        'rate_limit' => [
            'messages' => 1000,
            'media' => 50,
        ],
        'redis_prefix' => 'wework:finance:',
    ],
    'tables' => [
        // 进程状态监控表
        'process_stats' => [
            'size' => 1024,
            'columns' => [
                ['name' => 'company_id', 'type' => \Swoole\Table::TYPE_INT],
                ['name' => 'pid', 'type' => \Swoole\Table::TYPE_INT],
                ['name' => 'last_seq', 'type' => \Swoole\Table::TYPE_INT, 'size' => 8],
                ['name' => 'memory', 'type' => \Swoole\Table::TYPE_INT],
                ['name' => 'updated_at', 'type' => \Swoole\Table::TYPE_INT],
            ]
        ],
    ],
    //每个worker里需要预加载以共用的实例
    'concretes' => [
        \WxworkFinanceSdk::class => \app\work\service\WechatSdkFactory::class,
    ],
    //重置器
    'resetters' => [],
    //每次请求前需要清空的实例
    'instances' => [],
    //每次请求前需要重新执行的服务
    'services' => [],
    'wechat_finance' => [
        'max_memory' => 100, // 内存阈值(MB)
        'max_retry' => 5,    // 最大重试次数
        'base_sleep' => 1,   // 基础休眠时间(秒)
    ],
];
