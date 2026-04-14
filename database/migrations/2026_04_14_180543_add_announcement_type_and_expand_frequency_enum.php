<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE checklist_tasks MODIFY COLUMN type ENUM('photo','note','any','both','photo_note','announcement') NOT NULL DEFAULT 'photo_note'");
        DB::statement("ALTER TABLE checklist_tasks MODIFY COLUMN frequency ENUM('daily','once','weekly','monthly','custom') NOT NULL DEFAULT 'daily'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE checklist_tasks MODIFY COLUMN type ENUM('photo','note','any','both','photo_note') NOT NULL DEFAULT 'photo_note'");
        DB::statement("ALTER TABLE checklist_tasks MODIFY COLUMN frequency ENUM('daily','once') NOT NULL DEFAULT 'daily'");
    }
};
