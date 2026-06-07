<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\KompendiumSuche;
use App\Models\KompendiumRoman;
use App\Models\KompendiumSearchLog;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\User;
use App\Services\KompendiumSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class KompendiumSearchLoggingTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function purchaseKompendiumForUser(User $user): void
    {
        $reward = Reward::query()->where('slug', 'kompendium')->firstOrFail();

        RewardPurchase::query()->create([
            'user_id' => $user->id,
            'reward_id' => $reward->id,
            'cost_baxx' => $reward->cost_baxx,
            'purchased_at' => now(),
        ]);
    }

    private function createIndexedRoman(User $user, string $path, string $content, string $serie = 'maddrax', int $number = 1): void
    {
        Storage::disk('private')->put($path, $content);

        KompendiumRoman::query()->create([
            'dateiname' => basename($path),
            'dateipfad' => $path,
            'serie' => $serie,
            'roman_nr' => $number,
            'titel' => pathinfo($path, PATHINFO_FILENAME),
            'hochgeladen_am' => now(),
            'hochgeladen_von' => $user->id,
            'status' => 'indexiert',
            'indexiert_am' => now(),
        ]);
    }

    private function mockSearch(array $paths, int $times = 1): void
    {
        $this->partialMock(KompendiumSearchService::class, function ($mock) use ($paths, $times) {
            $mock->shouldReceive('search')
                ->times($times)
                ->andReturn(['ids' => $paths]);
        });
    }

    public function test_livewire_search_submit_writes_search_log(): void
    {
        Storage::fake('private');
        $user = $this->actingMemberWithPoints(150);
        $this->purchaseKompendiumForUser($user);

        $path = 'romane/maddrax/001 - Test.txt';
        $this->createIndexedRoman($user, $path, 'Aruula findet einen Hinweis.');
        $this->mockSearch([$path]);

        Livewire::actingAs($user)
            ->test(KompendiumSuche::class)
            ->set('query', 'Aruula')
            ->call('performSearch')
            ->assertHasNoErrors();

        $log = KompendiumSearchLog::query()->firstOrFail();

        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('Aruula', $log->query);
        $this->assertSame('aruula', $log->normalized_query);
        $this->assertSame('search_submit', $log->source);
        $this->assertSame('ok', $log->status);
        $this->assertSame(1, $log->results_count);
        $this->assertFalse($log->is_admin_search);
        $this->assertSame(['maddrax'], $log->selected_serien);
        $this->assertSame(['aruula'], $log->parsed_query['terms']);
        $this->assertArrayNotHasKey('ip_address', $log->getAttributes());
        $this->assertArrayNotHasKey('user_agent', $log->getAttributes());
    }

    public function test_filter_and_sort_changes_are_logged_but_load_more_is_not(): void
    {
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

        Livewire::actingAs($user)
            ->test(KompendiumSuche::class)
            ->set('query', 'Aruula')
            ->call('performSearch')
            ->call('loadMore')
            ->set('selectedSerien', ['maddrax'])
            ->set('direction', 'asc');

        $this->assertSame(3, KompendiumSearchLog::query()->count());
        $this->assertSame(1, KompendiumSearchLog::query()->where('source', 'search_submit')->count());
        $this->assertSame(1, KompendiumSearchLog::query()->where('source', 'filter_change')->count());
        $this->assertSame(1, KompendiumSearchLog::query()->where('source', 'sort_change')->count());
    }

    public function test_negative_only_query_is_logged_with_status(): void
    {
        Storage::fake('private');
        $user = $this->actingMemberWithPoints(150);
        $this->purchaseKompendiumForUser($user);

        Livewire::actingAs($user)
            ->test(KompendiumSuche::class)
            ->set('query', 'NOT Aruula')
            ->call('performSearch')
            ->assertSet('error', 'Bitte gib mindestens einen positiven Suchbegriff ein.');

        $log = KompendiumSearchLog::query()->firstOrFail();

        $this->assertSame('no_positive_operands', $log->status);
        $this->assertSame('NOT Aruula', $log->query);
        $this->assertSame(0, $log->results_count);
    }

    public function test_admin_search_is_stored_but_marked_as_admin_search(): void
    {
        Storage::fake('private');
        $admin = $this->actingAdmin();
        $this->purchaseKompendiumForUser($admin);

        $path = 'romane/maddrax/001 - Admin Test.txt';
        $this->createIndexedRoman($admin, $path, 'Admin findet Aruula.');
        $this->mockSearch([$path]);

        Livewire::actingAs($admin)
            ->test(KompendiumSuche::class)
            ->set('query', 'Aruula')
            ->call('performSearch');

        $this->assertTrue(KompendiumSearchLog::query()->firstOrFail()->is_admin_search);
    }

    public function test_api_search_logs_only_first_page(): void
    {
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

        $this->assertSame(1, KompendiumSearchLog::query()->count());
        $this->assertSame('api_search', KompendiumSearchLog::query()->firstOrFail()->source);
    }

    public function test_search_without_kompendium_access_does_not_write_log(): void
    {
        $this->actingMember(Role::Mitglied);

        $this->getJson('/kompendium/suche?q=Aruula')
            ->assertStatus(403);

        $this->assertSame(0, KompendiumSearchLog::query()->count());
    }
}
