<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\KompendiumSuche;
use App\Models\KompendiumSearchLog;
use App\Services\KompendiumSearchLogService;
use App\Services\KompendiumSearchService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(KompendiumSearchLoggingTestFixtures::class);

afterEach(function (): void {
    Mockery::close();
});

test('livewire search submit writes search log', function (): void {
    Storage::fake('private');
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $path = 'romane/maddrax/001 - Test.txt';
    $this->createIndexedRoman($user, $path, 'Aruula findet einen Hinweis.');
    $this->mockSearch([$path]);
    Livewire::actingAs($user)->test(KompendiumSuche::class)->set('query', 'Aruula')->call('performSearch')->assertHasNoErrors();
    $log = KompendiumSearchLog::query()->firstOrFail();
    expect($log->user_id)->toBe($user->id)
        ->and($log->query)->toBe('Aruula')
        ->and($log->normalized_query)->toBe('aruula')
        ->and($log->source)->toBe('search_submit')
        ->and($log->status)->toBe('ok')
        ->and($log->search_mode)->toBe('lexical')
        ->and($log->duration_ms)->toBeNull()
        ->and($log->results_count)->toBe(1)
        ->and($log->is_admin_search)->toBeFalse()
        ->and($log->selected_serien)->toBe(['maddrax'])
        ->and($log->parsed_query['terms'])->toBe(['aruula'])
        ->and($log->getAttributes())->not->toHaveKey('ip_address')
        ->and($log->getAttributes())->not->toHaveKey('user_agent');
});

test('failed hybrid search is logged as hybrid', function (): void {
    Storage::fake('private');
    config(['kompendium.search.mode' => 'hybrid', 'kompendium.search.index_variant' => 'hybrid', 'scout.driver' => 'typesense']);
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $this->partialMock(KompendiumSearchService::class, function ($mock) {
        $mock->shouldReceive('searchWithContext')->once()->andThrow(new \RuntimeException('Typesense hybrid search failed.'));
    });
    Livewire::actingAs($user)->test(KompendiumSuche::class)->set('query', 'Aruula')->call('performSearch')->assertSet('error', 'Bei der Suche ist ein Fehler aufgetreten. Bitte versuche es erneut.');
    $log = KompendiumSearchLog::query()->firstOrFail();
    expect($log->status)->toBe('error')
        ->and($log->search_mode)->toBe('hybrid')
        ->and($log->duration_ms)->toBeNull();
});

test('filter and sort changes are logged but load more is not', function (): void {
    Storage::fake('private');
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $paths = [];
    foreach (range(1, 6) as $number) {
        $path = sprintf('romane/maddrax/%03d - Maddrax Treffer %d.txt', $number, $number);
        $this->createIndexedRoman($user, $path, 'Aruula findet einen Hinweis.', 'maddrax', $number);
        $paths[] = $path;
    }
    $missionPath = 'romane/missionmars/001 - Mars Treffer.txt';
    $this->createIndexedRoman($user, $missionPath, 'Aruula findet einen Hinweis.', 'missionmars', 1);
    $paths[] = $missionPath;
    $this->mockSearch($paths, 4);
    Livewire::actingAs($user)->test(KompendiumSuche::class)->set('query', 'Aruula')->call('performSearch')->call('loadMore')->set('selectedSerien', ['maddrax'])->set('direction', 'asc');
    expect(KompendiumSearchLog::query()->count())->toBe(3)
        ->and(KompendiumSearchLog::query()->where('source', 'search_submit')->count())->toBe(1)
        ->and(KompendiumSearchLog::query()->where('source', 'filter_change')->count())->toBe(1)
        ->and(KompendiumSearchLog::query()->where('source', 'sort_change')->count())->toBe(1);
});

test('negative only query is logged with status', function (): void {
    Storage::fake('private');
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    Livewire::actingAs($user)->test(KompendiumSuche::class)->set('query', 'NOT Aruula')->call('performSearch')->assertSet('error', 'Bitte gib mindestens einen positiven Suchbegriff ein.');
    $log = KompendiumSearchLog::query()->firstOrFail();
    expect($log->status)->toBe('no_positive_operands')
        ->and($log->query)->toBe('NOT Aruula')
        ->and($log->results_count)->toBe(0);
});

test('livewire rejects query longer than search log limit without logging', function (): void {
    Storage::fake('private');
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    Livewire::actingAs($user)->test(KompendiumSuche::class)->set('query', str_repeat('a', KompendiumSearchLogService::MAX_QUERY_LENGTH + 1))->call('performSearch')->assertSet('error', 'Bitte gib maximal '.KompendiumSearchLogService::MAX_QUERY_LENGTH.' Zeichen ein.')->assertSet('results', []);
    expect(KompendiumSearchLog::query()->count())->toBe(0);
});

test('admin search is stored but marked as admin search', function (): void {
    Storage::fake('private');
    $admin = $this->actingAdmin();
    $this->purchaseKompendiumForUser($admin);
    $path = 'romane/maddrax/001 - Admin Test.txt';
    $this->createIndexedRoman($admin, $path, 'Admin findet Aruula.');
    $this->mockSearch([$path]);
    Livewire::actingAs($admin)->test(KompendiumSuche::class)->set('query', 'Aruula')->call('performSearch');
    expect(KompendiumSearchLog::query()->firstOrFail()->is_admin_search)->toBeTrue();
});

test('api search logs only first page', function (): void {
    Storage::fake('private');
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $paths = [];
    foreach (range(1, 7) as $number) {
        $path = sprintf('romane/maddrax/%03d - Api Treffer %d.txt', $number, $number);
        $this->createIndexedRoman($user, $path, 'ApiSuchwort kommt hier vor.', 'maddrax', $number);
        $paths[] = $path;
    }
    $this->mockSearch($paths, 2);
    $this->getJson('/kompendium/suche?q=ApiSuchwort&page=1')->assertOk();
    $this->getJson('/kompendium/suche?q=ApiSuchwort&page=2')->assertOk();
    expect(KompendiumSearchLog::query()->count())->toBe(1)
        ->and(KompendiumSearchLog::query()->firstOrFail()->source)->toBe('api_search');
});

test('api search rejects query longer than search log limit without logging', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $this->getJson('/kompendium/suche?q='.str_repeat('a', KompendiumSearchLogService::MAX_QUERY_LENGTH + 1))->assertStatus(422)->assertJsonValidationErrors(['q']);
    expect(KompendiumSearchLog::query()->count())->toBe(0);
});

test('search without kompendium access does not write log', function (): void {
    $this->actingMember(Role::Mitglied);
    $this->getJson('/kompendium/suche?q=Aruula')->assertStatus(403);
    expect(KompendiumSearchLog::query()->count())->toBe(0);
});
