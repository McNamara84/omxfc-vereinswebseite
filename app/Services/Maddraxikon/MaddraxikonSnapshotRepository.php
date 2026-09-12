<?php

namespace App\Services\Maddraxikon;

use App\Enums\BookType;
use App\Exceptions\MaddraxikonCrawlException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use JsonException;
use LogicException;

class MaddraxikonSnapshotRepository
{
    private const POINTERS_TABLE = 'maddraxikon_book_snapshot_pointers';

    private const SNAPSHOTS_TABLE = 'maddraxikon_book_snapshots';

    public function activeId(BookType $type): ?string
    {
        if (! $this->tablesExist()) {
            return null;
        }

        $id = DB::table(self::POINTERS_TABLE)
            ->where('series_key', $type->key())
            ->value('snapshot_id');

        return is_string($id) && $id !== '' ? $id : null;
    }

    /** @return array{id: string, rows: array<int, array<string, mixed>>}|null */
    public function activeDataset(BookType $type): ?array
    {
        $id = $this->activeId($type);

        if ($id === null) {
            return null;
        }

        return [
            'id' => $id,
            'rows' => $this->dataset($id, $type),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function dataset(string $snapshotId, BookType $type): array
    {
        $payload = DB::table(self::SNAPSHOTS_TABLE)
            ->where('snapshot_id', $snapshotId)
            ->where('series_key', $type->key())
            ->value('payload');

        if (! is_string($payload)) {
            throw new MaddraxikonCrawlException(
                "Der Datenbank-Snapshot {$snapshotId} für {$type->label()} fehlt."
            );
        }

        return $this->decode($payload, $type);
    }

    /** @param array<string, string> $jsonBySeries */
    public function storeAndActivate(string $snapshotId, array $jsonBySeries): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Maddraxikon-Snapshots dürfen nur innerhalb einer Datenbanktransaktion aktiviert werden.');
        }

        $now = now();

        foreach ($jsonBySeries as $seriesKey => $payload) {
            $type = BookType::fromKey($seriesKey);

            if ($type === null) {
                throw new MaddraxikonCrawlException("Unbekannte Buchreihe: {$seriesKey}");
            }

            $this->decode($payload, $type);
            $previousSnapshotId = DB::table(self::POINTERS_TABLE)
                ->where('series_key', $seriesKey)
                ->value('snapshot_id');

            if (is_string($previousSnapshotId) && $previousSnapshotId !== $snapshotId) {
                DB::table(self::SNAPSHOTS_TABLE)
                    ->where('snapshot_id', $previousSnapshotId)
                    ->where('series_key', $seriesKey)
                    ->update(['retired_at' => $now]);
            }

            DB::table(self::SNAPSHOTS_TABLE)->insert([
                'snapshot_id' => $snapshotId,
                'series_key' => $seriesKey,
                'payload' => $payload,
                'created_at' => $now,
                'retired_at' => null,
            ]);
            DB::table(self::POINTERS_TABLE)->updateOrInsert(
                ['series_key' => $seriesKey],
                ['snapshot_id' => $snapshotId, 'updated_at' => $now],
            );
        }
    }

    public function pruneInactive(): int
    {
        if (! $this->tablesExist()) {
            return 0;
        }

        $cutoff = now()->subHours(max(
            24,
            (int) config('maddraxikon.crawler.snapshot_retention_hours', 48),
        ));
        $active = DB::table(self::POINTERS_TABLE)
            ->pluck('snapshot_id', 'series_key');
        $deleted = 0;

        DB::table(self::SNAPSHOTS_TABLE)
            ->where('retired_at', '<', $cutoff)
            ->orderBy('retired_at')
            ->get(['snapshot_id', 'series_key'])
            ->each(function (object $snapshot) use ($active, &$deleted): void {
                $snapshotId = (string) $snapshot->snapshot_id;
                $seriesKey = (string) $snapshot->series_key;

                if ($active->get($seriesKey) === $snapshotId) {
                    return;
                }

                $deleted += DB::table(self::SNAPSHOTS_TABLE)
                    ->where('snapshot_id', $snapshotId)
                    ->where('series_key', $seriesKey)
                    ->delete();
            });

        return $deleted;
    }

    private function tablesExist(): bool
    {
        return Schema::hasTable(self::POINTERS_TABLE)
            && Schema::hasTable(self::SNAPSHOTS_TABLE);
    }

    /** @return array<int, array<string, mixed>> */
    private function decode(string $payload, BookType $type): array
    {
        try {
            $rows = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new MaddraxikonCrawlException(
                "Datenbank-Snapshot für {$type->label()} enthält ungültiges JSON.",
                previous: $exception,
            );
        }

        if (! is_array($rows) || ! array_is_list($rows)) {
            throw new MaddraxikonCrawlException(
                "Datenbank-Snapshot für {$type->label()} enthält keine JSON-Liste."
            );
        }

        return $rows;
    }
}
