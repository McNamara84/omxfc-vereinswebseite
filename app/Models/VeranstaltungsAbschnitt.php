<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VeranstaltungsAbschnitt extends Model
{
    use HasFactory;

    protected $table = 'veranstaltungs_abschnitte';

    protected $fillable = [
        'veranstaltung_id',
        'schluessel',
        'titel',
        'markdown_inhalt',
        'sort_order',
        'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function veranstaltung(): BelongsTo
    {
        return $this->belongsTo(Veranstaltung::class);
    }

    public function scopeSichtbar($query)
    {
        return $query->where('is_visible', true)->orderBy('sort_order');
    }

    public function getHtmlInhaltAttribute(): string
    {
        return (string) Str::markdown((string) ($this->markdown_inhalt ?? ''), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}