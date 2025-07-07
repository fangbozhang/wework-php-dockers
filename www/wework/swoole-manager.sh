#!/bin/bash

# 服务管理脚本：启动/停止/重启 Swoole 服务
# 位置：项目根目录

ACTION=$1

case "$ACTION" in
    start)
        # 清除缓存
        rm -rf runtime/cache/*
        composer dump-autoload -o > /dev/null
        
        # 启动 Swoole 服务（后台运行）
        nohup php think swoole >> runtime/logs/swoole.log 2>&1 &
        SWOOLE_PID=$!
        echo $SWOOLE_PID > runtime/swoole.pid
        echo "Swoole started with PID: $SWOOLE_PID"
        
        # 启动队列服务（后台运行）
        nohup php think queue:work --queue WechatMediaDownload >> runtime/logs/queue_worker.log 2>&1 &
        QUEUE_PID=$!
        echo $QUEUE_PID > runtime/queue_worker.pid
        echo "Queue worker started with PID: $QUEUE_PID"
        ;;
        
    stop)
        # 停止 Swoole 服务
        if [ -f runtime/swoole.pid ]; then
            SWOOLE_PID=$(cat runtime/swoole.pid)
            kill $SWOOLE_PID
            rm runtime/swoole.pid
            echo "Stopped Swoole (PID: $SWOOLE_PID)"
        else
            echo "Swoole is not running"
        fi
        
        # 停止队列服务
        if [ -f runtime/queue_worker.pid ]; then
            QUEUE_PID=$(cat runtime/queue_worker.pid)
            kill $QUEUE_PID
            rm runtime/queue_worker.pid
            echo "Stopped Queue worker (PID: $QUEUE_PID)"
        else
            echo "Queue worker is not running"
        fi
        ;;
        
    restart)
        $0 stop
        sleep 2
        $0 start
        ;;
        
    status)
        # 检查 Swoole 状态
        if [ -f runtime/swoole.pid ]; then
            SWOOLE_PID=$(cat runtime/swoole.pid)
            if ps -p $SWOOLE_PID > /dev/null; then
                echo "Swoole is running (PID: $SWOOLE_PID)"
            else
                echo "Swoole PID file exists but process is not running"
            fi
        else
            echo "Swoole is not running"
        fi
        
        # 检查队列状态
        if [ -f runtime/queue_worker.pid ]; then
            QUEUE_PID=$(cat runtime/queue_worker.pid)
            if ps -p $QUEUE_PID > /dev/null; then
                echo "Queue worker is running (PID: $QUEUE_PID)"
            else
                echo "Queue worker PID file exists but process is not running"
            fi
        else
            echo "Queue worker is not running"
        fi
        ;;
        
    *)
        echo "Usage: $0 {start|stop|restart|status}"
        exit 1
esac

exit 0