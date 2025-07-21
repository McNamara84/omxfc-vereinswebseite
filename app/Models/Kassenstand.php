<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property float $betrag
 * @property Carbon $letzte_aktualisierung
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
class Kassenstand extends Model
{
    use HasFactory;

    protected $table = 'kassenstand';

    protected $fillable = [
        'team_id',
        'betrag',
        'letzte_aktualisierung',
    ];

    protected $casts = [
        'betrag' => 'decimal:2',
        'letzte_aktualisierung' => 'date',
    ];

    /**
     * Get the team that owns the kassenstand.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
