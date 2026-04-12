<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistTaskFile extends Model
{
    protected $fillable = [
        'checklist_task_id', 'file_path', 'file_original_name', 'file_mime', 'sort_order',
    ];

    public function task()
    {
        return $this->belongsTo(ChecklistTask::class, 'checklist_task_id');
    }

    public function isImage(): bool
    {
        return $this->file_mime && str_starts_with($this->file_mime, 'image/');
    }

    public function isVideo(): bool
    {
        return $this->file_mime && str_starts_with($this->file_mime, 'video/');
    }
}
