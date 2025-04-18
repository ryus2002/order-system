<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Exception;

class RedisLockService
{
    /**
     * 嘗試獲取分散式鎖
     *
     * @param string $lockKey 鎖的鍵名
     * @param string $lockValue 鎖的值（通常是請求ID）
     * @param int $ttl 鎖的生存時間（秒）
     * @return bool 是否成功獲取鎖
     */
    public function acquire(string $lockKey, string $lockValue, int $ttl = 5): bool
    {
        // 使用 Redis SET 命令的 NX 選項，只有當 key 不存在時才設置
        // EX 選項設置過期時間，防止死鎖
        return (bool) Redis::set($lockKey, $lockValue, 'EX', $ttl, 'NX');
    }

    /**
     * 釋放分散式鎖
     * 使用 Lua 腳本確保原子性操作，只有當鎖的值匹配時才刪除
     *
     * @param string $lockKey 鎖的鍵名
     * @param string $lockValue 鎖的值（通常是請求ID）
     * @return bool 是否成功釋放鎖
     */
    public function release(string $lockKey, string $lockValue): bool
    {
        $script = <<<LUA
if redis.call('get', KEYS[1]) == ARGV[1] then
    return redis.call('del', KEYS[1])
else
    return 0
end
LUA;
        
        return (bool) Redis::eval($script, 1, $lockKey, $lockValue);
    }

    /**
     * 使用回調函數在鎖內執行操作
     *
     * @param string $lockKey 鎖的鍵名
     * @param callable $callback 在鎖內執行的回調函數
     * @param int $ttl 鎖的生存時間（秒）
     * @param int $retries 重試次數
     * @param int $sleepMilliseconds 重試間隔（毫秒）
     * @return mixed 回調函數的返回值
     * @throws Exception 如果無法獲取鎖或執行過程中出錯
     */
    public function withLock(string $lockKey, callable $callback, int $ttl = 5, int $retries = 3, int $sleepMilliseconds = 200)
    {
        $lockValue = uniqid('', true);
        $acquired = false;
        
        // 嘗試獲取鎖，如果失敗則重試
        for ($i = 0; $i <= $retries; $i++) {
            if ($this->acquire($lockKey, $lockValue, $ttl)) {
                $acquired = true;
                break;
            }
            
            if ($i < $retries) {
                usleep($sleepMilliseconds * 1000);
            }
        }
        
        if (!$acquired) {
            throw new Exception('無法獲取分散式鎖，系統繁忙，請稍後再試');
        }
        
        try {
            // 在鎖內執行回調函數
            return $callback();
        } finally {
            // 確保無論如何都釋放鎖
            $this->release($lockKey, $lockValue);
        }
    }
}