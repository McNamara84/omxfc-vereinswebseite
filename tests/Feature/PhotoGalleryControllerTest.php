<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class PhotoGalleryControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Mitglied']);
        return $user;
    }

    public function test_index_loads_photos_for_years(): void
    {
        Http::fake([
            'cloud.maddrax-fanclub.de/*1.jpg' => Http::response('', 200),
            'cloud.maddrax-fanclub.de/*2.jpg' => Http::response('', 404),
        ]);

        $this->actingAs($this->actingMember());

        $response = $this->get('/fotogalerie');

        $response->assertOk();
        $response->assertViewHas('activeYear', '2025');

        $photos = $response->viewData('photos');
        $this->assertEquals('https://cloud.maddrax-fanclub.de/public.php/dav/files/jnGa6sEecKa3fiX/Foto1.jpg', $photos['2025'][0]);
        $this->assertEquals('https://cloud.maddrax-fanclub.de/public.php/dav/files/tztWY5ML5XMRWPw/Foto1.jpg', $photos['2024'][0]);
        $this->assertEquals('https://cloud.maddrax-fanclub.de/public.php/dav/files/jjpfnJbgStE8LcQ/Foto1.jpg', $photos['2023'][0]);
    }
}
