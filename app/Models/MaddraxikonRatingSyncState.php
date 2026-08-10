<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaddraxikonRatingSyncState extends Model
{
    use HasFactory;

    protected $fillable = [
        'wiki_key',
        'last_started_at',
        'last_succeeded_at',
        'last_error_at',
        'last_error_category',
        'consecutive_failures',
        'last_candidate_count',
        'last_updated_count',
        'last_removed_count',
        'last_skipped_count',
    ];

    protected function casts(): array
    {
        return [
            'last_started_at' => 'immutable_datetime',
            'last_succeeded_at' => 'immutable_datetime',
            'last_error_at' => 'immutable_datetime',
            'consecutive_failures' => 'integer',
            'last_candidate_count' => 'integer',
            'last_updated_count' => 'integer',
            'last_removed_count' => 'integer',
            'last_skipped_count' => 'integer',
        ];
    }
}
