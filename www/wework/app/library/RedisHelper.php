<?php

namespace app\library;

use think\facade\Cache;
use think\facade\Log;
use Predis\Client;
use Predis\Connection\ConnectionException;

/**
 * Redis 助手类
 * 提供统一的 Redis 连接和常用操作方法
 */
class RedisHelper {
    /**
     * Redis 客户端实例
     * @var Client
     */
    protected static $instance;

    /**
     * 获取 Redis 客户端实例（单例模式）
     * @return Client
     */
    public static function getInstance() {
        if (empty(self::$instance)) {
            self::$instance = self::createClient();
        }
        return self::$instance;
    }

    /**
     * 创建 Redis 客户端连接
     * @return Client
     */
    protected static function createClient() {
        try {
            $config = config('cache.stores.redis');

            // 构建连接参数
            $parameters = [
                'scheme' => $config['scheme'] ?? 'tcp',
                'host' => $config['host'] ?? '127.0.0.1',
                'port' => $config['port'] ?? 6379,
                'password' => $config['password'] ?? null,
                'database' => $config['select'] ?? 0,
                'timeout' => $config['timeout'] ?? 5.0,
            ];

            // 构建客户端选项
            $options = [
                'prefix' => $config['prefix'] ?? '',
                'connection_persistent' => true, // 启用长连接
                'exceptions' => true, // 启用异常处理
            ];

            // 创建 Redis 客户端
            $client = new Client($parameters, $options);

            // 测试连接
            $client->ping();

            return $client;
        } catch (ConnectionException $e) {
            Log::error("Redis连接失败: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 设置缓存（带过期时间）
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int $ttl 过期时间（秒）
     * @return bool
     */
    public static function set(string $key, $value, int $ttl = 0) {
        try {
            $client = self::getInstance();

            if ($ttl > 0) {
                return $client->setex($key, $ttl, serialize($value));
            } else {
                return $client->set($key, serialize($value));
            }
        } catch (\Exception $e) {
            Log::error("Redis设置缓存失败: {$e->getMessage()}, Key: {$key}");
            return false;
        }
    }

    /**
     * 获取缓存
     * @param string $key 缓存键
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get(string $key, $default = null) {
        try {
            $client = self::getInstance();
            $value = $client->get($key);

            if ($value === null) {
                return $default;
            }

            return unserialize($value);
        } catch (\Exception $e) {
            Log::error("Redis获取缓存失败: {$e->getMessage()}, Key: {$key}");
            return $default;
        }
    }

    /**
     * 删除缓存
     * @param string|array $keys 缓存键（单个或数组）
     * @return int 删除的键数量
     */
    public static function delete($keys) {
        try {
            $client = self::getInstance();
            return $client->del((array)$keys);
        } catch (\Exception $e) {
            Log::error("Redis删除缓存失败: {$e->getMessage()}, Keys: " . json_encode($keys));
            return 0;
        }
    }

    /**
     * 判断缓存是否存在
     * @param string $key 缓存键
     * @return bool
     */
    public static function exists(string $key) {
        try {
            $client = self::getInstance();
            return $client->exists($key);
        } catch (\Exception $e) {
            Log::error("Redis判断缓存存在失败: {$e->getMessage()}, Key: {$key}");
            return false;
        }
    }

    /**
     * 设置过期时间
     * @param string $key 缓存键
     * @param int $ttl 过期时间（秒）
     * @return bool
     */
    public static function expire(string $key, int $ttl) {
        try {
            $client = self::getInstance();
            return $client->expire($key, $ttl);
        } catch (\Exception $e) {
            Log::error("Redis设置过期时间失败: {$e->getMessage()}, Key: {$key}");
            return false;
        }
    }

    /**
     * 获取列表长度
     * @param string $key 列表键
     * @return int
     */
    public static function lLen(string $key) {
        try {
            $client = self::getInstance();
            return $client->llen($key);
        } catch (\Exception $e) {
            Log::error("Redis获取列表长度失败: {$e->getMessage()}, Key: {$key}");
            return 0;
        }
    }

    /**
     * 从列表左侧插入元素
     * @param string $key 列表键
     * @param mixed $value 元素值
     * @return int 列表新长度
     */
    public static function lPush(string $key, $value) {
        try {
            $client = self::getInstance();
            return $client->lpush($key, serialize($value));
        } catch (\Exception $e) {
            Log::error("Redis列表左插入失败: {$e->getMessage()}, Key: {$key}");
            return 0;
        }
    }

    /**
     * 从列表右侧弹出元素
     * @param string $key 列表键
     * @return mixed
     */
    public static function rPop(string $key) {
        try {
            $client = self::getInstance();
            $value = $client->rpop($key);

            if ($value === null) {
                return null;
            }

            return unserialize($value);
        } catch (\Exception $e) {
            Log::error("Redis列表右弹出失败: {$e->getMessage()}, Key: {$key}");
            return null;
        }
    }

    /**
     * 获取哈希表中的字段值
     * @param string $key 哈希表键
     * @param string $field 字段名
     * @return mixed
     */
    public static function hGet(string $key, string $field) {
        try {
            $client = self::getInstance();
            $value = $client->hget($key, $field);

            if ($value === null) {
                return null;
            }

            return unserialize($value);
        } catch (\Exception $e) {
            Log::error("Redis获取哈希字段失败: {$e->getMessage()}, Key: {$key}, Field: {$field}");
            return null;
        }
    }

    /**
     * 设置哈希表中的字段值
     * @param string $key 哈希表键
     * @param string $field 字段名
     * @param mixed $value 字段值
     * @return bool
     */
    public static function hSet(string $key, string $field, $value) {
        try {
            $client = self::getInstance();
            return $client->hset($key, $field, serialize($value));
        } catch (\Exception $e) {
            Log::error("Redis设置哈希字段失败: {$e->getMessage()}, Key: {$key}, Field: {$field}");
            return false;
        }
    }

    /**
     * 获取分布式锁
     * @param string $key 锁键
     * @param int $timeout 超时时间（秒）
     * @param int $expire 锁过期时间（秒）
     * @return bool|string 返回锁值（获取成功）或 false（获取失败）
     */
    public static function getLock(string $key, int $timeout = 10, int $expire = 30) {
        $lockKey = "lock:{$key}";
        $lockValue = uniqid();
        $client = self::getInstance();

        $startTime = microtime(true);

        while (true) {
            // 使用 setnx 命令尝试获取锁
            $result = $client->set($lockKey, $lockValue, 'NX', 'EX', $expire);

            if ($result) {
                return $lockValue;
            }

            // 检查是否超时
            if (microtime(true) - $startTime > $timeout) {
                return false;
            }

            // 等待一段时间后重试
            usleep(100000); // 100毫秒
        }
    }

    /**
     * 释放分布式锁
     * @param string $key 锁键
     * @param string $lockValue 锁值
     * @return bool
     */
    public static function releaseLock(string $key, string $lockValue) {
        $lockKey = "lock:{$key}";
        $client = self::getInstance();

        // 使用 Lua 脚本保证原子性
        $script = <<<LUA
if redis.call("GET", KEYS[1]) == ARGV[1] then
    return redis.call("DEL", KEYS[1])
else
    return 0
end
LUA;

        return $client->eval($script, 1, $lockKey, $lockValue);
    }
}