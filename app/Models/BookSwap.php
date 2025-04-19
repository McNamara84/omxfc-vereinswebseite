<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookSwap extends Model
{
    use HasFactory;

    protected $fillable = [
        'offer_id',
        'request_id',
        'completed_at',
    ];

    protected $dates = ['completed_at'];

    public function offer()
    {
        return $this->belongsTo(BookOffer::class, 'offer_id');
    }

    public function request()
    {
        return $this->belongsTo(BookRequest::class, 'request_id');
    }
}
