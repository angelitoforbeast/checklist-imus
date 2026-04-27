<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Expand frequency enum to include 'recurring_on_complete'
        DB::statement("ALTER TABLE checklist_tasks MODIFY COLUMN frequency ENUM('daily','once','weekly','monthly','custom','recurring_on_complete') NOT NULL DEFAULT 'daily'");

        // 2. Add new columns for recurring-on-complete feature
        Schema::table('checklist_tasks', function (Blueprint $table) {
            $table->unsignedInteger('respawn_delay_minutes')->nullable()->after('frequency');
            $table->unsignedInteger('max_daily_count')->nullable()->after('respawn_delay_minutes');
            $table->unsignedBigInteger('parent_task_id')->nullable()->after('max_daily_count');
            $table->unsignedInteger('spawn_index')->nullable()->after('parent_task_id');

            $table->foreign('parent_task_id')
                  ->references('id')
                  ->on('checklist_tasks')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_tasks', function (Blueprint $table) {
            $table->dropForeign(['parent_task_id']);
            $table->dropColumn(['respawn_delay_minutes', 'max_daily_count', 'parent_task_id', 'spawn_index']);
        });

        DB::statement("ALTER TABLE checklist_tasks MODIFY COLUMN frequency ENUM('daily','once','weekly','monthly','custom') NOT NULL DEFAULT 'daily'");
    }
};
