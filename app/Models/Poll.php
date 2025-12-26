<?php

namespace App\Models;

use App\Enums\PollStatus;
use App\Enums\PollVisibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $question
 * @property string $menu_label
 * @property PollVisibility $visibility
 * @property PollStatus $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $activated_at
 * @property Carbon|null $archived_at
 * @property int $created_by_user_id
 */
class Poll extends Model
{
    use HasFactory;

    protected $fillable = [
        'question',
        'menu_label',
        'visibility',
        'status',
        'starts_at',
        'ends_at',
        'activated_at',
        'archived_at',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => PollVisibility::class,
            'status' => PollStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'activated_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('sort_order');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }

    public function isWithinVotingWindow(?Carbon $now = null): bool
    {
        $now ??= now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->ends_at && $now->gt($this->ends_at)) {
            return false;
        }

        return true;
    }
}
