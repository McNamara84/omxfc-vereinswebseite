<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\ConditionOptions;
use Illuminate\Foundation\Testing\TestCase;

pest()->extend(TestCase::class);

covers(ConditionOptions::class);

beforeEach(function (): void {
    app()->setLocale('de');
});

test('standard returns seven options', function (): void {
    $options = ConditionOptions::standard();
    expect($options)->toHaveCount(7);
    $ids = array_column($options, 'id');
    $this->assertArraysAreIdentical(['Z0', 'Z0-1', 'Z1', 'Z1-2', 'Z2', 'Z2-3', 'Z3'], $ids);
});

test('full returns nine options', function (): void {
    $options = ConditionOptions::full();
    expect($options)->toHaveCount(9);
    $ids = array_column($options, 'id');
    $this->assertArraysAreIdentical(['Z0', 'Z0-1', 'Z1', 'Z1-2', 'Z2', 'Z2-3', 'Z3', 'Z3-4', 'Z4'], $ids);
});

test('with same option prepends empty id option', function (): void {
    $options = ConditionOptions::withSameOption();
    expect($options)->toHaveCount(10)
        ->and($options[0]['id'])->toBe('')
        ->and($options[0]['name'])->toContain('Gleicher Zustand');
});

test('all options have id and name keys', function (): void {
    expect(ConditionOptions::full())->each->toHaveKeys(['id', 'name']);
});

test('option names start with condition code', function (): void {
    foreach (ConditionOptions::full() as $option) {
        expect($option['name'])->toStartWith($option['id'].' - ');
    }
});

test('intermediate conditions have title tooltip', function (): void {
    $intermediateIds = ['Z0-1', 'Z1-2', 'Z2-3', 'Z3-4'];
    foreach (ConditionOptions::full() as $option) {
        if (in_array($option['id'], $intermediateIds)) {
            expect($option)->toHaveKey('title')
                ->and($option['title'])->not->toBeEmpty();
        }
    }
});

test('main conditions have no title', function (): void {
    $mainIds = ['Z0', 'Z1', 'Z2', 'Z3', 'Z4'];
    foreach (ConditionOptions::full() as $option) {
        if (in_array($option['id'], $mainIds)) {
            expect($option)->not->toHaveKey('title');
        }
    }
});

test('full extends standard', function (): void {
    $standard = ConditionOptions::standard();
    $full = ConditionOptions::full();
    // Die ersten 7 Einträge von full() müssen standard() entsprechen
    expect(array_slice($full, 0, 7))->toBe($standard);
});

test('with same option contains all full options', function (): void {
    $full = ConditionOptions::full();
    $withSame = ConditionOptions::withSameOption();
    // Ab Index 1 müssen alle full()-Einträge enthalten sein
    expect(array_slice($withSame, 1))->toBe($full);
});
