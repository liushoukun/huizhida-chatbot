<?php

use HuiZhiDa\Core\Domain\Conversation\Enums\ContentType;
use HuiZhiDa\Core\Domain\Conversation\Enums\MessageType;
use HuiZhiDa\Core\Domain\Conversation\Enums\UserType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() : void
    {
        Schema::create('messages', function (Blueprint $table) {
            // 主键
            $table->unsignedBigInteger('id')->primary()->comment('ID');

            // 核心标识
            $table->string('message_id', 64)->unique()->comment('消息ID(系统生成)');
            $table->string('conversation_id', 64)->index()->comment('会话ID(系统生成)');
            $table->string('chat_id', 64)->nullable()->index()->comment('对话ID(系统生成)');
            $table->string('status', 32)->default('pending')->comment('状态: pending, sent, failed');
            // 业务字段
            $table->unsignedBigInteger('app_id')->index()->comment('应用ID');// 冗余

            // 消息
            $table->string('message_type', 32)->comment('消息类型');
            $table->string('content_type', 32)->comment('内容类型');
            $table->json('content')->nullable()->comment('消息内容（Content对象）');
            $table->text('raw_data')->nullable()->comment('原始数据');

            // 发送人
            $table->string('sender_type', 32)->comment(UserType::comments('用户类型'));
            $table->string('sender_id', 128)->index()->comment('用户ID');
            $table->string('sender_nickname', 100)->nullable()->comment('用户昵称');
            $table->string('sender_avatar', 255)->nullable()->comment('用户头像');

            // 外部接受消息时存在
            $table->unsignedBigInteger('channel_id')->index()->comment('渠道ID');
            $table->string('channel_message_id', 128)->nullable()->index()->comment('渠道消息ID');
            $table->string('channel_chat_id', 128)->nullable()->index()->comment('渠道对话ID');
            // 是智能体返回时 存在
            $table->unsignedBigInteger('agent_id')->nullable()->index()->comment('处理智能体ID');
            $table->unsignedBigInteger('agent_message_id')->nullable()->comment('智能体内消息ID');
            $table->unsignedBigInteger('agent_chat_id')->nullable()->comment('智能体内对话ID');

            // 时间字段
            $table->bigInteger('timestamp')->comment('消息时间戳');
            $table->timestamps();
            $table->softDeletes();

            // 索引
            $table->index(['conversation_id', 'timestamp'], 'idx_conversation_timestamp');
            $table->index(['chat_id', 'timestamp'], 'idx_chat_timestamp');


            $table->comment('消息表');
        });
    }

    public function down() : void
    {
        Schema::dropIfExists('messages');
    }
};
