<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $bundle_id
 * @property int $user_id
 * @property string $series
 * @property int $book_number
 * @property string $book_title
 * @property string $condition
 * @property string|null $condition_max
 * @property array|null $photos
 * @property bool $completed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read BookSwap|null $swap
 * @property-read string $condition_range
 */
class BookOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bundle_id',
        'series',
        'book_number',
        'book_title',
        'condition',
        'condition_max',
        'photos',
        'completed',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function swap()
    {
        return $this->hasOne(BookSwap::class, 'offer_id');
    }

    public function getPhotosAttribute($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        $photos = is_array($decoded) ? $decoded : [];

        return $this->sanitizePhotoPaths($photos);
    }

    public function setPhotosAttribute($value): void
    {
        if ($value === null) {
            $this->attributes['photos'] = null;
            return;
        }

        if (!is_array($value)) {
            $decoded = json_decode($value ?? '[]', true);
            $value = is_array($decoded) ? $decoded : [];
        }

        $this->attributes['photos'] = json_encode($this->sanitizePhotoPaths($value));
    }

    /**
     * @param array<int, mixed> $photos
     * @return array<int, string>
     */
    private function sanitizePhotoPaths(array $photos): array
    {
        $normalized = [];

        foreach ($photos as $photo) {
            if (!is_string($photo)) {
                continue;
            }

            $normalizedPath = ltrim(trim($photo), '/');

            if ($normalizedPath === '') {
                continue;
            }

            $normalized[] = $normalizedPath;
        }

        return $normalized;
    }

    /**
     * Prüft, ob dieses Angebot Teil eines Stapels ist.
     */
    public function isPartOfBundle(): bool
    {
        return $this->bundle_id !== null;
    }

    /**
     * Gibt alle anderen Angebote im selben Stapel zurück.
     *
     * HINWEIS: Diese Methode lädt keine Relationships automatisch.
     * Falls 'user', 'swap' oder andere Relations benötigt werden:
     *   $offer->bundleSiblings()->load(['user', 'swap'])
     * oder im Query-Builder:
     *   static::where('bundle_id', ...)->with(['user', 'swap'])->get()
     *
     * @return \Illuminate\Support\Collection<int, BookOffer>
     */
    public function bundleSiblings(): \Illuminate\Support\Collection
    {
        if (!$this->bundle_id) {
            return collect();
        }

        return static::where('bundle_id', $this->bundle_id)
            ->where('id', '!=', $this->id)
            ->get();
    }

    /**
     * Gibt alle Angebote im Stapel zurück (inkl. dieses).
     *
     * HINWEIS: Diese Methode lädt keine Relationships automatisch.
     * Falls 'user', 'swap' oder andere Relations benötigt werden:
     *   $offer->bundleOffers()->load(['user', 'swap'])
     * oder im Query-Builder:
     *   static::where('bundle_id', ...)->with(['user', 'swap'])->get()
     *
     * @return \Illuminate\Support\Collection<int, BookOffer>
     */
    public function bundleOffers(): \Illuminate\Support\Collection
    {
        if (!$this->bundle_id) {
            return collect([$this]);
        }

        return static::where('bundle_id', $this->bundle_id)->get();
    }

    /**
     * Formatierter Zustandsbereich.
     *
     * Gibt "Z1 bis Z2" zurück wenn condition_max gesetzt ist und sich unterscheidet,
     * sonst nur den einzelnen Zustand.
     *
     * HINWEIS: Diese Methode validiert nicht ob condition_max logisch korrekt ist
     * (d.h. schlechter als condition). Bei ungültigen Daten in der DB könnte
     * "Z2 bis Z1" ausgegeben werden, was semantisch falsch ist. Die Validierung
     * erfolgt beim Speichern über validateConditionRange() im Controller.
     */
    public function getConditionRangeAttribute(): string
    {
        if ($this->condition_max && $this->condition !== $this->condition_max) {
            return $this->condition . ' bis ' . $this->condition_max;
        }

        return $this->condition;
    }
}
