<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(WireNavigateTestFixtures::class);

test('navigation menu has wire navigate on home link', function (): void {
    $html = $this->get('/')->getContent();
    $this->assertLinkHasWireNavigate($html, route('home'), 'Home-Link');
});

test('navigation menu has wire navigate on termine link', function (): void {
    $user = User::factory()->withPersonalTeam()->create();
    $html = $this->actingAs($user)->get('/')->getContent();
    $this->assertLinkHasWireNavigate($html, route('termine'), 'Termine-Link');
});

test('navigation menu has wire navigate on dashboard link for authenticated users', function (): void {
    $user = $this->actingMember();
    $html = $this->actingAs($user)->get('/dashboard')->getContent();
    $this->assertLinkHasWireNavigate($html, route('dashboard'), 'Dashboard-Link');
});

test('navigation menu logout does not have wire navigate', function (): void {
    $user = $this->actingMember();
    $html = $this->actingAs($user)->get('/dashboard')->getContent();
    // Logout-Formular extrahieren und sicherstellen, dass kein wire:navigate darin vorkommt
    preg_match('/<form[^>]*logout[^>]*>.*?<\/form>/si', $html, $logoutForm);
    expect($logoutForm)->not->toBeEmpty()
        ->and($logoutForm[0])->not->toContain('wire:navigate');
});

test('home page has wire navigate on chronik link', function (): void {
    $html = $this->get('/')->getContent();
    $this->assertLinkHasWireNavigate($html, route('chronik'), 'Chronik-Link');
});

test('home page has wire navigate on mitglied werden link', function (): void {
    $html = $this->get('/')->getContent();
    $this->assertLinkHasWireNavigate($html, route('mitglied.werden'), 'Mitglied-werden-Link');
});

test('dashboard has wire navigate on todos link', function (): void {
    $user = $this->actingMember();
    $html = $this->actingAs($user)->get('/dashboard')->getContent();
    $this->assertLinkHasWireNavigate($html, route('todos.index'), 'Todos-Link');
});

test('dashboard has wire navigate on romantausch link', function (): void {
    $user = $this->actingMember();
    $html = $this->actingAs($user)->get('/dashboard')->getContent();
    $this->assertLinkHasWireNavigate($html, route('romantausch.index'), 'Romantausch-Link');
});

test('todos index has wire navigate on create link', function (): void {
    $user = $this->actingVorstand();
    $html = $this->actingAs($user)->get(route('todos.index'))->getContent();
    $this->assertLinkHasWireNavigate($html, route('todos.create'), 'Todo-erstellen-Link');
});

test('reviews index has wire navigate on dashboard navigation link', function (): void {
    $user = $this->actingMember();
    $html = $this->actingAs($user)->get(route('reviews.index'))->getContent();
    // Die Seite enthält mindestens einen wire:navigate-Link (z.B. im Navigationsmenü)
    $this->assertLinkHasWireNavigate($html, route('dashboard'), 'Dashboard-Link in Reviews-Navigation');
});

test('hoerbuecher index has wire navigate in navigation', function (): void {
    $user = $this->actingVorstand();
    $html = $this->actingAs($user)->get(route('hoerbuecher.index'))->getContent();
    $this->assertLinkHasWireNavigate($html, route('dashboard'), 'Dashboard-Link in Hörbücher-Navigation');
});

test('romantausch index has wire navigate on offer link', function (): void {
    $user = $this->actingMember();
    $html = $this->actingAs($user)->get(route('romantausch.index'))->getContent();
    $this->assertLinkHasWireNavigate($html, route('romantausch.create-offer'), 'Angebot-erstellen-Link');
});

test('downloads page does not have wire navigate on download buttons', function (): void {
    $user = $this->actingMember();
    $html = $this->actingAs($user)->get(route('downloads'))->getContent();
    // Seite lädt erfolgreich
    expect($html)->not->toBeEmpty();
    // Download-Links dürfen KEIN wire:navigate haben (Binary-Response)
    if (preg_match_all('/href="([^"]*herunterladen\/[^"]*)"/', $html, $matches)) {
        foreach ($matches[1] as $downloadUrl) {
            $this->assertLinkHasNoWireNavigate($html, $downloadUrl, 'Download-Button');
        }
    }
});

test('livewire progress bar uses brand color', function (): void {
    $config = config('livewire.navigate');
    expect($config['show_progress_bar'])->toBeTrue()
        ->and($config['progress_bar_color'])->toEqual('#8B0116');
});
