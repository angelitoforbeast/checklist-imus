<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistAnalysisLog extends Model
{
    protected $fillable = ['submission_id', 'user_id', 'prompt_used', 'analysis_result'];

    public function submission()
    {
        return $this->belongsTo(ChecklistSubmission::class, 'submission_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
