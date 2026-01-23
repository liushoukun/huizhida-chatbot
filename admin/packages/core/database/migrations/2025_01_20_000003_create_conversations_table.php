<?php

use HuiZhiDa\Core\Domain\Conversation\Enums\ConversationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() : void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('conversation_id', 64)->unique()->comment('会话ID(系统生成)');
            $table->string('agent_conversation_id', 128)->index()->nullable()->comment('智能体会话ID');
            $table->string('channel_conversation_id', 128)->nullable()->index()->comment('渠道会话ID(系统生成)');

            // 渠道信息
            $table->unsignedBigInteger('app_id')->index()->comment('应用ID');
            $table->unsignedBigInteger('channel_id')->nullable()->index()->comment('渠道ID（关联channels表）');
            $table->unsignedBigInteger('agent_id')->nullable()->index()->comment('智能体（关联agents表）');

            // 渠道用户信息
            $table->string('user_type', 32)->default('user')->comment('用户类型');
            $table->string('user_id', 64)->comment('用户ID');
            $table->string('user_nickname', 64)->nullable()->comment('用户昵称');
            $table->string('user_avatar')->nullable()->comment('用户头像');

            // 会话状态
            $table->string('status', 20)->default(ConversationStatus::Pending)->comment(ConversationStatus::comments('状态'));


            // 上下文和转接信息
            $table->json('context')->nullable()->comment('上下文数据');
            // 转接处理
            $table->string('transfer_reason', 100)->nullable()->comment('转接原因');
            $table->string('transfer_source', 20)->nullable()->comment('转接来源: rule, agent');
            $table->timestamp('transfer_time')->nullable()->comment('转接时间');
            $table->string('assigned_human', 64)->nullable()->comment('分配的人工客服');

            $table->timestamps();
            $table->timestamp('closed_at')->nullable()->comment('关闭时间');



        });
    }

    public function down() : void
    {
        Schema::dropIfExists('conversations');
    }
};
