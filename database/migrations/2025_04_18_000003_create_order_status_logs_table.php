<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_status_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('previous_status')->nullable(); // 允许为null，表示初始状态
            $table->string('new_status');
            $table->text('remarks')->nullable();
            $table->timestamps();
            
            // 外鍵約束
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            
            // 索引
            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_logs');
    }
};