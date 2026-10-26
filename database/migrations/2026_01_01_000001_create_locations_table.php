<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 廠區 → 區域 → 棟別 → 樓層 → 儲位/儲槽，以 parent_id 自我關聯成樹
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique()->comment('儲位編號');
            $table->string('name', 100)->comment('儲位名稱');
            $table->string('level', 20)->comment('階層: factory/area/building/floor/location');
            $table->foreignId('parent_id')->nullable()->constrained('locations')->restrictOnDelete()->comment('上級節點');
            $table->string('type', 50)->nullable()->comment('儲位類型');
            $table->decimal('capacity', 14, 3)->nullable()->comment('容量上限');
            $table->string('capacity_unit', 20)->nullable();
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->index(['parent_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
