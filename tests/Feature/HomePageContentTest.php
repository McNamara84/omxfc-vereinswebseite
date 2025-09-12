<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_displays_all_sections_and_projects(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Willkommen beim Offiziellen MADDRAX Fanclub e. V.!')
            ->assertSee('<title>Startseite – Offizieller MADDRAX Fanclub e. V.</title>', false)
            ->assertSee('Wer wir sind')
            ->assertSee('Wir Maddrax-Fans sind eine muntere Gruppe')
            ->assertSee('Was wir machen')
            ->assertSee('Wir treffen uns in unterschiedlichen Konstellationen mal online')
            ->assertSee('Aktuelle Projekte')
            ->assertSee('Maddraxikon')
            ->assertSee('EARDRAX')
            ->assertSee('MAPDRAX')
            ->assertSee('Fantreffen 2026')
            ->assertSee('Vorteile einer Mitgliedschaft')
            ->assertSee('Kostenlose Teilnahme an den jährlichen Fantreffen')
            ->assertSee('aktive Mitglieder');
    }

    public function test_home_page_contains_structured_data(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('@context', false)
            ->assertSee('SearchAction', false);
    }
}
