<?php

namespace App\Models;

use App\Models\Concerns\UsesUtcCombatDates;
use Illuminate\Database\Eloquent\Model;

class RpgCombatMilestone extends Model
{
    use UsesUtcCombatDates;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['winner_side' => 'integer'];
    }

    public function message(): string
    {
        return match ($this->kind) {
            'challenged' => $this->challenger_kind === 'npc'
                ? "{$this->challenger_character} fordert {$this->defender_character} von {$this->defender_name} zum Übungskampf heraus! NSC der AG-Leitung {$this->challenger_name}."
                : "{$this->challenger_name} hat mit {$this->challenger_character} den Charakter {$this->defender_character} von {$this->defender_name} zum Übungskampf herausgefordert.",
            'started' => "{$this->defender_name} hat die Herausforderung angenommen. {$this->challenger_character} und {$this->defender_character} beginnen ihren Übungskampf.",
            default => "Der Übungskampf zwischen {$this->challenger_character} und {$this->defender_character} ist beendet. ".($this->winner_side
                ? ($this->winner_side === 1 ? $this->challenger_character : $this->defender_character).' hat gewonnen.'
                : ($this->result === 'cancelled' ? 'Der Kampf wurde abgebrochen.' : 'Der Kampf endet unentschieden.')),
        };
    }
}
