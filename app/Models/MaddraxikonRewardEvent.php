<?php

namespace App\Models;

use App\Enums\MaddraxikonRewardEventStatus;
use Database\Factories\MaddraxikonRewardEventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaddraxikonRewardEvent extends Model
{
    public const ACTION_EDIT_SESSION = 'maddraxikon_edit_session';

    public const ACTION_NEW_ARTICLE = 'maddraxikon_new_article';

    /** @use HasFactory<MaddraxikonRewardEventFactory> */
    use HasFactory;

    protected $fillable = [
        'wiki_key',
        'user_id',
        'account_link_id',
        'source_contribution_id',
        'action_key',
        'source_key',
        'source_revision_id',
        'session_anchor_revision_id',
        'activity_date',
        'sequence_number',
        'baxx_earning_rule_id',
        'rule_points',
        'rule_every_count',
        'rule_updated_at',
        'candidate_points',
        'awarded_points',
        'capped_points',
        'status',
        'status_reason',
        'user_point_id',
        'reversal_user_point_id',
        'awarded_at',
        'reversed_at',
        'reversed_by',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'source_revision_id' => 'integer',
            'session_anchor_revision_id' => 'integer',
            'activity_date' => 'date',
            'sequence_number' => 'integer',
            'rule_points' => 'integer',
            'rule_every_count' => 'integer',
            'rule_updated_at' => 'datetime',
            'candidate_points' => 'integer',
            'awarded_points' => 'integer',
            'capped_points' => 'integer',
            'status' => MaddraxikonRewardEventStatus::class,
            'awarded_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accountLink(): BelongsTo
    {
        return $this->belongsTo(MaddraxikonAccountLink::class, 'account_link_id');
    }

    public function sourceContribution(): BelongsTo
    {
        return $this->belongsTo(MaddraxikonContribution::class, 'source_contribution_id');
    }

    public function earningRule(): BelongsTo
    {
        return $this->belongsTo(BaxxEarningRule::class, 'baxx_earning_rule_id');
    }

    public function userPoint(): BelongsTo
    {
        return $this->belongsTo(UserPoint::class, 'user_point_id');
    }

    public function reversalUserPoint(): BelongsTo
    {
        return $this->belongsTo(UserPoint::class, 'reversal_user_point_id');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }
}
