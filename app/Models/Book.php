<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use App\Models\Review;
use Carbon\Carbon;

/**
 * @property int $id
 * @property int $roman_number
 * @property string $title
 * @property string $author
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
    ];

    /**
     * Get all reviews for this book.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
}
