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
        Schema::table('checklist_analysis_logs', function (Blueprint $table) {
            $table->string('log_type')->default('analysis')->after('user_id');
            $table->string('verdict')->nullable()->after('analysis_result');
            $table->index('log_type');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_analysis_logs', function (Blueprint $table) {
            $table->dropIndex(['log_type']);
            $table->dropColumn(['log_type', 'verdict']);
        });
    }
};
