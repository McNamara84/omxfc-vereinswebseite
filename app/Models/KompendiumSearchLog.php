<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KompendiumSearchLog extends Model
{
    protected $fillable = [
        'user_id',
        'query',
        'normalized_query',
        'parsed_query',
        'selected_serien',
        'sort',
        'direction',
        'results_count',
        'source',
        'status',
        'is_admin_search',
        'candidates_truncated',
        'scanned_candidates',
    ];

    protected $casts = [
        'parsed_query' => 'array',
        'selected_serien' => 'array',
        'results_count' => 'integer',
        'is_admin_search' => 'boolean',
        'candidates_truncated' => 'boolean',
        'scanned_candidates' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
