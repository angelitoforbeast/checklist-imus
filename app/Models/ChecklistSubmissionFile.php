<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistSubmissionFile extends Model
{
    protected $fillable = [
        'checklist_submission_id',
        'file_path',
        'original_name',
    ];

    public function submission()
    {
        return $this->belongsTo(ChecklistSubmission::class, 'checklist_submission_id');
    }
}
