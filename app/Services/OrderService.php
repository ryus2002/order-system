<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\OrderStatusLog;
use App\Services\RedisLockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class OrderService
{
    /**
     * Redis鎖服務
     *
     * @var RedisLockService
     */
    protected $lockService;

    /**
     * 建立訂單服務實例
     *
     * @param RedisLockService $lockService
     * @return void
     */
    public function __construct(RedisLockService $lockService)
    {
        $this->lockService = $lockService;
    }

    /**
     * 創建訂單並使用Redis分散式鎖防止超賣
     *
     * @param array $orderData
     * @return Order
     * @throws Exception
     */
    public function createOrder(array $orderData)
    {
        $productId = $orderData['product_id'];
        $quantity = $orderData['quantity'];
        $userId = $orderData['user_id'];

        // 生成產品鎖的鍵名
        $lockKey = "product_lock:{$productId}";
        
        // 使用分散式鎖執行訂單創建邏輯
        return $this->lockService->withLock($lockKey, function () use ($productId, $quantity, $userId, $orderData) {
            // 獲取產品信息
            $product = Product::find($productId);
            
            if (!$product) {
                throw new Exception('產品不存在');
            }
            
            // 檢查庫存是否足夠
            if ($product->stock < $quantity) {
                throw new Exception('庫存不足');
            }
            
            // 開始事務
            DB::beginTransaction();
            
            try {
                // 更新庫存
                $product->stock -= $quantity;
                $product->save();
                
                // 計算總價
                $totalPrice = $product->price * $quantity;
                
                // 創建訂單
                $order = Order::create([
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'total_price' => $totalPrice,
                    'status' => Order::STATUS_PENDING,
                    'payment_status' => Order::PAYMENT_UNPAID,
                    'shipping_status' => Order::SHIPPING_UNSHIPPED,
                    'remarks' => $orderData['remarks'] ?? null,
                ]);
                
                // 記錄訂單狀態變更
                OrderStatusLog::create([
                    'order_id' => $order->id,
                    'previous_status' => '',
                    'new_status' => Order::STATUS_PENDING,
                    'remarks' => '訂單創建',
                ]);
                
                // 提交事務
                DB::commit();
                
                // 發送訂單創建事件到Kafka
                $this->sendOrderToKafka($order);
                
                return $order;
                
            } catch (Exception $e) {
                // 回滾事務
                DB::rollBack();
                Log::error('創建訂單失敗: ' . $e->getMessage());
                throw $e;
            }
        });
    }
    
    /**
     * 發送訂單到Kafka消息隊列
     *
     * @param Order $order
     * @return void
     */
    private function sendOrderToKafka(Order $order)
    {
        // 在實際環境中，這裡會使用Kafka客戶端將訂單發送到消息隊列
        // 這裡使用日誌模擬
        Log::info('訂單已發送到Kafka隊列', ['order_id' => $order->id]);
        
        // TODO: 實現實際的Kafka生產者代碼
        // 可以使用 "nmred/kafka-php" 等Kafka客戶端庫
    }
    
    /**
     * 更新訂單狀態
     *
     * @param int $orderId
     * @param string $newStatus
     * @param string $remarks
     * @return Order
     */
    public function updateOrderStatus($orderId, $newStatus, $remarks = null)
    {
        $order = Order::findOrFail($orderId);
        $previousStatus = $order->status;
        
        // 開始事務
        DB::beginTransaction();
        
        try {
            // 更新訂單狀態
            $order->status = $newStatus;
            $order->save();
            
            // 記錄狀態變更
            OrderStatusLog::create([
                'order_id' => $order->id,
                'previous_status' => $previousStatus,
                'new_status' => $newStatus,
                'remarks' => $remarks,
            ]);
            
            // 提交事務
            DB::commit();
            
            return $order;
        } catch (Exception $e) {
            // 回滾事務
            DB::rollBack();
            Log::error('更新訂單狀態失敗: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 取消訂單並恢復庫存
     *
     * @param int $orderId
     * @param string $remarks
     * @return Order
     */
    public function cancelOrder($orderId, $remarks = '用戶取消訂單')
    {
        $order = Order::with('product')->findOrFail($orderId);
        
        // 檢查訂單是否可以取消
        if (!in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_PROCESSING])) {
            throw new Exception('該訂單狀態無法取消');
        }
        
        // 使用分散式鎖執行取消訂單邏輯
        $lockKey = "order_lock:{$orderId}";
        
        return $this->lockService->withLock($lockKey, function () use ($order, $remarks) {
            // 開始事務
            DB::beginTransaction();
            
            try {
                // 恢復庫存
                $product = $order->product;
                $product->stock += $order->quantity;
                $product->save();
                
                // 更新訂單狀態
                $previousStatus = $order->status;
                $order->status = Order::STATUS_CANCELLED;
                $order->save();
                
                // 記錄狀態變更
                OrderStatusLog::create([
                    'order_id' => $order->id,
                    'previous_status' => $previousStatus,
                    'new_status' => Order::STATUS_CANCELLED,
                    'remarks' => $remarks,
                ]);
                
                // 提交事務
                DB::commit();
                
                return $order;
            } catch (Exception $e) {
                // 回滾事務
                DB::rollBack();
                Log::error('取消訂單失敗: ' . $e->getMessage());
                throw $e;
            }
        });
    }
}