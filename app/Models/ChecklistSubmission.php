<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChecklistSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'checklist_task_id',
        'user_id',
        'date',
        'notes',
        'file_path',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function task()
    {
        return $this->belongsTo(ChecklistTask::class, 'checklist_task_id')->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function files()
    {
        return $this->hasMany(ChecklistSubmissionFile::class);
    }

    public function logs()
    {
        return $this->hasMany(ChecklistSubmissionLog::class);
    }

    public function analysisLogs()
    {
        return $this->hasMany(ChecklistAnalysisLog::class, 'submission_id');
    }
}
