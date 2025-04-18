<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 創建一個管理員用戶，如果不存在
        if (!User::where('email', 'admin@example.com')->exists()) {
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now()
        ]);
        }

        // 創建一些普通測試用戶，如果不存在
        if (!User::where('email', 'user1@example.com')->exists()) {
        User::create([
            'name' => 'Test User 1',
            'email' => 'user1@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now()
        ]);
        }

        if (!User::where('email', 'user2@example.com')->exists()) {
        User::create([
            'name' => 'Test User 2',
            'email' => 'user2@example.com',
            'password' => Hash::make('password'),
            'email_verified_at' => now()
        ]);
    }

        // 如果需要更多用戶，可以使用工廠批量創建
        // 創建10個隨機用戶，確保郵箱不重複
        // User::factory()->count(10)->create();
}
}