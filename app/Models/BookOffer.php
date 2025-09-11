<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $series
 * @property int $book_number
 * @property string $book_title
 * @property string $condition
 * @property array|null $photos
 * @property bool $completed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read BookSwap|null $swap
 */
class BookOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'series',
        'book_number',
        'book_title',
        'condition',
        'photos',
        'completed',
    ];

    protected $casts = [
        'photos' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function swap()
    {
        return $this->hasOne(BookSwap::class, 'offer_id');
    }
}
