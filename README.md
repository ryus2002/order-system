# 高併發訂單處理系統

這是一個基於 Laravel 框架開發的高併發訂單處理系統，採用了多種現代化技術來確保系統在高負載情況下的穩定性和可靠性。

## 系統特點

- **Redis 分散式鎖**：防止商品超賣，確保庫存一致性
- **Kafka 消息隊列**：異步處理訂單，提高系統吞吐量
- **DB 讀寫分離**：優化數據庫訪問，提升系統性能
- **響應式前端設計**：提供良好的用戶體驗

## 技術棧

- **後端**：Laravel 12.x
- **前端**：HTML, CSS, JavaScript, Bootstrap 5
- **數據庫**：MySQL (主從架構)
- **緩存**：Redis
- **消息隊列**：Kafka
- **Web 服務器**：Nginx

## 系統架構

```
                                 ┌─────────────┐
                                 │    Client   │
                                 └──────┬──────┘
                                        │
                                        ▼
                                 ┌─────────────┐
                                 │    Nginx    │
                                 └──────┬──────┘
                                        │
                                        ▼
┌────────────────────────────────────────────────────────────┐
│                        Laravel App                         │
├────────────┬───────────────┬───────────────┬───────────────┤
│  API Layer │ Service Layer │ Business Logic│    Models     │
└─────┬──────┴───────┬───────┴───────┬───────┴───────┬───────┘
      │              │               │               │
      ▼              ▼               ▼               ▼
┌──────────┐  ┌─────────────┐  ┌──────────┐  ┌─────────────┐
│  Redis   │  │   Kafka     │  │MySQL主庫 │  │ MySQL從庫   │
│(分散式鎖)│  │(消息隊列)   │  │(寫操作) │  │ (讀操作)    │
└──────────┘  └─────────────┘  └──────────┘  └─────────────┘
```

## 功能模塊

### 產品管理
- 產品列表展示
- 產品詳情查看
- 庫存實時更新

### 訂單處理
- 訂單創建與提交
- 分布式鎖防止超賣
- 訂單狀態追蹤
- 訂單取消功能

### 系統監控
- 性能指標監控
- 錯誤日誌記錄
- 系統狀態報告

## 安裝與部署

### 系統要求
- PHP >= 8.2
- MySQL >= 8.0
- Redis >= 6.0
- Kafka >= 3.0
- Composer
- Node.js & NPM

### 安裝步驟

1. 克隆代碼庫
   ```bash
   git clone https://your-repository-url/high-concurrency-order-system.git
   cd high-concurrency-order-system
   ```

2. 安裝 PHP 依賴
   ```bash
   composer install
   ```

3. 配置環境變數
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. 修改 `.env` 文件配置數據庫、Redis 和 Kafka 連接

5. 運行數據庫遷移和種子數據
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

6. 啟動開發服務器
   ```bash
   php artisan serve
   ```

7. 啟動消息隊列消費者
   ```bash
   php artisan queue:consume
   ```

## 性能測試

系統在以下配置下進行了壓力測試：

- 測試環境：AWS t3.large 實例
- 並發用戶：1000
- 測試時長：10分鐘
- 平均響應時間：< 200ms
- 成功率：99.9%

詳細的性能測試報告可以在 `docs/performance_test.md` 文件中找到。

## 開發指南

### 目錄結構

```
app/
├── Console/Commands/       # 命令行工具
├── Http/Controllers/       # 控制器
├── Models/                 # 數據模型
├── Providers/              # 服務提供者
└── Services/               # 業務服務層
    ├── OrderService.php    # 訂單服務
    └── RedisLockService.php # Redis 分散式鎖服務

config/                     # 配置文件
database/                   # 數據庫遷移和種子
resources/
├── views/                  # 視圖模板
    └── order-page.blade.php # 訂單頁面
routes/                     # 路由定義
tests/                      # 測試文件
```

### 關鍵代碼說明

#### 分散式鎖實現

```php
// app/Services/RedisLockService.php
public function acquireLock($lockName, $lockValue, $ttl = 10)
{
    return Redis::set($lockName, $lockValue, 'EX', $ttl, 'NX');
}
```

#### 訂單處理流程

```php
// app/Services/OrderService.php
public function createOrder($data)
{
    // 獲取分散式鎖
    $lockName = "product_lock:{$data['product_id']}";
    $lockValue = uniqid();
    
    if (!$this->redisLock->acquireLock($lockName, $lockValue)) {
        throw new \Exception('系統繁忙，請稍後再試');
    }
    
    try {
        // 檢查庫存
        $product = Product::find($data['product_id']);
        if ($product->stock < $data['quantity']) {
            throw new \Exception('庫存不足');
        }
        
        // 創建訂單
        $order = new Order();
        // 設置訂單屬性...
        $order->save();
        
        // 減少庫存
        $product->stock -= $data['quantity'];
        $product->save();
        
        // 發送到消息隊列進行後續處理
        // ...
        
        return $order;
    } finally {
        // 釋放鎖
        $this->redisLock->releaseLock($lockName, $lockValue);
    }
}
```

## 貢獻指南

1. Fork 本項目
2. 創建您的特性分支 (`git checkout -b feature/amazing-feature`)
3. 提交您的更改 (`git commit -m 'Add some amazing feature'`)
4. 推送到分支 (`git push origin feature/amazing-feature`)
5. 開啟一個 Pull Request

## 授權協議

本項目採用 MIT 許可證 - 查看 [LICENSE](LICENSE) 文件了解更多細節。

## 聯繫方式

- 項目維護者：您的姓名
- 電子郵件：your.email@example.com
- 項目鏈接：https://github.com/yourusername/high-concurrency-order-system

## 致謝

- Laravel 團隊提供的優秀框架
- 所有為這個項目做出貢獻的開發者