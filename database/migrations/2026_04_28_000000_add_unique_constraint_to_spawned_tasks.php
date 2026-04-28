<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE checklist_tasks ADD COLUMN spawn_date DATE GENERATED ALWAYS AS (DATE(created_at)) STORED NULL");

        Schema::table('checklist_tasks', function (Blueprint $table) {
            $table->unique(['parent_task_id', 'spawn_date', 'spawn_index'], 'unique_spawn_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_tasks', function (Blueprint $table) {
            $table->dropUnique('unique_spawn_per_day');
        });

        DB::statement("ALTER TABLE checklist_tasks DROP COLUMN spawn_date");
    }
};
