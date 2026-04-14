<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add new columns to checklist_tasks
        Schema::table('checklist_tasks', function (Blueprint $table) {
            $table->string('submission_mode', 20)->default('group')->after('frequency');
            // frequency already exists as 'daily'/'once', we just allow more values now
            $table->json('schedule_days')->nullable()->after('submission_mode');   // for weekly [0-6] or monthly [1-31]
            $table->json('schedule_dates')->nullable()->after('schedule_days');    // for custom specific dates
            $table->date('start_date')->nullable()->after('schedule_dates');
            $table->date('end_date')->nullable()->after('start_date');
        });

        // Modify frequency column to allow new values (just change the default, it's a varchar)
        // Laravel stores enums as strings, so no migration needed for the column type itself

        // Add started_at to checklist_submissions for tracking when user started the task
        Schema::table('checklist_submissions', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_tasks', function (Blueprint $table) {
            $table->dropColumn(['submission_mode', 'schedule_days', 'schedule_dates', 'start_date', 'end_date']);
        });

        Schema::table('checklist_submissions', function (Blueprint $table) {
            $table->dropColumn('started_at');
        });
    }
};
