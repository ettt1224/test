<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 廠內作業紀錄（申報資料的核心來源）
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('tx_no', 30)->unique()->comment('異動單號');
            $table->string('type', 20)->comment('作業類型: in/use/split/transfer/out');
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('quantity', 14, 3)->comment('異動數量');
            $table->string('unit', 20)->comment('計量單位');
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('operator', 50)->comment('操作人員');
            $table->dateTime('occurred_at')->comment('作業時間');
            $table->string('batch_no', 100)->nullable()->comment('批號');
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'occurred_at']);
            $table->index(['type', 'occurred_at']);
        });

        // 即時庫存（依物品 × 儲位 × 單位彙總，由作業紀錄維護）
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->string('unit', 20);
            $table->decimal('quantity', 14, 3)->default(0);
            $table->timestamps();

            $table->unique(['item_id', 'location_id', 'unit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocks');
        Schema::dropIfExists('transactions');
    }
};
