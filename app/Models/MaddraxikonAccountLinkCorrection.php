<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class MaddraxikonAccountLinkCorrection extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'actor_user_id' => 'integer',
            'affected_user_id' => 'integer',
            'released_account_link_id' => 'integer',
            'old_wiki_user_id' => 'integer',
            'corrected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        self::updating(static function (): never {
            throw new LogicException(
                'Maddraxikon-Zuordnungskorrekturen sind unveränderlich.'
            );
        });

        self::deleting(static function (): never {
            throw new LogicException(
                'Maddraxikon-Zuordnungskorrekturen dürfen nicht gelöscht werden.'
            );
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function affectedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'affected_user_id');
    }
}
