<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Romantausch;

use App\Services\Romantausch\BundleService;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(BundleServiceTestFixtures::class);

beforeEach(function (): void {
    $this->service = app(BundleService::class);
});

test('parse single numbers', function (): void {
    $this->assertArraysAreEqual([1, 5, 10], $this->service->parseBookNumbers('1, 5, 10'));
});

test('parse range', function (): void {
    $this->assertArraysAreEqual([1, 2, 3, 4, 5], $this->service->parseBookNumbers('1-5'));
});

test('parse mixed ranges and singles', function (): void {
    $result = $this->service->parseBookNumbers('1-3, 5, 7-9');
    $this->assertArraysAreEqual([1, 2, 3, 5, 7, 8, 9], $result);
});

test('parse removes duplicates', function (): void {
    $result = $this->service->parseBookNumbers('1, 2, 1-3');
    $this->assertArraysAreEqual([1, 2, 3], $result);
});

test('parse handles whitespace', function (): void {
    $result = $this->service->parseBookNumbers('  1 , 2 , 3  ');
    $this->assertArraysAreEqual([1, 2, 3], $result);
});

test('parse handles leading zeros', function (): void {
    $result = $this->service->parseBookNumbers('001, 010, 100');
    $this->assertArraysAreEqual([1, 10, 100], $result);
});

test('parse ignores invalid ranges', function (): void {
    // Umgekehrter Bereich wird ignoriert
    $result = $this->service->parseBookNumbers('5-1, 10');
    $this->assertArraysAreEqual([10], $result);
});

test('parse ignores too large ranges', function (): void {
    // Bereich > MAX_RANGE_SPAN wird ignoriert
    $result = $this->service->parseBookNumbers('1-600, 10');
    $this->assertArraysAreEqual([10], $result);
});

test('parse ignores non numeric', function (): void {
    $result = $this->service->parseBookNumbers('1, abc, 3');
    $this->assertArraysAreEqual([1, 3], $result);
});

test('parse ignores ranges with non numeric endpoints', function (): void {
    $result = $this->service->parseBookNumbers('abc-3, 1-xyz, 4-5, 7');
    expect($result)->toBe([4, 5, 7]);
});

test('parse returns empty for invalid input', function (): void {
    $this->assertArraysAreEqual([], $this->service->parseBookNumbers(''));
    $this->assertArraysAreEqual([], $this->service->parseBookNumbers('abc'));
});

test('format single number', function (): void {
    $offers = collect([(object) ['book_number' => 5]]);
    expect($this->service->formatBookNumbersRange($offers))->toEqual('5');
});

test('format consecutive range', function (): void {
    $offers = collect([(object) ['book_number' => 1], (object) ['book_number' => 2], (object) ['book_number' => 3]]);
    expect($this->service->formatBookNumbersRange($offers))->toEqual('1-3');
});

test('format mixed ranges', function (): void {
    $offers = collect([(object) ['book_number' => 1], (object) ['book_number' => 2], (object) ['book_number' => 3], (object) ['book_number' => 5], (object) ['book_number' => 7], (object) ['book_number' => 8]]);
    expect($this->service->formatBookNumbersRange($offers))->toEqual('1-3, 5, 7-8');
});

test('format empty collection', function (): void {
    expect($this->service->formatBookNumbersRange(collect()))->toBeEmpty();
});

test('valid condition range', function (): void {
    // Von besser nach schlechter ist valide
    expect($this->service->validateConditionRange('Z0', 'Z2'))->toBeNull();
    expect($this->service->validateConditionRange('Z1', 'Z1'))->toBeNull();
    // Gleich ist ok
});

test('invalid condition range reversed', function (): void {
    // Von schlechter nach besser ist nicht erlaubt
    $error = $this->service->validateConditionRange('Z2', 'Z0');
    expect($error)->not->toBeNull()
        ->toContain('Bis');
});

test('condition range without max', function (): void {
    // Kein Max-Wert ist immer gültig
    expect($this->service->validateConditionRange('Z2', null))->toBeNull();
    expect($this->service->validateConditionRange('Z2', ''))->toBeNull();
});

test('unknown condition value', function (): void {
    $error = $this->service->validateConditionRange('INVALID', 'Z2');
    expect($error)->not->toBeNull()
        ->toContain('INVALID');
});

test('unknown upper condition reports the invalid value and allowed values', function (): void {
    $error = $this->service->validateConditionRange('Z0', 'INVALID');
    expect($error)->not->toBeNull()
        ->toContain("'INVALID'");
    foreach (BundleService::CONDITION_ORDER as $condition) {
        expect($error)->toContain($condition);
    }
});

test('no missing numbers', function (): void {
    $requested = [1, 2, 3];
    $existing = [1, 2, 3, 4, 5];
    expect($this->service->validateMissingBookNumbers($requested, $existing))->toBeNull();
});

test('some missing numbers', function (): void {
    $requested = [1, 2, 999, 1000];
    $existing = [1, 2, 3];
    $result = $this->service->validateMissingBookNumbers($requested, $existing);
    expect($result)->not->toBeNull()
        ->toContain('999')
        ->toContain('1000');
});

test('truncates long missing list', function (): void {
    $requested = range(1, 20);
    $existing = [];
    $result = $this->service->validateMissingBookNumbers($requested, $existing);
    expect($result)->not->toBeNull()
        ->toContain('...')
        ->toContain('20 insgesamt');
});

test('constants have expected values', function (): void {
    expect(BundleService::MAX_RANGE_SPAN)->toEqual(500)
        ->and(BundleService::MIN_BUNDLE_SIZE)->toEqual(2)
        ->and(BundleService::MAX_BUNDLE_SIZE)->toEqual(200)
        ->and(BundleService::CONDITION_ORDER)->toContain('Z0')
        ->toContain('Z4');
});
