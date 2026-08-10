<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaddraxikonReviewRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'review_id',
        'book_id',
        'user_id',
        'account_link_id',
        'maddraxikon_page_id',
        'wiki_user_id',
        'rating',
        'source_voted_at',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'maddraxikon_page_id' => 'integer',
            'wiki_user_id' => 'integer',
            'rating' => 'integer',
            'source_voted_at' => 'immutable_datetime',
            'synced_at' => 'immutable_datetime',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function accountLink(): BelongsTo
    {
        return $this->belongsTo(MaddraxikonAccountLink::class, 'account_link_id');
    }
}
