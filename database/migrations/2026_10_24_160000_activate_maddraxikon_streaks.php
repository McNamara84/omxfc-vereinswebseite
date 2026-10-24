<?php

use App\Models\MaddraxikonStreakRun;
use App\Models\MaddraxikonSyncState;
use App\Services\Maddraxikon\MaddraxikonStreakSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! config('maddraxikon.streaks.enabled')) {
            return;
        }

        DB::transaction(function (): void {
            $now = CarbonImmutable::now('UTC')->startOfSecond();
            $start = app(MaddraxikonStreakSettings::class)->start() ?? $now;
            MaddraxikonStreakRun::query()->firstOrCreate(
                ['wiki_key' => config('maddraxikon.wiki_key')],
                ['starts_at_epoch' => $start->timestamp],
            );

            // Cover deployment onward even if the first scheduled import runs
            // later. Existing import progress and recovery boundaries stay put.
            $sync = MaddraxikonSyncState::query()->firstOrCreate(
                ['wiki_key' => config('maddraxikon.wiki_key')],
            );
            $sync = MaddraxikonSyncState::query()->lockForUpdate()->findOrFail($sync->id);
            if ($sync->watermark_at === null) {
                $sync->update(['watermark_at' => $now, 'initial_watermark_at' => $now]);
            }
        }, 3);
    }

    public function down(): void
    {
        // Preserve the rollout boundary and audit history across rollbacks.
    }
};
