<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\FantreffenVipAuthors;
use App\Models\FantreffenVipAuthor;
use App\Models\Veranstaltung;
use Database\Seeders\FantreffenPlaywrightSeeder;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(FantreffenVipAuthorsTestFixtures::class);

beforeEach(function (): void {
    Config::set('app.testing_minimal_layout', true);
    $this->veranstaltung = Veranstaltung::featuredPublic() ?? Veranstaltung::query()->orderByDesc('ist_highlight')->firstOrFail();
    $this->veranstaltung->update(['vip_autoren_aktiv' => true]);
});

test('vip authors page is accessible for admin', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $this->actingAs($admin);
    $response = $this->get($this->vipAuthorsRoute());
    $response->assertStatus(200);
    $response->assertSeeLivewire('fantreffen-vip-authors');
});

test('legacy vip authors route redirects to canonical event route', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $this->actingAs($admin);
    $this->get(route('admin.fantreffen.vip-authors'))->assertRedirect(route('admin.veranstaltungen.vip-authors', ['veranstaltung' => 'maddrax-fantreffen-2026']));
});

test('playwright seeder assigns vip authors to legacy event slug', function (): void {
    $this->veranstaltung->update(['slug' => 'future-featured-event', 'status' => 'veroeffentlicht', 'ist_highlight' => true]);
    $legacyVeranstaltung = Veranstaltung::query()->firstWhere('slug', 'maddrax-fantreffen-2026');
    if (! $legacyVeranstaltung) {
        $legacyVeranstaltung = Veranstaltung::create(['titel' => 'Maddrax-Fantreffen 2026', 'slug' => 'maddrax-fantreffen-2026', 'status' => 'archiviert', 'vip_autoren_aktiv' => true, 'ist_highlight' => false]);
    }
    $this->seed(FantreffenPlaywrightSeeder::class);
    $this->assertDatabaseHas('fantreffen_vip_authors', ['name' => 'Oliver Fröhlich', 'veranstaltung_id' => $legacyVeranstaltung->id]);
    $this->assertDatabaseHas('fantreffen_vip_authors', ['name' => 'Jo Zybell', 'veranstaltung_id' => $legacyVeranstaltung->id]);
});

test('vip authors page is accessible for vorstand', function (): void {
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $this->actingAs($vorstand);
    $response = $this->get($this->vipAuthorsRoute());
    $response->assertStatus(200);
});

test('vip authors page is accessible for kassenwart', function (): void {
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $this->actingAs($kassenwart);
    $response = $this->get($this->vipAuthorsRoute());
    $response->assertStatus(200);
});

test('vip authors page is not accessible for regular member', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $this->actingAs($member);
    $response = $this->get($this->vipAuthorsRoute());
    $response->assertStatus(403);
});

test('vip authors page is not accessible for guests', function (): void {
    $response = $this->get($this->vipAuthorsRoute());
    $response->assertRedirect('/login');
});

test('admin can create vip author', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    Livewire::actingAs($admin)->test(FantreffenVipAuthors::class, ['veranstaltung' => $this->veranstaltung])->call('openForm')->set('name', 'Oliver Fröhlich')->set('pseudonym', 'Ian Rolf Hill')->set('is_active', true)->set('sort_order', 0)->call('save')->assertHasNoErrors();
    $this->assertDatabaseHas('fantreffen_vip_authors', ['name' => 'Oliver Fröhlich', 'pseudonym' => 'Ian Rolf Hill', 'is_active' => true, 'sort_order' => 0]);
});

test('admin can create vip author with tentative status', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    Livewire::actingAs($admin)->test(FantreffenVipAuthors::class, ['veranstaltung' => $this->veranstaltung])->call('openForm')->set('name', 'Vorbehalt Autor')->set('pseudonym', '')->set('is_active', true)->set('is_tentative', true)->call('save')->assertHasNoErrors();
    $this->assertDatabaseHas('fantreffen_vip_authors', ['name' => 'Vorbehalt Autor', 'is_active' => true, 'is_tentative' => true]);
});

test('admin can create vip author without pseudonym', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    Livewire::actingAs($admin)->test(FantreffenVipAuthors::class, ['veranstaltung' => $this->veranstaltung])->call('openForm')->set('name', 'Jo Zybell')->set('pseudonym', '')->set('is_active', true)->call('save')->assertHasNoErrors();
    $this->assertDatabaseHas('fantreffen_vip_authors', ['name' => 'Jo Zybell', 'pseudonym' => null, 'is_active' => true]);
});

test('admin can edit vip author', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $author = FantreffenVipAuthor::create(['name' => 'Original Name', 'pseudonym' => 'Original Pseudo', 'is_active' => true, 'is_tentative' => false, 'sort_order' => 0]);
    Livewire::actingAs($admin)->test(FantreffenVipAuthors::class, ['veranstaltung' => $this->veranstaltung])->call('edit', $author->id)->set('name', 'Updated Name')->set('pseudonym', 'Updated Pseudo')->set('is_tentative', true)->call('save')->assertHasNoErrors();
    $this->assertDatabaseHas('fantreffen_vip_authors', ['id' => $author->id, 'name' => 'Updated Name', 'pseudonym' => 'Updated Pseudo', 'is_tentative' => true]);
});

