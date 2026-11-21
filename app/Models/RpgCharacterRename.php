<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpgCharacterRename extends Model
{
    public const UPDATED_AT = null;

    #[\Override]
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['revision_before' => 'integer', 'revision_after' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Dokumentierte Namensänderungen sind unveränderlich.');
        });
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(RpgCharacter::class, 'rpg_character_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(RewardPurchase::class, 'reward_purchase_id');
    }
}
