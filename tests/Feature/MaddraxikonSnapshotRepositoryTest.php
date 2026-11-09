<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Services\Maddraxikon\MaddraxikonSnapshotRepository;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonSnapshotRepositoryTestFixtures::class);

test('activation requires the callers database transaction', function (): void {
    $testTransactionLevel = DB::transactionLevel();
    for ($level = 0; $level < $testTransactionLevel; $level++) {
        DB::rollBack();
    }
    try {
        app(MaddraxikonSnapshotRepository::class)->storeAndActivate('44444444-4444-4444-8444-444444444444', ['maddrax' => $this->datasetJson('Euree')]);
        $this->fail('Expected activation outside a transaction to fail.');
    } catch (LogicException $exception) {
        expect($exception->getMessage())->toContain('Datenbanktransaktion');
    } finally {
        for ($level = 0; $level < $testTransactionLevel; $level++) {
            DB::beginTransaction();
        }
    }
});

test('partial activation preserves other series pointers', function (): void {
    $repository = app(MaddraxikonSnapshotRepository::class);
    DB::transaction(fn () => $repository->storeAndActivate('55555555-5555-4555-8555-555555555555', ['maddrax' => $this->datasetJson('Euree'), 'hardcovers' => $this->datasetJson(null)]));
    DB::transaction(fn () => $repository->storeAndActivate('66666666-6666-4666-8666-666666666666', ['maddrax' => $this->datasetJson('Weltrat')]));
    expect($repository->activeId(BookType::MaddraxDieDunkleZukunftDerErde))->toBe('66666666-6666-4666-8666-666666666666')
        ->and($repository->activeId(BookType::MaddraxHardcover))->toBe('55555555-5555-4555-8555-555555555555');
});

test('pruning keeps freshly retired snapshots and removes them after retention', function (): void {
    $repository = app(MaddraxikonSnapshotRepository::class);
    $oldId = '77777777-7777-4777-8777-777777777777';
    $activeId = '88888888-8888-4888-8888-888888888888';
    DB::transaction(fn () => $repository->storeAndActivate($oldId, ['maddrax' => $this->datasetJson('Euree')]));
    DB::table('maddraxikon_book_snapshots')->where('snapshot_id', $oldId)->update(['created_at' => now()->subDays(3)]);
    DB::transaction(fn () => $repository->storeAndActivate($activeId, ['maddrax' => $this->datasetJson('Weltrat')]));
    expect($repository->pruneInactive())->toBe(0);
    $this->assertDatabaseHas('maddraxikon_book_snapshots', ['snapshot_id' => $oldId]);
    DB::table('maddraxikon_book_snapshots')->where('snapshot_id', $oldId)->update(['retired_at' => now()->subDays(3)]);
    expect($repository->pruneInactive())->toBe(1);
    $this->assertDatabaseMissing('maddraxikon_book_snapshots', ['snapshot_id' => $oldId]);
    $this->assertDatabaseHas('maddraxikon_book_snapshots', ['snapshot_id' => $activeId]);
});

test('schema and active pointer queries are cached outside transactions', function (): void {
    $testTransactionLevel = DB::transactionLevel();
    $snapshotId = '20122012-2012-4012-8012-201220122012';
    $numericType = BookType::ZweiTausendZwölfDasJahrDerApokalypse;
    for ($level = 0; $level < $testTransactionLevel; $level++) {
        DB::rollBack();
    }
    try {
        DB::table('maddraxikon_book_snapshot_pointers')->insert(['series_key' => $numericType->key(), 'snapshot_id' => $snapshotId, 'updated_at' => now()]);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $repository = new MaddraxikonSnapshotRepository;
        foreach (BookType::cases() as $type) {
            $expected = $type === $numericType ? $snapshotId : null;
            expect($repository->activeId($type))->toBe($expected)
                ->and($repository->activeId($type))->toBe($expected);
        }
        expect(DB::getQueryLog())->toHaveCount(3);
    } finally {
        DB::disableQueryLog();
        DB::table('maddraxikon_book_snapshot_pointers')->where('series_key', $numericType->key())->delete();
        for ($level = 0; $level < $testTransactionLevel; $level++) {
            DB::beginTransaction();
        }
    }
});

test('active pointer cache can be reset on a long lived repository', function (): void {
    $testTransactionLevel = DB::transactionLevel();
    $oldId = '31313131-3131-4131-8131-313131313131';
    $newId = '32323232-3232-4232-8232-323232323232';
    $type = BookType::MaddraxDieDunkleZukunftDerErde;
    for ($level = 0; $level < $testTransactionLevel; $level++) {
        DB::rollBack();
    }
    try {
        $repository = new MaddraxikonSnapshotRepository;
        DB::transaction(fn () => $repository->storeAndActivate($oldId, ['maddrax' => $this->datasetJson('Euree')]));
        expect($repository->activeId($type))->toBe($oldId);
        DB::transaction(fn () => (new MaddraxikonSnapshotRepository)->storeAndActivate($newId, ['maddrax' => $this->datasetJson('Weltrat')]));
        expect($repository->activeId($type))->toBe($oldId);
        $repository->clearActiveIdsCache();
        expect($repository->activeId($type))->toBe($newId);
    } finally {
        DB::table('maddraxikon_book_snapshot_pointers')->where('series_key', $type->key())->delete();
        DB::table('maddraxikon_book_snapshots')->whereIn('snapshot_id', [$oldId, $newId])->delete();
        for ($level = 0; $level < $testTransactionLevel; $level++) {
            DB::beginTransaction();
        }
    }
});
