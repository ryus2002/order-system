<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\DB;

class DatabaseServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // 實現讀寫分離
        $this->implementReadWriteSeparation();
    }

    /**
     * 實現讀寫分離邏輯
     *
     * @return void
     */
    private function implementReadWriteSeparation()
    {
        // 獲取當前請求方法
        $method = request()->method();
        
        // 根據請求方法決定使用讀或寫連接
        // GET 請求使用讀連接，其他請求使用寫連接
        if ($method === 'GET') {
            // 使用讀連接
            DB::setDefaultConnection('mysql_read');
        } else {
            // 使用寫連接
            DB::setDefaultConnection('mysql');
        }
    }
}