<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 創建一些測試產品
        $products = [
            [
                'name' => '高性能筆記本電腦',
                'description' => '16GB RAM, 512GB SSD, Intel i7處理器',
                'price' => 12999.00,
                'stock' => 100,
                'status' => 'active'
            ],
            [
                'name' => '智能手機',
                'description' => '6.5英寸屏幕, 128GB存儲, 高清相機',
                'price' => 4999.00,
                'stock' => 200,
                'status' => 'active'
            ],
            [
                'name' => '無線耳機',
                'description' => '藍牙5.0, 主動降噪, 30小時續航',
                'price' => 999.00,
                'stock' => 300,
                'status' => 'active'
            ],
            [
                'name' => '智能手錶',
                'description' => '心率監測, 防水, 多種運動模式',
                'price' => 1599.00,
                'stock' => 150,
                'status' => 'active'
            ],
            [
                'name' => '平板電腦',
                'description' => '10.9英寸屏幕, 64GB存儲, 支持手寫筆',
                'price' => 3299.00,
                'stock' => 80,
                'status' => 'active'
            ]
        ];

        foreach ($products as $productData) {
            // 檢查產品是否已存在
            if (!Product::where('name', $productData['name'])->exists()) {
                Product::create($productData);
            }
        }
    }
}
