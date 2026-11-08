<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('skeleton table renders with default props', function (): void {
    $html = Blade::render('<x-skeleton-table />');
    expect($html)->toContain('aria-hidden="true"')
        ->toContain('skeleton')
        ->toContain('<table')
        ->toContain('<thead>')
        ->toContain('<tbody>');
});

test('skeleton table renders correct number of columns', function (): void {
    $html = Blade::render('<x-skeleton-table :columns="3" :rows="1" />');
    // 3 header columns + 3 body columns = 6 total <td>/<th> cells
    expect(substr_count($html, '<th>'))->toEqual(3);
    expect(substr_count($html, '<td>'))->toEqual(3);
});

test('skeleton table renders correct number of rows', function (): void {
    $html = Blade::render('<x-skeleton-table :columns="1" :rows="4" />');
    // 1 header <tr> + 4 body <tr> = 5 total
    expect(substr_count($html, '<tr>'))->toEqual(5);
});

test('skeleton table renders avatar in first column', function (): void {
    $html = Blade::render('<x-skeleton-table :columns="2" :rows="1" :hasAvatar="true" />');
    expect($html)->toContain('rounded-full');
});

test('skeleton table does not render avatar by default', function (): void {
    $html = Blade::render('<x-skeleton-table :columns="2" :rows="1" />');
    expect($html)->not->toContain('rounded-full');
});

test('skeleton table uses variable widths', function (): void {
    $html = Blade::render('<x-skeleton-table :columns="5" :rows="2" />');
    // All 5 width variants should appear
    expect($html)->toContain('w-16');
    expect($html)->toContain('w-20')
        ->toContain('w-24')
        ->toContain('w-28')
        ->toContain('w-32');
});

test('skeleton table is hidden from screen readers', function (): void {
    $html = Blade::render('<x-skeleton-table />');
    expect($html)->toContain('aria-hidden="true"');
});

test('skeleton table with single column and row', function (): void {
    $html = Blade::render('<x-skeleton-table :columns="1" :rows="1" />');
    expect(substr_count($html, '<th>'))->toEqual(1)
        ->and(substr_count($html, '<td>'))->toEqual(1)
        ->and(substr_count($html, '<tr>'))->toEqual(2);
    // 1 header + 1 body
});
