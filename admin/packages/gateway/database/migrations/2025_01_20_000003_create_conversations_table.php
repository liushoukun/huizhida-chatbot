<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() : void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_id', 64)->unique()->comment('会话ID（系统生成）');
            $table->string('agent_conversation_id', 128)->index()->nullable()->comment('智能体会话ID');
            $table->string('channel_conversation_id', 128)->nullable()->index()->comment('渠道会话ID（渠道方提供）');

            // 渠道信息
            $table->unsignedBigInteger('app_id')->index()->comment('应用ID');
            $table->unsignedBigInteger('channel_id')->nullable()->index()->comment('渠道ID（关联channels表）');
            $table->string('channel_type', 32)->index()->comment('渠道类型: wecom, taobao, douyin等');


            // 渠道用户信息
            $table->string('channel_user_id', 128)->index()->comment('渠道用户ID');
            $table->string('user_nickname', 100)->nullable()->comment('用户昵称');
            $table->string('user_avatar', 255)->nullable()->comment('用户头像');
            $table->boolean('is_vip')->default(false)->comment('是否VIP');
            $table->json('user_tags')->nullable()->comment('用户标签');
            $table->json('user_extra')->nullable()->comment('用户扩展信息');

            // 会话状态
            $table->string('status', 20)->default('active')->comment('状态: active, pending_agent, pending_human, transferred, closed');

            // Agent 分配
            $table->unsignedBigInteger('assigned_agent_id')->nullable()->index()->comment('分配的Agent ID');
            $table->unsignedBigInteger('current_agent_id')->nullable()->index()->comment('当前处理的Agent ID');

            // 上下文和转接信息
            $table->json('context')->nullable()->comment('上下文数据');
            $table->string('transfer_reason', 100)->nullable()->comment('转接原因');
            $table->string('transfer_source', 20)->nullable()->comment('转接来源: rule, agent');
            $table->timestamp('transfer_time')->nullable()->comment('转接时间');
            $table->string('assigned_human', 64)->nullable()->comment('分配的人工客服');

            $table->timestamps();
            $table->timestamp('closed_at')->nullable()->comment('关闭时间');

            // 索引
            $table->index(['channel_type', 'channel_user_id', 'app_id'], 'idx_conversation_channel_user');
            $table->index(['assigned_agent_id', 'status'], 'idx_conversation_agent_status');
        });
    }

    public function down() : void
    {
        Schema::dropIfExists('conversations');
    }
};
