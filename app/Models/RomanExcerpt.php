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

    public static function scoutDocumentId(string $path): string
    {
        return sha1(str_replace('\\', '/', $path));
    }

    /*  Scout-Schlüssel */
    public function getScoutKey(): mixed
    {
        return self::scoutDocumentId($this->path);
    }

    public function getScoutKeyName(): string
    {
        return 'id';
    }

    /*  Daten für Index – enthält Typesense-ID und den Originalpfad */
    public function toSearchableArray(): array
    {
        $clean = preg_replace("/[^\\p{L}\\p{N}' ]+/u", ' ', $this->body);

        $tokens = array_filter(
            explode(' ', $clean),
            fn ($w) => $w !== '' && ! in_array(mb_strtolower($w), self::STOP_WORDS, true)
        );

        return [
            'id' => (string) $this->getScoutKey(),
            'path' => $this->path,
            'cycle' => $this->cycle,
            'roman_nr' => $this->roman_nr === null ? null : (string) $this->roman_nr,
            'title' => $this->title,
            'body' => implode(' ', $tokens),
        ];
    }
}
