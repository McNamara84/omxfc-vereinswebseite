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
        return [
            'id' => (string) $this->getScoutKey(),
            'path' => $this->path,
            'cycle' => $this->cycle,
            'roman_nr' => $this->roman_nr === null ? null : (string) $this->roman_nr,
            'title' => $this->title,
            'body' => $this->searchableBody(),
        ];
    }

    private function searchableBody(): string
    {
        $clean = preg_replace("/[^\\p{L}\\p{N}' ]+/u", ' ', $this->body) ?? '';
        $withoutStopWords = preg_replace(self::stopWordPattern(), ' ', $clean) ?? $clean;

        return preg_replace('/\\s+/u', ' ', trim($withoutStopWords)) ?? trim($withoutStopWords);
    }

    private static function stopWordPattern(): string
    {
        static $pattern;

        if ($pattern !== null) {
            return $pattern;
        }

        $quotedStopWords = array_map(
            static fn (string $word): string => preg_quote($word, '/'),
            self::STOP_WORDS
        );

        $pattern = "/(?<![\\p{L}\\p{N}'])(?:".implode('|', $quotedStopWords).")(?![\\p{L}\\p{N}'])/iu";

        return $pattern;
    }
}
