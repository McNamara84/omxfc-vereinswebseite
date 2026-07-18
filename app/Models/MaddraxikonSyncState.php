<?php

namespace App\Models;

use App\Models\Concerns\HasUtcEpochAttributes;
use Database\Factories\MaddraxikonSyncStateFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaddraxikonSyncState extends Model
{
    /** @use HasFactory<MaddraxikonSyncStateFactory> */
    use HasFactory, HasUtcEpochAttributes;

    protected $fillable = [
        'wiki_key',
        'watermark_at',
        'watermark_at_epoch',
        'initial_watermark_at',
        'initial_watermark_at_epoch',
        'last_started_at',
        'last_started_at_epoch',
        'run_sequence',
        'last_succeeded_at',
        'last_succeeded_at_epoch',
        'last_error_at',
        'last_error_at_epoch',
        'last_error',
        'consecutive_failures',
        'last_imported_count',
        'last_seen_rc_id',
        'recovery_required_at',
        'recovery_required_at_epoch',
        'recovery_from_at',
        'recovery_from_at_epoch',
        'recovery_until_at',
        'recovery_until_at_epoch',
        'last_recovery_succeeded_at',
        'last_recovery_succeeded_at_epoch',
        'last_recovered_from_at',
        'last_recovered_from_at_epoch',
        'last_recovered_until_at',
        'last_recovered_until_at_epoch',
        'last_recovered_count',
    ];

    protected function casts(): array
    {
        return [
            'watermark_at_epoch' => 'integer',
            'initial_watermark_at_epoch' => 'integer',
            'last_started_at_epoch' => 'integer',
            'run_sequence' => 'integer',
            'last_succeeded_at_epoch' => 'integer',
            'last_error_at_epoch' => 'integer',
            'consecutive_failures' => 'integer',
            'last_imported_count' => 'integer',
            'last_seen_rc_id' => 'integer',
            'recovery_required_at_epoch' => 'integer',
            'recovery_from_at_epoch' => 'integer',
            'recovery_until_at_epoch' => 'integer',
            'last_recovery_succeeded_at_epoch' => 'integer',
            'last_recovered_from_at_epoch' => 'integer',
            'last_recovered_until_at_epoch' => 'integer',
            'last_recovered_count' => 'integer',
        ];
    }

    protected function watermarkAt(): Attribute
    {
        return $this->utcEpochAttribute('watermark_at');
    }

    protected function initialWatermarkAt(): Attribute
    {
        return $this->utcEpochAttribute('initial_watermark_at');
    }

    protected function lastStartedAt(): Attribute
    {
        return $this->utcEpochAttribute('last_started_at');
    }

    protected function lastSucceededAt(): Attribute
    {
        return $this->utcEpochAttribute('last_succeeded_at');
    }

    protected function lastErrorAt(): Attribute
    {
        return $this->utcEpochAttribute('last_error_at');
    }

    protected function recoveryRequiredAt(): Attribute
    {
        return $this->utcEpochAttribute('recovery_required_at');
    }

    protected function recoveryFromAt(): Attribute
    {
        return $this->utcEpochAttribute('recovery_from_at');
    }

    protected function recoveryUntilAt(): Attribute
    {
        return $this->utcEpochAttribute('recovery_until_at');
    }

    protected function lastRecoverySucceededAt(): Attribute
    {
        return $this->utcEpochAttribute('last_recovery_succeeded_at');
    }

    protected function lastRecoveredFromAt(): Attribute
    {
        return $this->utcEpochAttribute('last_recovered_from_at');
    }

    protected function lastRecoveredUntilAt(): Attribute
    {
        return $this->utcEpochAttribute('last_recovered_until_at');
    }
}
