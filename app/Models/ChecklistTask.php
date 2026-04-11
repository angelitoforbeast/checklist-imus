<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChecklistTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'type',
        'is_active',
        'sort_order',
        'ai_prompt',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function submissions()
    {
        return $this->hasMany(ChecklistSubmission::class);
    }

    public function submissionForDate($date)
    {
        return $this->submissions()->where('date', $date)->first();
    }
}
