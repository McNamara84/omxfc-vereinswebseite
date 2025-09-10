<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HomePageContentTest extends TestCase
{
    public function seed($class = 'Database\\Seeders\\DatabaseSeeder')
    {
        // Prevent automatic seeding during TestCase setup
        return $this;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('teams');

        parent::tearDown();
    }

    public function test_home_page_displays_all_sections_and_projects(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Willkommen beim Offiziellen MADDRAX Fanclub e. V.!')
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
