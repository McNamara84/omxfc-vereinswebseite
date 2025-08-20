<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;

class PageAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_are_accessible(): void
    {
        $urls = [
            '/',
            '/satzung',
            '/chronik',
            '/ehrenmitglieder',
            '/termine',
            '/mitglied-werden',
            '/spenden',
            '/impressum',
            '/datenschutz',
            '/changelog',
            '/mitglied-werden/erfolgreich',
            '/mitglied-werden/bestaetigt',
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_home_page_shows_correct_member_count(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();

        $team->users()->attach(User::factory()->create(), ['role' => 'Mitglied']);
        $team->users()->attach(User::factory()->create(), ['role' => 'Anw\xC3\xA4rter']);

        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('2');
    }
}
