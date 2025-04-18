<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderStatusLog extends Model
{
    use HasFactory;

    /**
     * 可以批量賦值的屬性
     */
    protected $fillable = [
        'order_id',
        'previous_status',
        'new_status',
        'remarks',
    ];

    /**
     * 狀態日誌與訂單的關聯
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}