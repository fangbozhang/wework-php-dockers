<?php
declare (strict_types=1);

namespace app\command;

use Swoole\Table;
use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use Swoole\Http\Server;

class FinanceServer extends Command {

    protected function configure() {
        $this->setName('FinanceServer')
            ->setDescription('the finance server command');
    }

    protected function execute(Input $input, Output $output) {
        $http = new Server('0.0.0.0', 9501);

        $processStatsTable = new Table(1024);
        $processStatsTable->column('company_id', Table::TYPE_INT);
        $processStatsTable->column('pid', Table::TYPE_INT);
        $processStatsTable->column('last_seq', Table::TYPE_INT, 8);
        $processStatsTable->column('memory', Table::TYPE_INT);
        $processStatsTable->column('updated_at', Table::TYPE_INT);
        $processStatsTable->create();

        $http->processStatsTable = $processStatsTable;

        $companyIds = \think\facade\Config::get('swoole.wechat_finance.companies');
        dd($companyIds);
        $companyIds = [1];
        if (is_string($companyIds)) {
            $companyIds = explode(',', $companyIds);
        }

        \app\process\WechatFinanceManager::start($http, $companyIds);

        $http->on('request', function ($request, $response) {
            $response->header('Content-Type', 'text/plain');
            $response->end("WeWork Finance Server Running\n");
        });

        $http->start();
    }


}