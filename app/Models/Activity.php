<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Activity extends Model
{
    public const ACTION_MADDRAXIKON_ACCOUNT_LINKED = 'maddraxikon_account_linked';

    public const ACTION_MADDRAXIKON_BAXX_AWARDED_PREFIX = 'maddraxikon_baxx_awarded_';

    protected $fillable = [
        'user_id',
        'subject_type',
        'subject_id',
        'action',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
