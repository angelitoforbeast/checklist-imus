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
        Schema::create('checklist_submission_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_submission_id')->constrained('checklist_submissions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('action', ['submitted', 'updated'])->default('submitted');
            $table->text('notes_snapshot')->nullable();
            $table->integer('file_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_submission_logs');
    }
};
