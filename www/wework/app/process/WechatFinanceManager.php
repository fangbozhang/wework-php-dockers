<?php

namespace app\process;

use Swoole\Http\Server;
use app\common\model\CompanyConfig;

class WechatFinanceManager {
    public static function start(Server $server, array $companyIds = null) {
        if ($companyIds === null) {
            WechatFinanceWorker::startAllWorkers($server);
        } else {
            $validCompanies = CompanyConfig::where('id', 'in', $companyIds)
                ->where('status', 1)
                ->column('id');

            foreach ($validCompanies as $companyId) {
                $server->addProcess(new WechatFinanceWorker($companyId, $server));
            }
        }
    }
}