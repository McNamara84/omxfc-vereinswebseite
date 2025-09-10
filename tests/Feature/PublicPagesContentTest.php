<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_chronik_page_shows_heading(): void
    {
        $this->get('/chronik')
            ->assertOk()
            ->assertSee('Chronik des Offiziellen MADDRAX Fanclub e. V.');
    }

    public function test_ehrenmitglieder_page_shows_member_names(): void
    {
        $this->get('/ehrenmitglieder')
            ->assertOk()
            ->assertSee('Ehrenmitglieder')
            ->assertSee('Michael Edelbrock');
    }

    public function test_satzung_page_shows_sections(): void
    {
        $this->get('/satzung')
            ->assertOk()
            ->assertSee('Satzung des Offiziellen MADDRAX Fanclub e.V.')
            ->assertSee('§1 Name', false);
    }

    public function test_mitglied_werden_page_shows_form_fields(): void
    {
        $this->get('/mitglied-werden')
            ->assertOk()
            ->assertSee('Mitglied werden')
            ->assertSee('Vorname')
            ->assertSee('Nachname');
    }

    public function test_mitglied_werden_erfolgreich_page_shows_success_message(): void
    {
        $this->get('/mitglied-werden/erfolgreich')
            ->assertOk()
            ->assertSee('Antrag erfolgreich eingereicht');
    }

    public function test_mitglied_werden_bestaetigt_page_shows_confirmation_message(): void
    {
        $this->get('/mitglied-werden/bestaetigt')
            ->assertOk()
            ->assertSee('Vielen Dank für deine Bestätigung!');
    }
}

