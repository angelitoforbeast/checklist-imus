<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Change description from varchar(255) to TEXT
        DB::statement('ALTER TABLE checklist_tasks MODIFY description TEXT NULL');

        // Add instructions column
            Schema::table('checklist_tasks', function (Blueprint $table) {
                $table->text('instructions')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        Schema::table('checklist_tasks', function (Blueprint $table) {
            $table->dropColumn('instructions');
        });
        DB::statement('ALTER TABLE checklist_tasks MODIFY description VARCHAR(255) NULL');
    }
};
