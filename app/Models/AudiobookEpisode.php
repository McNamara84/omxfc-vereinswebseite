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
     * Scale factor mapping 0–100% progress to a 0–120° hue range.
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
    ];

    protected function casts(): array
    {
        return [
            'planned_release_date' => 'date',
        ];
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function progressHue(): float
    {
        return $this->progress * self::PROGRESS_HUE_FACTOR;
    }
}
