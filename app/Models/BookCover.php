<?php

namespace App\Models;

use App\Enums\BookCoverStatus;
use Carbon\CarbonImmutable;
use Database\Factories\BookCoverFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $book_id
 * @property BookCoverStatus $status
 * @property string|null $source_file_title
 * @property string|null $source_url
 * @property string|null $source_sha1
 * @property string|null $small_path
 * @property string|null $large_path
 * @property CarbonImmutable|null $last_synced_at
 * @property-read Book $book
 */
class BookCover extends Model
{
    /** @use HasFactory<BookCoverFactory> */
    use HasFactory;

    protected $fillable = [
        'book_id',
        'status',
        'source_file_title',
        'source_url',
        'source_sha1',
        'source_description_url',
        'source_artist',
        'source_credit',
        'source_license',
        'source_license_url',
        'small_path',
        'large_path',
        'width',
        'height',
        'mime_type',
        'last_synced_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'status' => BookCoverStatus::class,
            'width' => 'integer',
            'height' => 'integer',
            'last_synced_at' => 'immutable_datetime',
        ];
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(CoverRating::class);
    }

    public function isReady(): bool
    {
        return $this->status === BookCoverStatus::Ready
            && filled($this->small_path)
            && filled($this->large_path);
    }
}
