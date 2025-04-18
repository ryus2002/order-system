<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Support\Facades\Log;

class ConsumeOrderQueue extends Command
{
    /**
     * 命令名稱
     *
     * @var string
     */
    protected $signature = 'queue:consume-orders';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '消費Kafka訂單隊列，處理訂單';

    /**
     * 訂單服務
     *
     * @var OrderService
     */
    protected $orderService;

    /**
     * 創建命令實例
     *
     * @param OrderService $orderService
     * @return void
     */
    public function __construct(OrderService $orderService)
    {
        parent::__construct();
        $this->orderService = $orderService;
    }

    /**
     * 執行命令
     *
     * @return int
     */
    public function handle()
    {
        $this->info('開始消費訂單隊列...');

        // 在實際環境中，這裡會使用Kafka客戶端從隊列中消費訂單消息
        // 這裡使用模擬方式實現

        try {
            // 模擬Kafka消費者的無限循環
            while (true) {
                // 模擬從Kafka獲取消息
                $this->processOrderMessage();
                
                // 避免CPU使用率過高
                sleep(1);
            }
        } catch (\Exception $e) {
            $this->error('處理訂單隊列時出錯: ' . $e->getMessage());
            Log::error('訂單隊列處理錯誤', ['error' => $e->getMessage()]);
            return 1;
        }

        return 0;
    }

    /**
     * 處理訂單消息
     *
     * @return void
     */
    private function processOrderMessage()
    {
        // 在實際環境中，這裡會處理從Kafka接收到的真實訂單消息
        // 這裡使用模擬方式實現
        
        $this->info('處理訂單消息...');
        
        // 模擬處理邏輯：獲取所有待處理訂單並更新狀態
        $pendingOrders = Order::where('status', Order::STATUS_PENDING)
            ->take(10)
            ->get();
            
        foreach ($pendingOrders as $order) {
            try {
                // 模擬訂單處理
                $this->info("處理訂單 #{$order->id}");
                
                // 更新訂單狀態為處理中
                $this->orderService->updateOrderStatus(
                    $order->id, 
                    Order::STATUS_PROCESSING, 
                    '訂單開始處理'
                );
                
                // 模擬處理時間
                sleep(1);
                
                // 模擬處理完成，更新訂單狀態
                $this->orderService->updateOrderStatus(
                    $order->id, 
                    Order::STATUS_COMPLETED, 
                    '訂單處理完成'
                );
                
                $this->info("訂單 #{$order->id} 處理完成");
            } catch (\Exception $e) {
                $this->error("處理訂單 #{$order->id} 失敗: " . $e->getMessage());
                
                // 更新訂單狀態為失敗
                $this->orderService->updateOrderStatus(
                    $order->id, 
                    Order::STATUS_FAILED, 
                    '訂單處理失敗: ' . $e->getMessage()
                );
            }
        }
    }
}