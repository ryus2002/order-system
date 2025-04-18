<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    /**
     * 可以批量賦值的屬性
     */
    protected $fillable = [
        'name',
        'description',
        'price',
        'stock',
        'status', // active, inactive
    ];

    /**
     * 產品狀態常量
     */
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    /**
     * 產品與訂單的關聯
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}