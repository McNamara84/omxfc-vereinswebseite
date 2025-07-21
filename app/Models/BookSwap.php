<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

/**
 * @property int $id
 * @property int $offer_id
 * @property int $request_id
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BookOffer $offer
 * @property-read BookRequest $request
 */
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
