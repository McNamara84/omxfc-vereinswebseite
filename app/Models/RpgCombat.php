<?php

namespace App\Models;

use App\Models\Concerns\UsesUtcCombatDates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RpgCombat extends Model
{
    use UsesUtcCombatDates;

    protected $guarded = [];

    protected $hidden = ['state', 'submission_hash'];

    protected function casts(): array
    {
        return ['state' => 'array', 'revision' => 'integer', 'expires_at' => 'immutable_datetime',
            'accepted_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(RpgCombatParticipant::class)->orderBy('side');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(RpgCombatDecision::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(RpgCombatEvent::class);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['challenged', 'preparing', 'active', 'awaiting_ruling'], true);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'challenged' => 'Herausforderung offen', 'preparing' => 'Vorbereitung', 'active' => 'Kampf läuft', 'awaiting_ruling' => 'Regelfrage offen',
            'completed' => 'Beendet', 'expired' => 'Einladung abgelaufen', 'declined' => 'Abgelehnt', 'withdrawn' => 'Zurückgezogen',
            'membership_changed' => 'Beteiligung nicht mehr möglich', default => $status,
        };
    }

    public function resultLabel(): string
    {
        return match ($this->result) {
            'incapacity' => 'Bewusstlosigkeit', 'mutual_incapacity' => 'Beide Charaktere bewusstlos',
            'surrender' => 'Aufgabe', 'cancelled' => 'Einvernehmlich abgebrochen', 'round_limit' => 'Rundenlimit erreicht',
            'judicial_abort' => 'Durch die AG-Leitung neutral abgebrochen',
            default => self::statusLabel($this->result ?? $this->status),
        };
    }
}
