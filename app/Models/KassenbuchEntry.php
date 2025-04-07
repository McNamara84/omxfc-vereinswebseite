<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KassenbuchEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'created_by',
        'buchungsdatum',
        'betrag',
        'beschreibung',
        'typ'
    ];

    protected $casts = [
        'buchungsdatum' => 'date',
        'betrag' => 'decimal:2',
    ];

    /**
     * Get the team that owns the entry.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who created the entry.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
