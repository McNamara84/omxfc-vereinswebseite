<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $review_id
 * @property int $user_id
 * @property int|null $parent_id
 * @property string $content
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Review $review
 * @property-read User $user
 * @property-read ReviewComment|null $parent
 * @property-read Collection<int, ReviewComment> $children
 */
class ReviewComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'review_id',
        'user_id',
        'parent_id',
        'content',
    ];

    /**
     * The review this comment belongs to.
     */
    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    /**
     * The user who wrote the comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Parent comment if this is a reply.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ReviewComment::class, 'parent_id');
    }

    /**
     * Replies to this comment.
     */
    public function children(): HasMany
    {
        return $this->hasMany(ReviewComment::class, 'parent_id');
    }
}
