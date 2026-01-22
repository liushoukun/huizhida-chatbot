<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 64)->unique()->comment('消息ID');
            $table->string('conversation_id', 64)->index()->comment('会话ID');
            $table->string('app_id', 32)->index()->comment('应用ID');
            $table->string('channel', 20)->index()->comment('渠道');
            $table->tinyInteger('direction')->comment('方向: 1=接收, 2=发送');
            $table->string('message_type', 20)->comment('消息类型');
            $table->json('content')->comment('消息内容');
            $table->string('channel_message_id', 64)->nullable()->comment('渠道消息ID');
            $table->string('sender_type', 20)->comment('发送者类型: user, agent, human');
            $table->unsignedBigInteger('processed_by_agent_id')->nullable()->comment('处理的代理ID');
            $table->string('status', 20)->default('pending')->comment('状态: pending, sent, failed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
