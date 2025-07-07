<?php
declare(strict_types=1);

return [
    'listen' => [
        'WorkerStart' => [
            function ($server, $workerId) {
                // 只在第一个Worker进程中启动
                if ($workerId === 0) {
                    // 创建进程状态表
                    $table = new \Swoole\Table(1024);
                    $table->column('company_id', \Swoole\Table::TYPE_INT);
                    $table->column('pid', \Swoole\Table::TYPE_INT);
                    $table->column('last_seq', \Swoole\Table::TYPE_INT, 8);
                    $table->column('memory', \Swoole\Table::TYPE_INT);
                    $table->column('updated_at', \Swoole\Table::TYPE_INT);
                    $table->create();

                    // 绑定到服务器
                    $server->processStatsTable = $table;

                    // 获取所有启用的公司
                    $companies = \app\common\model\CompanyConfig::where('status', 1)->select();

                    foreach ($companies as $company) {
                        // 启动进程
                        $process = new \app\process\WechatFinanceWorker($company->id);
                        $server->addProcess($process->getProcess());

                        // 记录进程信息
                        $table->set("company_{$company->id}", [
                            'company_id' => $company->id,
                            'pid' => $process->getProcess()->pid,
                            'last_seq' => $company->last_seq,
                            'memory' => 0,
                            'updated_at' => time()
                        ]);
                    }

                    // 定时保存进程状态（每10秒）
                    \Swoole\Timer::tick(10000, function () use ($server) {
                        foreach ($server->processStatsTable as $key => $row) {
                            $company = \app\model\CompanyConfig::find($row['company_id']);
                            if ($company && $company->last_seq != $row['last_seq']) {
                                $company->last_seq = $row['last_seq'];
                                $company->save();
                            }
                        }
                    });
                }
            }
        ],

        'WorkerStop' => [
            function ($server, $workerId) {
                // 主进程退出时保存状态
                if ($workerId === 0 && isset($server->processStatsTable)) {
                    foreach ($server->processStatsTable as $row) {
                        $company = \app\model\CompanyConfig::find($row['company_id']);
                        if ($company) {
                            $company->last_seq = $row['last_seq'];
                            $company->save();
                        }
                    }
                }
            }
        ]
    ]
];
