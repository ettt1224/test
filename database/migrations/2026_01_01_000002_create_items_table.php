<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('物品代號');
            $table->string('name_zh', 200)->comment('中文名稱');
            $table->string('name_en', 200)->nullable()->comment('英文名稱');
            $table->string('cas_no', 100)->nullable()->comment('CAS No. / 管制編號');
            $table->string('state', 20)->nullable()->comment('物品狀態: solid/liquid/gas');
            $table->string('control_category', 20)->default('general')->comment('管制分類');
            $table->json('hazard_labels')->nullable()->comment('危害標示');
            $table->string('default_unit', 20)->nullable()->comment('預設計量單位');
            $table->string('sds_path')->nullable()->comment('SDS 檔案路徑');
            $table->string('sds_original_name')->nullable();
            $table->timestamps();

            $table->index('control_category');
            $table->index('cas_no');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
