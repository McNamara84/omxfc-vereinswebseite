<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RpgItemType extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starting_equipment' => 'boolean', 'archived' => 'boolean', 'revision' => 'integer', 'current_version' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new \LogicException('Gegenstände werden archiviert.'));
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RpgItemVersion::class, 'item_type_id');
    }
}
