<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class RpgNpc extends Model
{
    protected $guarded = [];

    protected $attributes = ['revision' => 0];

    protected $hidden = ['submission_hash'];

    protected function casts(): array
    {
        return ['configuration' => 'array', 'profile' => 'array', 'revision' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $npc): void {
            if ($npc->isDirty(['profile', 'profile_hash', 'configuration', 'template_key', 'template_version', 'rulebook_sha256', 'special_template_key', 'team_id', 'source_page'])) {
                throw new \LogicException('NSC-Vorlagenstände sind unveränderlich.');
            }
        });
        static::deleting(function (self $npc): void {
            if (RpgCombat::whereIn('status', RpgCombat::OPEN_STATUSES)->whereHas('participants', fn ($q) => $q->where('rpg_npc_id', $npc->id))->exists()) {
                throw ValidationException::withMessages(['npc' => 'NSCs mit offenen Kämpfen können nicht gelöscht werden.']);
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function displayName(): string
    {
        return $this->custom_name ?: $this->profile['name'];
    }
}
