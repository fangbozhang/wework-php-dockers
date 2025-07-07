<?php
use think\facade\Config;
use Swoole\Http\Server;

// 初始化
require __DIR__ . '/../vendor/autoload.php';
$http = new Server('0.0.0.0', 9501);

// 初始化Swoole Table
$processStatsTable = new Swoole\Table(1024);
$processStatsTable->column('company_id', Swoole\Table::TYPE_INT);
$processStatsTable->column('pid', Swoole\Table::TYPE_INT);
$processStatsTable->column('last_seq', Swoole\Table::TYPE_INT, 8);
$processStatsTable->column('memory', Swoole\Table::TYPE_INT);
$processStatsTable->column('updated_at', Swoole\Table::TYPE_INT);
$processStatsTable->create();

$http->processStatsTable = $processStatsTable;

// 启动企业微信worker
$companyIds = Config::get('swoole.wechat_finance.companies');
if (is_string($companyIds)) {
    $companyIds = explode(',', $companyIds);
}

\app\process\WechatFinanceManager::start($http, $companyIds);

// 启动HTTP服务
$http->on('request', function ($request, $response) {
    $response->header('Content-Type', 'text/plain');
    $response->end("WeWork Finance Server Running\n");
});

$http->start();
