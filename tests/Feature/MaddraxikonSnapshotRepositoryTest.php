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

    private function datasetJson(?string $cycle): string
    {
        return (string) json_encode([[
            'nummer' => 1,
            'titel' => 'Roman',
            'zyklus' => $cycle,
        ]], JSON_THROW_ON_ERROR);
    }
}
