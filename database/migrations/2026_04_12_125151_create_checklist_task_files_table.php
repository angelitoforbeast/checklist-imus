<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_task_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_task_id')->constrained()->onDelete('cascade');
            $table->string('file_path');
            $table->string('file_original_name')->nullable();
            $table->string('file_mime')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // Migrate existing reference_image data to the new table
        $tasks = DB::table('checklist_tasks')->whereNotNull('reference_image')->where('reference_image', '!=', '')->get();
        foreach ($tasks as $task) {
            DB::table('checklist_task_files')->insert([
                'checklist_task_id' => $task->id,
                'file_path' => $task->reference_image,
                'file_original_name' => basename($task->reference_image),
                'file_mime' => 'image/png',
                'sort_order' => 0,
                'created_at' => $task->created_at,
                'updated_at' => $task->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_task_files');
    }
};
