<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistSubmissionLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'checklist_submission_id', 'user_id', 'action', 'notes_snapshot', 'file_count', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function submission()
    {
        return $this->belongsTo(ChecklistSubmission::class, 'checklist_submission_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
