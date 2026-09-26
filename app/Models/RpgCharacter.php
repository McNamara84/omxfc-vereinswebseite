<?php

namespace App\Models;

use Database\Factories\RpgCharacterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $character_name
 * @property array<string, mixed> $payload
 * @property string|null $portrait_path
 * @property string|null $portrait_mime
 * @property string|null $portrait_original_name
 * @property-read User $user
 */
class RpgCharacter extends Model
{
    /** @use HasFactory<RpgCharacterFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'character_name',
        'payload',
        'portrait_path',
        'portrait_mime',
        'portrait_original_name',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'initial_payload' => 'array',
            'revision' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $character): void {
            $character->initial_payload = $character->payload;
        });
        static::updating(function (self $character): void {
            if ($character->isDirty('initial_payload')) {
                throw new \LogicException('Der Ausgangsstand des Charakters ist unveränderlich.');
            }
        });
    }

    public function experienceEntries(): HasMany
    {
        return $this->hasMany(RpgExperienceEntry::class);
    }

    public function advancements(): HasMany
    {
        return $this->hasMany(RpgAdvancementRequest::class);
    }

    public function experienceBalance(): int
    {
        return (int) $this->experienceEntries()->sum('amount');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function displayName(): string
    {
        $name = trim($this->character_name);

        return $name !== '' ? $name : 'Charakter';
    }
}
