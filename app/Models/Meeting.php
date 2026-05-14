<?php

namespace App\Models;

use App\Enums\MeetingRhythmType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Meeting extends Model
{
    use HasFactory;

    public function resolvedZoomUrl(): ?string
    {
        return $this->zoom_url ?: config('services.meetings.zoom_links.'.$this->slug);
    }

    public function hasResolvedZoomUrl(): bool
    {
        return filled($this->resolvedZoomUrl());
    }

    public function usesZoomFallback(): bool
    {
        return blank($this->zoom_url) && filled(config('services.meetings.zoom_links.'.$this->slug));
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'slug',
        'zoom_url',
        'is_active',
        'sort_order',
        'time_from',
        'time_to',
        'rhythm_type',
        'interval_weeks',
        'starts_on',
        'weekday',
        'week_of_month',
        'day_of_month',
        'rhythm_note',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'rhythm_type' => MeetingRhythmType::class,
        'interval_weeks' => 'integer',
        'starts_on' => 'date',
        'weekday' => 'integer',
        'week_of_month' => 'integer',
        'day_of_month' => 'integer',
        'updated_by' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('title');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
