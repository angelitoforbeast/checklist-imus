<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class ChecklistTask extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title', 'description', 'instructions', 'type', 'required_photos', 'required_photos_before_start', 'is_active', 'sort_order',
        'ai_prompt', 'approval_prompt', 'task_time', 'reference_image',
        'frequency', 'submission_mode',
        'schedule_days', 'schedule_dates', 'start_date', 'end_date',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'schedule_days'  => 'array',
        'schedule_dates' => 'array',
        'start_date'     => 'date',
        'end_date'       => 'date',
    ];

    public function submissions(): HasMany
    {
        return $this->hasMany(ChecklistSubmission::class);
    }

    public function assignedUsers(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'checklist_task_users');
    }

    public function referenceFiles(): HasMany
    {
        return $this->hasMany(ChecklistTaskFile::class)->orderBy('sort_order');
    }

    /**
     * Check if this task should appear on a given date based on its frequency/schedule.
     */
    public function isScheduledFor(string $dateString): bool
    {
        $date = Carbon::parse($dateString);

        // Check start_date / end_date bounds
        if ($this->start_date && $date->lt($this->start_date)) return false;
        if ($this->end_date && $date->gt($this->end_date)) return false;

        switch ($this->frequency) {
            case 'daily':
                return true;

            case 'once':
                // "once" tasks are always scheduled (filtered elsewhere by completion)
                return true;

            case 'weekly':
                // schedule_days contains day-of-week numbers: 0=Sun, 1=Mon, ..., 6=Sat
                $days = $this->schedule_days ?? [];
                return in_array($date->dayOfWeek, $days);

            case 'monthly':
                // schedule_days contains day-of-month numbers: 1-31
                $days = $this->schedule_days ?? [];
                return in_array($date->day, $days);

            case 'custom':
                // schedule_dates contains specific date strings: ['2026-04-20', '2026-04-21']
                $dates = $this->schedule_dates ?? [];
                return in_array($date->toDateString(), $dates);

            default:
                return true;
        }
    }
}
