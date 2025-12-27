<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $poll_id
 * @property string $label
 * @property string|null $image_url
 * @property string|null $link_url
 * @property int $sort_order
 */
class PollOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'poll_id',
        'label',
        'image_url',
        'link_url',
        'sort_order',
    ];

    public function poll(): BelongsTo
    {
        return $this->belongsTo(Poll::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PollVote::class);
    }
}
