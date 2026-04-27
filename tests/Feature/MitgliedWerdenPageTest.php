<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class MitgliedWerdenPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_page_shows_onboarding_sections_and_single_main_heading(): void
    {
        $response = $this->withoutVite()->get('/mitglied-werden');

        $response->assertOk();
        $response->assertSeeText('Mitglied werden');
        $response->assertSeeText('Was du direkt bekommst');
        $response->assertSeeText('So läuft dein Einstieg ab');
        $response->assertSeeText('Dein Antrag in wenigen Minuten');

        $crawler = new Crawler($response->getContent());
        $this->assertCount(1, $crawler->filter('h1'));
    }

    public function test_membership_success_page_shows_next_steps_and_home_navigation(): void
    {
        $response = $this->withoutVite()->get('/mitglied-werden/erfolgreich');

        $response->assertOk();
        $response->assertSeeText('Antrag erfolgreich eingereicht!');
        $response->assertSeeText('Was jetzt passiert');
        $response->assertSeeText('Zurück zur Startseite');
        $response->assertSee(route('home'), false);
    }

    public function test_membership_confirmation_page_shows_review_process_guidance(): void
    {
        $response = $this->withoutVite()->get('/mitglied-werden/bestaetigt');

        $response->assertOk();
        $response->assertSeeText('Vielen Dank für deine Bestätigung!');
        $response->assertSeeText('Wie es jetzt weitergeht');
        $response->assertSeeText('Prüfung durch Vorstand');
        $response->assertSeeText('Zurück zur Startseite');
    }
}