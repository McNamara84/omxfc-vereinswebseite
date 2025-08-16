<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudiobookEpisode extends Model
{
    use HasFactory;

    public const STATUSES = [
        'Skript wird erstellt',
        'In Korrekturlesung',
        'Aufnahmen in Arbeit',
        'Audiobearbeitung gestartet',
        'Videobearbeitung gestartet',
        'Cover und Thumbnail in Arbeit',
        'Veröffentlichung geplant',
        'Veröffentlicht',
    ];

    /**
     * Scale factor mapping 0–100% progress to a 0–120° hue range,
     * which covers red (0°) to green (120°) on the HSL color wheel.
     */
    private const PROGRESS_HUE_FACTOR = 1.2;

    protected $fillable = [
        'episode_number',
        'title',
        'author',
        'planned_release_date',
        'status',
        'responsible_user_id',
        'progress',
        'notes',
        'roles_total',
        'roles_filled',
    ];

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /**
     * Hue value from 0° (red) to 120° (green) representing completion level.
     */
    public function progressHue(): float
    {
        return $this->progress * self::PROGRESS_HUE_FACTOR;
    }

    public function rolesFilledPercent(): int
    {
        if ($this->roles_total === 0) {
            return 0;
        }

        return (int) round(($this->roles_filled / $this->roles_total) * 100);
    }

    public function rolesHue(): float
    {
        return $this->rolesFilledPercent() * self::PROGRESS_HUE_FACTOR;
    }
}
