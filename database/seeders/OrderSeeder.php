<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 獲取所有用戶和產品
        $users = User::all();
        $products = Product::all();
        
        // 如果沒有用戶或產品，則不創建訂單
        if ($users->isEmpty() || $products->isEmpty()) {
            $this->command->info('沒有找到用戶或產品，跳過訂單創建');
            return;
        }
        
        // 訂單狀態列表
        $statuses = ['pending', 'processing', 'completed', 'cancelled', 'failed'];
        
        // 為每個用戶創建幾個訂單
        foreach ($users as $user) {
            // 每個用戶創建1-3個訂單
            $orderCount = rand(1, 3);
            
            for ($i = 0; $i < $orderCount; $i++) {
                // 隨機選擇一個產品
                $product = $products->random();
                
                // 隨機數量，但不超過庫存
                $quantity = rand(1, min(3, $product->stock));
                
                // 計算總價
                $totalPrice = $product->price * $quantity;
                
                // 隨機選擇一個狀態
                $status = $statuses[array_rand($statuses)];
                
                // 創建訂單
                $order = Order::create([
                    'user_id' => $user->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'total_price' => $totalPrice,
                    'status' => $status,
                    'remarks' => '測試訂單 #' . ($i + 1) . ' 用戶: ' . $user->name
                ]);
                
                // 創建訂單狀態日誌 - 第一條日誌，表示訂單創建
                OrderStatusLog::create([
                    'order_id' => $order->id,
                    'previous_status' => null, // 初始狀態，previous_status 為 null
                    'new_status' => 'pending',
                    'remarks' => '訂單已創建'
                ]);
                
                // 如果訂單狀態不是pending，添加額外的狀態日誌
                if ($status !== 'pending') {
                    OrderStatusLog::create([
                        'order_id' => $order->id,
                        'previous_status' => 'pending',
                        'new_status' => $status,
                        'remarks' => '訂單狀態已更新為: ' . $status
                    ]);
                }
                
                // 如果訂單完成，更新產品庫存
                if ($status === 'completed') {
                    $product->stock -= $quantity;
                    $product->save();
                }
            }
        }
    }
}