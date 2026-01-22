<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use HuiZhiDa\Agent\Domain\Models\Enums\AgentStatus;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('owner_type', 32)->comment('所属者类型');
            $table->string('owner_id', 64)->comment('所属者ID');
            $table->string('name', 100)->comment('智能体名称');
            $table->string('agent_type', 20)->default('tencent_yuanqi')->comment('智能体类型');
            $table->string('provider', 20)->nullable()->comment('提供者(ollama/openai/qwen/coze等)');
            $table->json('config')->nullable()->comment('配置信息(加密)');
            $table->unsignedBigInteger('fallback_agent_id')->nullable()->comment('降级智能体ID');
            $table->tinyInteger('status')->default(AgentStatus::ENABLED->value)->comment('状态');
            $table->operator();
            $table->softDeletes();

            // 索引
            $table->index(['owner_type', 'owner_id'], 'idx_agent_owner');
            $table->index(['agent_type'], 'idx_agent_type');
            $table->index(['provider'], 'idx_agent_provider');
            $table->index(['status'], 'idx_agent_status');
            $table->index(['fallback_agent_id'], 'idx_agent_fallback');
            $table->foreign('fallback_agent_id')->references('id')->on('agents')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
