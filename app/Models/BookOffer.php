<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int $id
 * @property int $user_id
 * @property string $series
 * @property int $book_number
 * @property string $book_title
 * @property string $condition
 * @property array|null $photos
 * @property bool $completed
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 * @property-read BookSwap|null $swap
 */
class BookOffer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'series',
        'book_number',
        'book_title',
        'condition',
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
        if (is_array($value)) {
            $photos = $value;
        } else {
            $decoded = json_decode($value ?? '[]', true);
            $photos = is_array($decoded) ? $decoded : [];
        }

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

            $trimmed = trim($photo);

            if ($trimmed === '') {
                continue;
            }

            $path = ltrim($trimmed, '/');

            if ($path === '') {
                continue;
            }

            $normalized[] = $path;
        }

        return $normalized;
    }
}