test('admin can delete vip author', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    // Create three authors with consecutive sort_order values
    $author1 = FantreffenVipAuthor::create(['name' => 'First', 'is_active' => true, 'sort_order' => 0]);
    $author2 = FantreffenVipAuthor::create(['name' => 'To Delete', 'is_active' => true, 'sort_order' => 1]);
    $author3 = FantreffenVipAuthor::create(['name' => 'Third', 'is_active' => true, 'sort_order' => 2]);
    // Delete the middle author
    Livewire::actingAs($admin)->test(FantreffenVipAuthors::class, ['veranstaltung' => $this->veranstaltung])->call('delete', $author2->id);
    // Verify author was deleted
    $this->assertDatabaseMissing('fantreffen_vip_authors', ['id' => $author2->id]);
    // Verify sort_order was recompacted (remaining authors should have consecutive values starting from 0)
    expect($author1->fresh()->sort_order)->toEqual(0);
    expect($author3->fresh()->sort_order)->toEqual(1);
});

test('admin can toggle active status', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $author = FantreffenVipAuthor::create(['name' => 'Test Author', 'is_active' => true, 'sort_order' => 0]);
    Livewire::actingAs($admin)->test(FantreffenVipAuthors::class, ['veranstaltung' => $this->veranstaltung])->call('toggleActive', $author->id);
    $this->assertDatabaseHas('fantreffen_vip_authors', ['id' => $author->id, 'is_active' => false]);
    Livewire::actingAs($admin)->test(FantreffenVipAuthors::class, ['veranstaltung' => $this->veranstaltung])->call('toggleActive', $author->id);
    $this->assertDatabaseHas('fantreffen_vip_authors', ['id' => $author->id, 'is_active' => true]);
});

test('move up swaps positions correctly', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $author1 = FantreffenVipAuthor::create(['name' => 'First', 'sort_order' => 0, 'is_active' => true]);
    $author2 = FantreffenVipAuthor::create(['name' => 'Second', 'sort_order' => 1, 'is_active' => true]);
    Livewire::actingAs($admin)->test(FantreffenVipAuthors::class, ['veranstaltung' => $this->veranstaltung])->call('moveUp', $author2->id);
    expect($author2->fresh()->sort_order)->toEqual(0)
        ->and($author1->fresh()->sort_order)->toEqual(1);
});

test('move down swaps positions correctly', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $author1 = FantreffenVipAuthor::create(['name' => 'First', 'sort_order' => 0, 'is_active' => true]);
    $author2 = FantreffenVipAuthor::create(['name' => 'Second', 'sort_order' => 1, 'is_active' => true]);
    Livewire::actingAs($admin)->test(FantreffenVipAuthors::class, ['veranstaltung' => $this->veranstaltung])->call('moveDown', $author1->id);
    expect($author1->fresh()->sort_order)->toEqual(1)
        ->and($author2->fresh()->sort_order)->toEqual(0);
});

test('move up at top does nothing', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $author = FantreffenVipAuthor::create(['name' => 'First', 'sort_order' => 0, 'is_active' => true]);
    Livewire::actingAs($admin)->test(FantreffenVipAuthors::class, ['veranstaltung' => $this->veranstaltung])->call('moveUp', $author->id);
    expect($author->fresh()->sort_order)->toEqual(0);
});

test('move down at bottom does nothing', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $author = FantreffenVipAuthor::create(['name' => 'Only', 'sort_order' => 0, 'is_active' => true]);
    Livewire::actingAs($admin)->test(FantreffenVipAuthors::class, ['veranstaltung' => $this->veranstaltung])->call('moveDown', $author->id);
    expect($author->fresh()->sort_order)->toEqual(0);
});

test('name is required', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    Livewire::actingAs($admin)->test(FantreffenVipAuthors::class, ['veranstaltung' => $this->veranstaltung])->call('openForm')->set('name', '')->call('save')->assertHasErrors(['name' => 'required']);
});

test('vip authors displayed on public page', function (): void {
    FantreffenVipAuthor::create(['name' => 'Oliver Fröhlich', 'pseudonym' => 'Ian Rolf Hill', 'is_active' => true, 'sort_order' => 0]);
    $response = $this->get($this->publicEventRoute());
    $response->assertStatus(200);
    $response->assertSee('Gästeliste');
    $response->assertSee('Oliver Fröhlich');
    $response->assertSee('Ian Rolf Hill');
});

test('tentative vip authors show label and disclaimer on public page', function (): void {
    FantreffenVipAuthor::create(['name' => 'Vorbehalt Autor', 'pseudonym' => null, 'is_active' => true, 'is_tentative' => true, 'sort_order' => 0]);
    $response = $this->get($this->publicEventRoute());
    $response->assertStatus(200);
    $response->assertSee('Vorbehalt Autor');
    $response->assertSee('(unter Vorbehalt)');
    $crawler = new Crawler($response->getContent());
    expect($crawler->filterXPath('//text()[contains(., "Vorbehalt Autor")]')->count())->toBe(1);
});

test('inactive vip authors not displayed on public page', function (): void {
    FantreffenVipAuthor::create(['name' => 'Hidden Author', 'is_active' => false, 'sort_order' => 0]);
    $response = $this->get($this->publicEventRoute());
    $response->assertStatus(200);
    $response->assertDontSee('VIP-Autoren bestätigt!');
    $response->assertDontSee('Hidden Author');
});

test('display name includes pseudonym when set', function (): void {
    $author = FantreffenVipAuthor::create(['name' => 'Oliver Fröhlich', 'pseudonym' => 'Ian Rolf Hill', 'is_active' => true, 'sort_order' => 0]);
    expect($author->display_name)->toEqual('Oliver Fröhlich („Ian Rolf Hill")');
});

test('display name shows only name without pseudonym', function (): void {
    $author = FantreffenVipAuthor::create(['name' => 'Jo Zybell', 'pseudonym' => null, 'is_active' => true, 'sort_order' => 0]);
    expect($author->display_name)->toEqual('Jo Zybell');
});
