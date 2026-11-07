<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpgInventoryEntry extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['before_state' => 'array', 'after_state' => 'array', 'changes' => 'array',
            'revision_before' => 'integer', 'revision_after' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Inventareinträge sind unveränderlich.'));
        static::deleting(fn () => throw new \LogicException('Inventareinträge sind unveränderlich.'));
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(RpgInventoryTransaction::class, 'transaction_id');
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(RpgCharacter::class, 'rpg_character_id');
    }
}
