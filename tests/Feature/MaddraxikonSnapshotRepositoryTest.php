<?php

namespace Tests\Feature;

use App\Enums\BookType;
use App\Services\Maddraxikon\MaddraxikonSnapshotRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class MaddraxikonSnapshotRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_activation_requires_the_callers_database_transaction(): void
    {
        $testTransactionLevel = DB::transactionLevel();

        for ($level = 0; $level < $testTransactionLevel; $level++) {
            DB::rollBack();
        }

        try {
            app(MaddraxikonSnapshotRepository::class)->storeAndActivate(
                '44444444-4444-4444-8444-444444444444',
                ['maddrax' => $this->datasetJson('Euree')],
            );
            $this->fail('Expected activation outside a transaction to fail.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('Datenbanktransaktion', $exception->getMessage());
        } finally {
            for ($level = 0; $level < $testTransactionLevel; $level++) {
                DB::beginTransaction();
            }
        }
    }

    public function test_partial_activation_preserves_other_series_pointers(): void
    {
        $repository = app(MaddraxikonSnapshotRepository::class);
        DB::transaction(fn () => $repository->storeAndActivate(
            '55555555-5555-4555-8555-555555555555',
            [
                'maddrax' => $this->datasetJson('Euree'),
                'hardcovers' => $this->datasetJson(null),
            ],
        ));
        DB::transaction(fn () => $repository->storeAndActivate(
            '66666666-6666-4666-8666-666666666666',
            ['maddrax' => $this->datasetJson('Weltrat')],
        ));

        $this->assertSame(
            '66666666-6666-4666-8666-666666666666',
            $repository->activeId(BookType::MaddraxDieDunkleZukunftDerErde),
        );
        $this->assertSame(
            '55555555-5555-4555-8555-555555555555',
            $repository->activeId(BookType::MaddraxHardcover),
        );
    }

    public function test_pruning_keeps_freshly_retired_snapshots_and_removes_them_after_retention(): void
    {
        $repository = app(MaddraxikonSnapshotRepository::class);
        $oldId = '77777777-7777-4777-8777-777777777777';
        $activeId = '88888888-8888-4888-8888-888888888888';
        DB::transaction(fn () => $repository->storeAndActivate(
            $oldId,
            ['maddrax' => $this->datasetJson('Euree')],
        ));
        DB::table('maddraxikon_book_snapshots')
            ->where('snapshot_id', $oldId)
            ->update(['created_at' => now()->subDays(3)]);
        DB::transaction(fn () => $repository->storeAndActivate(
            $activeId,
            ['maddrax' => $this->datasetJson('Weltrat')],
        ));

        $this->assertSame(0, $repository->pruneInactive());
        $this->assertDatabaseHas('maddraxikon_book_snapshots', ['snapshot_id' => $oldId]);
        DB::table('maddraxikon_book_snapshots')
            ->where('snapshot_id', $oldId)
            ->update(['retired_at' => now()->subDays(3)]);

        $this->assertSame(1, $repository->pruneInactive());
        $this->assertDatabaseMissing('maddraxikon_book_snapshots', ['snapshot_id' => $oldId]);
        $this->assertDatabaseHas('maddraxikon_book_snapshots', ['snapshot_id' => $activeId]);
    }

    public function test_schema_and_active_pointer_queries_are_cached_outside_transactions(): void
    {
        $testTransactionLevel = DB::transactionLevel();
        $snapshotId = '20122012-2012-4012-8012-201220122012';
        $numericType = BookType::ZweiTausendZwölfDasJahrDerApokalypse;

        for ($level = 0; $level < $testTransactionLevel; $level++) {
            DB::rollBack();
        }

        try {
            DB::table('maddraxikon_book_snapshot_pointers')->insert([
                'series_key' => $numericType->key(),
                'snapshot_id' => $snapshotId,
                'updated_at' => now(),
            ]);
            DB::flushQueryLog();
            DB::enableQueryLog();
            $repository = new MaddraxikonSnapshotRepository;

            foreach (BookType::cases() as $type) {
                $expected = $type === $numericType ? $snapshotId : null;

                $this->assertSame($expected, $repository->activeId($type));
                $this->assertSame($expected, $repository->activeId($type));
            }

            $this->assertCount(3, DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
            DB::table('maddraxikon_book_snapshot_pointers')
                ->where('series_key', $numericType->key())
                ->delete();

            for ($level = 0; $level < $testTransactionLevel; $level++) {
                DB::beginTransaction();
            }
        }
    }

    public function test_active_pointer_cache_can_be_reset_on_a_long_lived_repository(): void
    {
        $testTransactionLevel = DB::transactionLevel();
        $oldId = '31313131-3131-4131-8131-313131313131';
        $newId = '32323232-3232-4232-8232-323232323232';
        $type = BookType::MaddraxDieDunkleZukunftDerErde;

        for ($level = 0; $level < $testTransactionLevel; $level++) {
            DB::rollBack();
        }

        try {
            $repository = new MaddraxikonSnapshotRepository;
            DB::transaction(fn () => $repository->storeAndActivate(
                $oldId,
                ['maddrax' => $this->datasetJson('Euree')],
            ));
            $this->assertSame($oldId, $repository->activeId($type));

            DB::transaction(fn () => (new MaddraxikonSnapshotRepository)->storeAndActivate(
                $newId,
                ['maddrax' => $this->datasetJson('Weltrat')],
            ));
            $this->assertSame($oldId, $repository->activeId($type));

            $repository->clearActiveIdsCache();

            $this->assertSame($newId, $repository->activeId($type));
        } finally {
            DB::table('maddraxikon_book_snapshot_pointers')
                ->where('series_key', $type->key())
                ->delete();
            DB::table('maddraxikon_book_snapshots')
                ->whereIn('snapshot_id', [$oldId, $newId])
                ->delete();

            for ($level = 0; $level < $testTransactionLevel; $level++) {
                DB::beginTransaction();
            }
        }
    }

    private function datasetJson(?string $cycle): string
    {
        return (string) json_encode([[
            'nummer' => 1,
            'titel' => 'Roman',
            'zyklus' => $cycle,
        ]], JSON_THROW_ON_ERROR);
    }
}
