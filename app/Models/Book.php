<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
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
