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
 * @property int $fanfiction_id
 * @property int $user_id
 * @property int|null $parent_id
 * @property string $content
 * @property Carbon|null $deleted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Fanfiction $fanfiction
 * @property-read User $user
 * @property-read FanfictionComment|null $parent
 * @property-read Collection<int, FanfictionComment> $children
 */
class FanfictionComment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'fanfiction_id',
        'user_id',
        'parent_id',
        'content',
    ];

    /**
     * The fanfiction this comment belongs to.
     */
    public function fanfiction(): BelongsTo
    {
        return $this->belongsTo(Fanfiction::class);
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
        return $this->belongsTo(FanfictionComment::class, 'parent_id');
    }

    /**
     * Replies to this comment.
     */
    public function children(): HasMany
    {
        return $this->hasMany(FanfictionComment::class, 'parent_id');
    }

    /**
     * Alias for children() - replies to this comment.
     */
    public function replies(): HasMany
    {
        return $this->children();
    }
}
