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
        Schema::table('checklist_submission_files', function (Blueprint $table) {
            $table->renameColumn('original_name', 'file_original_name');
        });

        Schema::table('checklist_submission_files', function (Blueprint $table) {
            $table->string('file_mime')->nullable()->after('file_original_name');
            $table->integer('sort_order')->default(0)->after('file_mime');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('checklist_submission_files', function (Blueprint $table) {
            $table->dropColumn(['file_mime', 'sort_order']);
        });

        Schema::table('checklist_submission_files', function (Blueprint $table) {
            $table->renameColumn('file_original_name', 'original_name');
        });
    }
};
