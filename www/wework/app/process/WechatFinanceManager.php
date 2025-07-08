<?php

namespace app\process;

use Swoole\Http\Server;
use app\common\model\CompanyConfig;

class WechatFinanceManager {
    public static function start(Server $server, array $companyIds = null) {
        if ($companyIds === null) {
            WechatFinanceWorker::startAllWorkers($server);
        } else {
            $validCompanies = CompanyConfig::find(1);

            foreach ($validCompanies as $companyId) {
                $server->addProcess(new WechatFinanceWorker($companyId, $server));
            }
        }
    }
}