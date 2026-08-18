<?php

namespace App\Models;

use Database\Factories\CoverRatingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $user_id
 * @property int $book_cover_id
 * @property int $rating
 * @property-read User $user
 * @property-read BookCover $bookCover
 */
class CoverRating extends Model
{
    /** @use HasFactory<CoverRatingFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'book_cover_id',
        'rating',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookCover(): BelongsTo
    {
        return $this->belongsTo(BookCover::class);
    }
}
