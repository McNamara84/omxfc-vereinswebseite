<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

/**
 * @property string $path
 * @property string|null $cycle
 * @property int|null $roman_nr
 * @property string $title
 * @property string $body
 */
class RomanExcerpt extends Model
{
    use Searchable;

    public $timestamps = false;

    protected $guarded = [];

    /* eigener Primärschlüssel = path */
    protected $primaryKey = 'path';

    public $incrementing = false;

    protected $keyType = 'string';

    /** deutschsprachige Stop-Words (+ deine Beispiele) */
    private const STOP_WORDS = [
        'und', 'oder', 'aber', 'weil', 'dass', 'der', 'die', 'das', 'ein', 'eine', 'einer', 'eines',
        'auf', 'in', 'im', 'ist', 'sind', 'war', 'waren', 'mit', 'von', 'für',
    ];

    /*  Scout-Schlüssel */
    public function getScoutKey(): mixed
    {
        return $this->path;
    }

    public function getScoutKeyName(): string
    {
        return 'path';
    }

    /*  Daten für Index – enthält jetzt KEY „path“ */
    public function toSearchableArray(): array
    {
        $clean = preg_replace("/[^\\p{L}\\p{N}' ]+/u", ' ', $this->body);

        $tokens = array_filter(
            explode(' ', $clean),
            fn ($w) => $w !== '' && ! in_array(mb_strtolower($w), self::STOP_WORDS, true)
        );

        return [
            'path' => $this->path,        // Primärschlüssel
            'cycle' => $this->cycle,
            'roman_nr' => $this->roman_nr,
            'title' => $this->title,
            'body' => implode(' ', $tokens),
        ];
    }
}
