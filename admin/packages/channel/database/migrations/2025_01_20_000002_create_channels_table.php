<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use HuiZhiDa\Channel\Domain\Models\Enums\ChannelStatus;
use HuiZhiDa\Channel\Domain\Models\Enums\ChannelType;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('app_id')->comment('应用ID');
            $table->string('channel', 20)->default(ChannelType::WEBHOOK->value)->comment('渠道类型');
            $table->json('config')->nullable()->comment('配置信息(加密)');
            $table->tinyInteger('status')->default(ChannelStatus::ENABLED->value)->comment('状态');
            $table->operator();
            $table->softDeletes();

            // 索引
            $table->index(['app_id'], 'idx_channel_app');
            $table->index(['channel'], 'idx_channel_type');
            $table->index(['status'], 'idx_channel_status');
            $table->unique(['app_id', 'channel'], 'uk_channel_app_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
