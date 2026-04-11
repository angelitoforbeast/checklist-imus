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
        Schema::create('checklist_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_task_id')->constrained('checklist_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->text('notes')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();

            $table->unique(['checklist_task_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checklist_submissions');
    }
};
