<?php

namespace App\Models;

use Database\Factories\RpgCharacterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        ];
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
