<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AudiobookEpisode extends Model
{
    use HasFactory;

    public const STATUSES = [
        'Skripterstellung',
        'Korrekturlesung',
        'Rollenbesetzung',
        'Aufnahmensammlung',
        'Musikerstellung',
        'Audiobearbeitung',
        'Videobearbeitung',
        'Grafiken',
        'Veröffentlichungsplanung',
        'Veröffentlichung',
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

    /**
     * Determine whether all roles for the episode are filled.
     */
    public function getAllRolesFilledAttribute(): bool
    {
        return $this->roles_total > 0 && $this->roles_filled === $this->roles_total;
    }

    /**
     * Determine if the episode is a special edition.
     */
    public function isSpecialEdition(): bool
    {
        return str_starts_with($this->episode_number, 'SE');
    }

    /**
     * Accessor for the episode type ("se" or "regular").
     */
    public function getEpisodeTypeAttribute(): string
    {
        return $this->isSpecialEdition() ? 'se' : 'regular';
    }

    /**
     * Parse the planned release date into a Carbon instance.
     */
    public function getPlannedReleaseDateParsedAttribute(): ?Carbon
    {
        if (!$this->planned_release_date) {
            return null;
        }

        $formats = ['d.m.Y', 'm.Y', 'Y'];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $this->planned_release_date);
            } catch (InvalidFormatException $e) {
                continue;
            }
            if ($format === 'm.Y') {
                $date->day = 1;
            } elseif ($format === 'Y') {
                $date->month = 1;
                $date->day = 1;
            }

            return $date;
        }

        return null;
    }

    /**
     * Year extracted from the planned release date.
     */
    public function getReleaseYearAttribute(): ?int
    {
        return $this->planned_release_date_parsed?->year;
    }
}
