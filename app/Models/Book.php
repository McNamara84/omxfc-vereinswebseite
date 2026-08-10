<?php

namespace App\Models;

use App\Enums\BookType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property int $roman_number
 * @property string $title
 * @property string $author
 * @property BookType $type
 * @property int|null $maddraxikon_page_id
 * @property string|null $maddraxikon_page_title
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Review> $reviews
 */
class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'roman_number',
        'title',
        'author',
        'type',
        'maddraxikon_page_id',
        'maddraxikon_page_title',
        'maddraxikon_page_verified_at',
    ];

    protected $casts = [
        'type' => BookType::class,
        'maddraxikon_page_id' => 'integer',
        'maddraxikon_page_verified_at' => 'immutable_datetime',
    ];

    /**
     * Get all reviews for this book.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
