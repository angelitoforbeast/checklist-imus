<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'respawn_delay_minutes', 'max_daily_count', 'parent_task_id', 'spawn_index',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'schedule_days'  => 'array',
        'schedule_dates' => 'array',
        'start_date'     => 'date',
        'end_date'       => 'date',
    ];

    protected static function booted(): void
    {
        // spawn_date is a MySQL GENERATED column (DATE(created_at)). It must never
        // be included in INSERT/UPDATE — the DB rejects the statement if it is.
        // replicate(), mass-assignment, or any other path that copies attributes
        // would otherwise re-emit it and fail with SQLSTATE[HY000] 3105.
        static::saving(function (ChecklistTask $model) {
            unset($model->spawn_date);
        });
    }

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
     * Parent template task (for spawned recurring tasks).
     */
    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(ChecklistTask::class, 'parent_task_id');
    }

    /**
     * Spawned child tasks (for template recurring tasks).
     */
    public function spawnedTasks(): HasMany
    {
        return $this->hasMany(ChecklistTask::class, 'parent_task_id');
    }

    /**
     * Check if this is a recurring-on-complete template (not a spawned child).
     */
    public function isRecurringTemplate(): bool
    {
        return $this->frequency === 'recurring_on_complete' && $this->parent_task_id === null;
    }

    /**
     * Check if this is a spawned child of a recurring template.
     */
    public function isSpawnedTask(): bool
    {
        return $this->parent_task_id !== null;
    }

    /**
     * Get spawned tasks for a specific date.
     */
    public function spawnedTasksForDate(string $date): \Illuminate\Database\Eloquent\Collection
    {
        return $this->spawnedTasks()
            ->whereDate('created_at', $date)
            ->orderBy('spawn_index')
            ->get();
    }

    /**
     * Count completed spawned tasks for a specific date.
     */
    public function completedSpawnCountForDate(string $date): int
    {
        $spawnIds = $this->spawnedTasks()
            ->whereDate('created_at', $date)
            ->pluck('id');

        if ($spawnIds->isEmpty()) return 0;

        return ChecklistSubmission::whereIn('checklist_task_id', $spawnIds)
            ->where('date', $date)
            ->where('status', 'completed')
            ->count();
    }

    /**
     * Check if this task should appear on a given date based on its frequency/schedule.
     */
    public function isScheduledFor(string $dateString): bool
    {
        $date = Carbon::parse($dateString);

        // Recurring templates should never appear in daily view
        if ($this->isRecurringTemplate()) return false;

        // Spawned tasks: only show on the date they were created
        if ($this->isSpawnedTask()) {
            return $this->created_at && $this->created_at->toDateString() === $dateString;
        }

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
