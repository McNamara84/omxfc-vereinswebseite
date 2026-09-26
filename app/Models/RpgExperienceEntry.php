<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class RpgExperienceEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            $hasAward = $entry->rpg_experience_award_id !== null;
            $hasRequest = $entry->rpg_advancement_request_id !== null;
            if ($hasAward === $hasRequest) {
                throw new LogicException('Eine EP-Buchung benötigt genau einen Ursprungsbezug.');
            }
            $source = $hasAward ? $entry->award()->firstOrFail() : $entry->advancement()->firstOrFail();
            $expected = $hasAward ? $source->points : -$source->cost;
            if ((int) $source->rpg_character_id !== (int) $entry->rpg_character_id
                || $entry->amount !== $expected
                || ($hasAward ? $entry->amount < 0 : $entry->amount >= 0)) {
                throw new LogicException('Charakter und Betrag müssen dem Ursprung der EP-Buchung entsprechen.');
            }
        });
        static::updating(fn () => throw new LogicException('Gebuchte Erfahrungspunkte sind unveränderlich.'));
    }

    public function award(): BelongsTo
    {
        return $this->belongsTo(RpgExperienceAward::class, 'rpg_experience_award_id');
    }

    public function advancement(): BelongsTo
    {
        return $this->belongsTo(RpgAdvancementRequest::class, 'rpg_advancement_request_id');
    }
}
