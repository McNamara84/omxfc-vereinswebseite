<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RpgItemVersion extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['definition' => 'array', 'input' => 'array', 'calculation' => 'array', 'created_at' => 'datetime', 'version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Gegenstandsversionen sind unveränderlich.'));
        static::deleting(fn () => throw new \LogicException('Gegenstandsversionen sind unveränderlich.'));
    }

    public function itemType(): BelongsTo
    {
        return $this->belongsTo(RpgItemType::class);
    }
}
