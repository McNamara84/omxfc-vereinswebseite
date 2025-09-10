<?php

namespace Tests\Feature;

use Tests\TestCase;

class LegalPagesContentTest extends TestCase
{
    public function seed($class = 'Database\\Seeders\\DatabaseSeeder')
    {
        // Prevent automatic seeding during TestCase setup
        return $this;
    }

    public function test_impressum_page_shows_register_information(): void
    {
        $this->get('/impressum')
            ->assertOk()
            ->assertSee('Registernummer: 9677')
            ->assertSee('Vereinsregister');
    }

    public function test_datenschutz_page_highlights_legal_basis_and_rights(): void
    {
        $this->get('/datenschutz')
            ->assertOk()
            ->assertSee('Rechtsgrundlage der Verarbeitung')
            ->assertSee('Art. 6 Abs. 1 lit. b DSGVO', false)
            ->assertSee('Beschwerderecht bei einer Aufsichtsbehörde')
            ->assertSee('Datensicherheit');
    }
}
