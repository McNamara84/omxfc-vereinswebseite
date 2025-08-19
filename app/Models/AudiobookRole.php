<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudiobookRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'episode_id',
        'name',
        'description',
        'takes',
        'user_id',
        'speaker_name',
    ];

    public function episode(): BelongsTo
    {
        return $this->belongsTo(AudiobookEpisode::class, 'episode_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
