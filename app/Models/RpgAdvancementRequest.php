<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpgAdvancementRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'operations' => 'array', 'changes' => 'array',
            'before_payload' => 'array', 'after_payload' => 'array',
            'revision' => 'integer', 'cost' => 'integer', 'decided_at' => 'datetime',
        ];
    }

    public function character(): BelongsTo
    {
        return $this->belongsTo(RpgCharacter::class, 'rpg_character_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'approved' => 'Genehmigt',
            'rejected' => 'Abgelehnt',
            'withdrawn' => 'Zurückgezogen',
            default => 'Zur Prüfung',
        };
    }
}
