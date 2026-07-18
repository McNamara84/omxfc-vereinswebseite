<?php

namespace App\Models;

use App\Enums\MaddraxikonAccountLinkStatus;
use App\Models\Concerns\HasUtcEpochAttributes;
use Database\Factories\MaddraxikonAccountLinkFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaddraxikonAccountLink extends Model
{
    /** @use HasFactory<MaddraxikonAccountLinkFactory> */
    use HasFactory, HasUtcEpochAttributes;

    protected $fillable = [
        'user_id',
        'wiki_key',
        'oauth_subject',
        'wiki_user_id',
        'wiki_username',
        'status',
        'verification_method',
        'first_verified_at',
        'first_verified_at_epoch',
        'verified_at',
        'verified_at_epoch',
        'disconnected_at',
        'disconnected_at_epoch',
        'consent_version',
        'consented_at',
        'consented_at_epoch',
    ];

    protected function casts(): array
    {
        return [
            'wiki_user_id' => 'integer',
            'status' => MaddraxikonAccountLinkStatus::class,
            'first_verified_at_epoch' => 'integer',
            'verified_at_epoch' => 'integer',
            'disconnected_at_epoch' => 'integer',
            'consented_at_epoch' => 'integer',
        ];
    }

    protected function firstVerifiedAt(): Attribute
    {
        return $this->utcEpochAttribute('first_verified_at');
    }

    protected function verifiedAt(): Attribute
    {
        return $this->utcEpochAttribute('verified_at');
    }

    protected function disconnectedAt(): Attribute
    {
        return $this->utcEpochAttribute('disconnected_at');
    }

    protected function consentedAt(): Attribute
    {
        return $this->utcEpochAttribute('consented_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(MaddraxikonContribution::class, 'account_link_id');
    }

    public function rewardEvents(): HasMany
    {
        return $this->hasMany(MaddraxikonRewardEvent::class, 'account_link_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where('status', MaddraxikonAccountLinkStatus::Active->value)
            ->whereNull('disconnected_at');
    }

    public function isActive(): bool
    {
        return $this->status === MaddraxikonAccountLinkStatus::Active
            && $this->disconnected_at === null;
    }
}
