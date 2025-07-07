#!/bin/bash

# 确保日志目录存在
mkdir -p runtime/logs

# 清除缓存并重新生成自动加载
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Clearing cache and optimizing autoload..."
rm -rf runtime/cache/*
composer dump-autoload -o

# 启动 Swoole HTTP 服务
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting Swoole HTTP server..."
php think swoole >> runtime/logs/swoole.log 2>&1 &
SWOOLE_PID=$!
echo "Swoole PID: $SWOOLE_PID"

# 等待 Swoole 启动
echo "Waiting for Swoole to start..."
sleep 8

# 启动队列 Worker
echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting queue workers..."
php think queue:work --queue WechatMediaDownload >> runtime/logs/queue_worker.log 2>&1 &
QUEUE_PID=$!
echo "Queue Worker PID: $QUEUE_PID"

# 健康检查
echo "Performing health check..."
sleep 2
ps -p $SWOOLE_PID > /dev/null && echo "Swoole is running" || echo "Swoole failed to start"
ps -p $QUEUE_PID > /dev/null && echo "Queue worker is running" || echo "Queue worker failed to start"

# 捕获退出信号
trap "echo 'Stopping services...'; kill $SWOOLE_PID $QUEUE_PID; wait" EXIT

# 监控进程状态
echo "Services are running. Press Ctrl+C to stop."
echo "Logs:"
echo "  Swoole: runtime/logs/swoole.log"
echo "  Queue: runtime/logs/queue_worker.log"

# 主监控循环
while true; do
    sleep 60
    
    # 检查并重启 Swoole
    if ! ps -p $SWOOLE_PID > /dev/null; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Swoole process died, restarting..."
        php think swoole:start >> runtime/logs/swoole.log 2>&1 &
        SWOOLE_PID=$!
    fi
    
    # 检查并重启队列
    if ! ps -p $QUEUE_PID > /dev/null; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Queue worker died, restarting..."
        php think queue:work --queue WechatMediaDownload >> runtime/logs/queue_worker.log 2>&1 &
        QUEUE_PID=$!
    fi
    
    # 内存监控
    MEM_USAGE=$(free -m | awk 'NR==2{printf "%.2f%%", $3*100/$2}')
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Memory usage: $MEM_USAGE"
done