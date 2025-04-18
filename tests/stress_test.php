<?php

/**
 * 高併發訂單處理系統壓力測試腳本
 * 
 * 使用方法：
 * php tests/stress_test.php
 */

// 設定參數
$apiUrl = 'http://localhost/api/orders';
$concurrentUsers = 100;  // 並發用戶數
$requestsPerUser = 10;   // 每個用戶的請求數
$totalRequests = $concurrentUsers * $requestsPerUser;

echo "開始壓力測試...\n";
echo "並發用戶數: $concurrentUsers\n";
echo "每個用戶請求數: $requestsPerUser\n";
echo "總請求數: $totalRequests\n\n";

// 模擬產品數據
$productId = 1;  // 假設已有產品ID為1的產品
$userId = 1;     // 假設已有用戶ID為1的用戶

// 記錄開始時間
$startTime = microtime(true);

// 創建多進程模擬並發請求
$pids = [];
for ($i = 0; $i < $concurrentUsers; $i++) {
    $pid = pcntl_fork();
    
    if ($pid == -1) {
        die("無法創建子進程");
    } else if ($pid == 0) {
        // 子進程代碼
        for ($j = 0; $j < $requestsPerUser; $j++) {
            // 創建訂單請求
            $data = [
                'user_id' => $userId,
                'product_id' => $productId,
                'quantity' => 1,
                'remarks' => "壓力測試訂單 - 用戶 $i, 請求 $j"
            ];
            
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // 簡單記錄結果
            echo "用戶 $i, 請求 $j: HTTP狀態碼 $httpCode\n";
            
            // 短暫休息，避免完全同時請求
            usleep(rand(10000, 50000));  // 10-50ms
        }
        
        // 子進程完成後退出
        exit(0);
    } else {
        // 父進程記錄子進程PID
        $pids[] = $pid;
    }
}

// 父進程等待所有子進程完成
foreach ($pids as $pid) {
    pcntl_waitpid($pid, $status);
}

// 記錄結束時間
$endTime = microtime(true);
$totalTime = $endTime - $startTime;
$requestsPerSecond = $totalRequests / $totalTime;

echo "\n壓力測試完成\n";
echo "總執行時間: " . number_format($totalTime, 2) . " 秒\n";
echo "每秒處理請求數: " . number_format($requestsPerSecond, 2) . " 請求/秒\n";