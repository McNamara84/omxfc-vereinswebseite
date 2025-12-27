<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $poll_id
 * @property int $poll_option_id
 * @property int|null $user_id
 * @property string|null $ip_hash
 * @property string $voter_type
 */
class PollVote extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (PollVote $vote) {
            if ($vote->user_id === null && $vote->ip_hash === null) {
                throw new \InvalidArgumentException('PollVote requires either user_id or ip_hash.');
            }
        });
    }

    protected $fillable = [
        'poll_id',
        'poll_option_id',
        'user_id',
        'ip_hash',
        'voter_type',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(PollOption::class, 'poll_option_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
