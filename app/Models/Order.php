<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    /**
     * 可以批量賦值的屬性
     */
    protected $fillable = [
        'user_id',
        'product_id',
        'quantity',
        'total_price',
        'status', // pending, processing, completed, failed, cancelled
        'payment_status', // unpaid, paid, refunded
        'shipping_status', // unshipped, shipped, delivered
        'transaction_id',
        'remarks',
    ];

    /**
     * 訂單狀態常量
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * 支付狀態常量
     */
    const PAYMENT_UNPAID = 'unpaid';
    const PAYMENT_PAID = 'paid';
    const PAYMENT_REFUNDED = 'refunded';

    /**
     * 配送狀態常量
     */
    const SHIPPING_UNSHIPPED = 'unshipped';
    const SHIPPING_SHIPPED = 'shipped';
    const SHIPPING_DELIVERED = 'delivered';

    /**
     * 訂單與用戶的關聯
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 訂單與產品的關聯
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * 訂單狀態變更歷史
     */
    public function statusLogs()
    {
        return $this->hasMany(OrderStatusLog::class);
    }
}