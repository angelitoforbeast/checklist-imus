<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::table('checklist_tasks', function (Blueprint $table) {
            $table->unsignedInteger('required_photos_before_start')->default(0)->after('required_photos');
        });
    }
    public function down(): void
    {
        Schema::table('checklist_tasks', function (Blueprint $table) {
            $table->dropColumn('required_photos_before_start');
        });
    }
};
