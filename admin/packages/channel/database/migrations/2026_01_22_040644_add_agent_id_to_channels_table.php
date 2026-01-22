<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->unsignedBigInteger('agent_id')->nullable()->after('app_id')->comment('绑定的智能体ID');
            $table->index(['agent_id'], 'idx_channel_agent');
            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->dropIndex('idx_channel_agent');
            $table->dropColumn('agent_id');
        });
    }
};
