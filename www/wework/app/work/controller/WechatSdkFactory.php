<?php
namespace app\work\controller;

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
                // 这里不需要实际创建，由进程自行创建
                return null;
            }
        );
    }

    public function make($corpId, $corpSecret)
    {
        // 实际创建SDK实例
        return new WxworkFinanceSdk($corpId, $corpSecret);
    }

    public function invoke(callable $callable, $corpId, $corpSecret)
    {
        $sdk = $this->make($corpId, $corpSecret);
        return $callable($sdk);
    }
}