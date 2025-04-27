<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'series',
        'book_number',
        'book_title',
        'condition',
        'completed',
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
