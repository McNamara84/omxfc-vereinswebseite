<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberClientSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'user_agent_hash',
        'user_agent',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public static function hashUserAgent(?string $userAgent): string
    {
        return hash('sha256', (string) $userAgent);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
