<?php
namespace app\resetter;

use think\App;
use think\swoole\contract\ResetterInterface;
use think\swoole\Pool;
use think\swoole\Sandbox;

class DbResetter implements ResetterInterface
{
    // 修正方法签名，添加 Sandbox 参数
    public function handle(App $app, Sandbox $sandbox)
    {
        // 重置数据库连接
        $app->delete('db');
        $app->delete('db.connection');
        
        // 重新初始化数据库
        $app->make('db');
        
        // 重置连接池
        if ($app->has('swoole.pool')) {
            $pool = $app->make('swoole.pool');
            if ($pool instanceof Pool) {
                $pool->clear('db');
            }
        }
        
        return $app;
    }
}
