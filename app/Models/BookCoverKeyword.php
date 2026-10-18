<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookCoverKeyword extends Model
{
    protected $fillable = ['keyword', 'normalized_keyword'];

    public function cover(): BelongsTo
    {
        return $this->belongsTo(BookCover::class, 'book_cover_id');
    }
}
