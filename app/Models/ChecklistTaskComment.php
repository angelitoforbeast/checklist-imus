<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistTaskComment extends Model
{
    protected $table = 'checklist_task_comments';

    protected $fillable = [
        'checklist_task_id',
        'checklist_submission_id',
        'user_id',
        'date',
        'message',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function task()
    {
        return $this->belongsTo(ChecklistTask::class, 'checklist_task_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function submission()
    {
        return $this->belongsTo(ChecklistSubmission::class, 'checklist_submission_id');
    }
}
