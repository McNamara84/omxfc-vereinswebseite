<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RpgCheck extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['difficulty' => 'integer', 'winner_position' => 'integer', 'comparison_margin' => 'integer',
            'completed_at' => 'datetime', 'cancelled_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(RpgCheckBatch::class, 'rpg_check_batch_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(RpgCheckParticipant::class)->orderBy('position');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['pending', 'awaiting_decision'], true);
    }
}
