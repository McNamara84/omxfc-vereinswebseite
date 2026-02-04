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
    ];

    protected $casts = [
        'type' => BookType::class,
    ];

    /**
     * Get all reviews for this book.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
