<?php
namespace app\work\service;

use think\swoole\Pool;
use WxworkFinanceSdk;
class WechatSdkFactory {
    protected $pool;

    public function __construct()
    {
        $this->pool = new Pool(
            \think\swoole\pool\Proxy::class,
            config('swoole.pool.wechat_sdk'),
            function () {
                // 实际使用时由进程自行创建
                return null;
            }
        );
    }

    public function make($corpId, $corpSecret)
    {
        return new WxworkFinanceSdk($corpId, $corpSecret);
    }
}