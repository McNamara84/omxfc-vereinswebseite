<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('membership page shows onboarding sections and single main heading', function (): void {
    $response = $this->withoutVite()->get('/mitglied-werden');
    $response->assertOk();
    $response->assertSeeText('Mitglied werden');
    $response->assertSeeText('Was du direkt bekommst');
    $response->assertSeeText('So läuft dein Einstieg ab');
    $response->assertSeeText('Dein Antrag in wenigen Minuten');
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('h1'))->toHaveCount(1);
});

test('membership success page shows next steps and home navigation', function (): void {
    $response = $this->withoutVite()->get('/mitglied-werden/erfolgreich');
    $response->assertOk();
    $response->assertSeeText('Antrag erfolgreich eingereicht!');
    $response->assertSeeText('Was jetzt passiert');
    $response->assertSeeText('Zurück zur Startseite');
    $response->assertSee(route('home'), false);
});

test('membership confirmation page shows review process guidance', function (): void {
    $response = $this->withoutVite()->get('/mitglied-werden/bestaetigt');
    $response->assertOk();
    $response->assertSeeText('Vielen Dank für deine Bestätigung!');
    $response->assertSeeText('Wie es jetzt weitergeht');
    $response->assertSeeText('Prüfung durch Vorstand');
    $response->assertSeeText('Zurück zur Startseite');
});
