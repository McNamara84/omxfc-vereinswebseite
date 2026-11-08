<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AudiobookEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('episode type accessor', function (): void {
    $special = AudiobookEpisode::create(['episode_number' => 'SE1', 'title' => 'Special', 'author' => 'Author', 'planned_release_date' => '2025-01-01', 'status' => 'Skripterstellung', 'responsible_user_id' => null, 'progress' => 0, 'roles_total' => 0, 'roles_filled' => 0, 'notes' => null]);
    $regular = AudiobookEpisode::create(['episode_number' => 'F1', 'title' => 'Regular', 'author' => 'Author', 'planned_release_date' => '2025-01-01', 'status' => 'Skripterstellung', 'responsible_user_id' => null, 'progress' => 0, 'roles_total' => 0, 'roles_filled' => 0, 'notes' => null]);
    expect($special->episode_type)->toBe('se')
        ->and($special->isSpecialEdition())->toBeTrue()
        ->and($regular->episode_type)->toBe('regular')
        ->and($regular->isSpecialEdition())->toBeFalse();
});

test('all roles filled accessor', function (): void {
    $complete = AudiobookEpisode::create(['episode_number' => 'F2', 'title' => 'Complete', 'author' => 'Author', 'planned_release_date' => '2025-01-01', 'status' => 'Skripterstellung', 'responsible_user_id' => null, 'progress' => 0, 'roles_total' => 2, 'roles_filled' => 2, 'notes' => null]);
    $incomplete = AudiobookEpisode::create(['episode_number' => 'F3', 'title' => 'Incomplete', 'author' => 'Author', 'planned_release_date' => '2025-01-01', 'status' => 'Skripterstellung', 'responsible_user_id' => null, 'progress' => 0, 'roles_total' => 2, 'roles_filled' => 1, 'notes' => null]);
    expect($complete->all_roles_filled)->toBeTrue()
        ->and($incomplete->all_roles_filled)->toBeFalse();
});

test('release year accessor', function (): void {
    $fullDate = AudiobookEpisode::create(['episode_number' => 'F4', 'title' => 'Full', 'author' => 'Author', 'planned_release_date' => '15.06.2024', 'status' => 'Skripterstellung', 'responsible_user_id' => null, 'progress' => 0, 'roles_total' => 0, 'roles_filled' => 0, 'notes' => null]);
    $monthYear = AudiobookEpisode::create(['episode_number' => 'F5', 'title' => 'MonthYear', 'author' => 'Author', 'planned_release_date' => '06.2024', 'status' => 'Skripterstellung', 'responsible_user_id' => null, 'progress' => 0, 'roles_total' => 0, 'roles_filled' => 0, 'notes' => null]);
    $yearOnly = AudiobookEpisode::create(['episode_number' => 'F6', 'title' => 'YearOnly', 'author' => 'Author', 'planned_release_date' => '2024', 'status' => 'Skripterstellung', 'responsible_user_id' => null, 'progress' => 0, 'roles_total' => 0, 'roles_filled' => 0, 'notes' => null]);
    expect($fullDate->release_year)->toBe(2024)
        ->and($monthYear->release_year)->toBe(2024)
        ->and($yearOnly->release_year)->toBe(2024);
});

test('release year accessor handles invalid date', function (): void {
    $invalid = AudiobookEpisode::create(['episode_number' => 'F7', 'title' => 'Invalid', 'author' => 'Author', 'planned_release_date' => 'not-a-date', 'status' => 'Skripterstellung', 'responsible_user_id' => null, 'progress' => 0, 'roles_total' => 0, 'roles_filled' => 0, 'notes' => null]);
    expect($invalid->planned_release_date_parsed)->toBeNull()
        ->and($invalid->release_year)->toBeNull();
});

test('release year accessor handles nonexistent date', function (): void {
    $invalid = AudiobookEpisode::create(['episode_number' => 'F8', 'title' => 'Invalid', 'author' => 'Author', 'planned_release_date' => '31.02.2024', 'status' => 'Skripterstellung', 'responsible_user_id' => null, 'progress' => 0, 'roles_total' => 0, 'roles_filled' => 0, 'notes' => null]);
    expect($invalid->planned_release_date_parsed)->toBeNull()
        ->and($invalid->release_year)->toBeNull();
});
