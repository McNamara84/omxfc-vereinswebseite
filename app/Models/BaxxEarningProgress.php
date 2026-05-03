<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaxxEarningProgress extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'action_key',
        'processed_count',
    ];

    protected function casts(): array
    {
        return [
            'processed_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}