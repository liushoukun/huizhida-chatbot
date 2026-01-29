<?php

use HuiZhiDa\Core\Domain\Channel\Models\Enums\ChannelStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up() : void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('app_id')->comment('应用ID');
            $table->string('channel', 20)->comment('渠道类型');
            $table->json('config')->nullable()->comment('配置信息(加密)');

            $table->unsignedBigInteger('agent_id')->nullable()->after('app_id')->comment('绑定的智能体ID');

            $table->tinyInteger('status')->default(ChannelStatus::ENABLED->value)->comment('状态');
            $table->operator();
            $table->softDeletes();

            // 索引
            $table->index(['app_id'], 'idx_channel_app');
            $table->index(['channel'], 'idx_channel_type');
            $table->index(['status'], 'idx_channel_status');
            $table->unique(['app_id', 'channel'], 'uk_channel_app_type');
            $table->index(['agent_id'], 'idx_channel_agent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down() : void
    {
        Schema::dropIfExists('channels');
    }
};
