<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResourceType extends Model
{
    /** @use HasFactory<\Database\Factories\ResourceTypeFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'application',
        'slug',
        'name',
        'description',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'application' => 'string',
        'slug' => 'string',
        'name' => 'string',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $resourceType): void {
            $resourceType->application = strtoupper($resourceType->application);
        });
    }

    public function scopeForApplication(Builder $query, string $application): Builder
    {
        return $query->where('application', strtoupper($application));
    }
}
