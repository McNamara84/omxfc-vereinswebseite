<?php

namespace App\Models;

use App\Enums\TourAssignmentSource;
use App\Enums\TourAssignmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tour_key',
        'tour_version',
        'status',
        'assigned_via',
        'assigned_by_user_id',
        'assigned_at',
        'started_at',
        'completed_at',
        'dismissed_at',
        'next_prompt_at',
        'current_step_key',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'tour_version' => 'integer',
            'status' => TourAssignmentStatus::class,
            'assigned_via' => TourAssignmentSource::class,
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'next_prompt_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function isPromptDue(): bool
    {
        if ($this->status === TourAssignmentStatus::Completed) {
            return false;
        }

        return $this->next_prompt_at === null || $this->next_prompt_at->isPast();
    }
}