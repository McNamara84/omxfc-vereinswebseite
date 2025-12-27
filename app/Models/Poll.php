<?php

namespace App\Models;

use App\Enums\PollStatus;
use App\Enums\PollVisibility;
use Illuminate\Database\Eloquent\Builder;
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

    public function scopeOrderForAdminIndex(Builder $query): Builder
    {
        return $query
            ->orderByRaw(
                "CASE status WHEN ? THEN 0 WHEN ? THEN 1 ELSE 2 END",
                [PollStatus::Active->value, PollStatus::Draft->value]
            )
            ->orderByDesc('id');
    }

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

    public function buildChartData(): array
    {
        $this->loadMissing('options');

        $totalVotes = PollVote::query()->where('poll_id', $this->id)->count();

        $perOptionRows = PollVote::query()
            ->where('poll_id', $this->id)
            ->select('poll_option_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN voter_type = ? THEN 1 ELSE 0 END) as members', ['member'])
            ->selectRaw('SUM(CASE WHEN voter_type = ? THEN 1 ELSE 0 END) as guests', ['guest'])
            ->groupBy('poll_option_id')
            ->get();

        $perOption = [];
        foreach ($perOptionRows as $row) {
            $perOption[(int) $row->poll_option_id] = [
                'total' => (int) $row->total,
                'members' => (int) $row->members,
                'guests' => (int) $row->guests,
            ];
        }

        $optionLabels = [];
        $optionTotals = [];
        $optionMembers = [];
        $optionGuests = [];

        foreach ($this->options as $option) {
            $stats = $perOption[$option->id] ?? ['total' => 0, 'members' => 0, 'guests' => 0];

            $optionLabels[] = $option->label;
            $optionTotals[] = (int) ($stats['total'] ?? 0);
            $optionMembers[] = (int) ($stats['members'] ?? 0);
            $optionGuests[] = (int) ($stats['guests'] ?? 0);
        }

        $segment = PollVote::query()
            ->where('poll_id', $this->id)
            ->select('voter_type')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('voter_type')
            ->pluck('total', 'voter_type');

        $timeline = PollVote::query()
            ->where('poll_id', $this->id)
            ->selectRaw('DATE(created_at) as day')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'day' => (string) $row->day,
                'total' => (int) $row->total,
            ])
            ->all();

        return [
            'poll' => [
                'id' => $this->id,
                'question' => $this->question,
                'visibility' => $this->visibility->value,
                'status' => $this->status->value,
            ],
            'totals' => [
                'totalVotes' => $totalVotes,
                'members' => (int) ($segment['member'] ?? 0),
                'guests' => (int) ($segment['guest'] ?? 0),
            ],
            'options' => [
                'labels' => $optionLabels,
                'total' => $optionTotals,
                'members' => $optionMembers,
                'guests' => $optionGuests,
            ],
            'timeline' => $timeline,
        ];
    }
}
