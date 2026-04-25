<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReviewBaxxSpecialOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'points',
        'every_count',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'every_count' => 'integer',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('ends_at', '>', now());
    }
}