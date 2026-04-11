<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistSubmissionFile extends Model
{
    protected $fillable = [
        'checklist_submission_id', 'file_path', 'file_original_name', 'file_mime', 'sort_order',
    ];

    public function submission()
    {
        return $this->belongsTo(ChecklistSubmission::class, 'checklist_submission_id');
    }

    public function isImage(): bool
    {
        return $this->file_mime && str_starts_with($this->file_mime, 'image/');
    }
}
