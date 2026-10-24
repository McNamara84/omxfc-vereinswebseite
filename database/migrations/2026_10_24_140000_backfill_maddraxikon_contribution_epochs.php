<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Run before streak activation. Match occurredAtUtc()'s legacy fallback,
        // including the application's timezone, without changing known epochs.
        $timezone = (string) config('app.timezone', 'UTC');
        DB::table('maddraxikon_contributions')->whereNull('occurred_at_epoch')
            ->select(['id', 'occurred_at'])->chunkById(500, function (Collection $rows) use ($timezone): void {
                DB::transaction(function () use ($rows, $timezone): void {
                    foreach ($rows as $row) {
                        DB::table('maddraxikon_contributions')->where('id', $row->id)->whereNull('occurred_at_epoch')
                            ->update(['occurred_at_epoch' => CarbonImmutable::parse($row->occurred_at, $timezone)->timestamp]);
                    }
                }, 3);
            });
    }

    public function down(): void
    {
        // Preserve resolved instants; a rollback must not erase historical evidence.
    }
};
