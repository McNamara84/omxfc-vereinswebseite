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

    public function test_index_uses_fallback_when_no_photos_found(): void
    {
        Http::fake([
            'cloud.maddrax-fanclub.de/*1.jpg' => Http::response('', 404),
        ]);

        $this->actingAs($this->actingMember());

        $response = $this->get('/fotogalerie');

        $response->assertOk();

        $photos = $response->viewData('photos');
        $this->assertEquals(asset('images/galerie/2025/placeholder1.jpg'), $photos['2025'][0]);
        $this->assertEquals(asset('images/galerie/2024/placeholder1.jpg'), $photos['2024'][0]);
        $this->assertEquals(asset('images/galerie/2023/placeholder1.jpg'), $photos['2023'][0]);
    }

    private function ensurePlaceholder(string $year): void
    {
        $path = public_path("images/galerie/{$year}/placeholder1.jpg");
        if (!file_exists($path)) {
            mkdir(dirname($path), 0777, true);
            file_put_contents($path, 'dummy');
        }
    }

    public function test_proxy_image_returns_remote_file(): void
    {
        $this->ensurePlaceholder('2025');

        Http::fake([
            'cloud.maddrax-fanclub.de/*Foto1.jpg' => Http::response('img', 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $response = app(\App\Http\Controllers\PhotoGalleryController::class)->proxyImage('2025', 1);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('image/jpeg', $response->headers->get('Content-Type'));
        $this->assertEquals('img', $response->getContent());
    }

    public function test_proxy_image_returns_placeholder_on_failure(): void
    {
        $this->ensurePlaceholder('2025');

        Http::fake([
            'cloud.maddrax-fanclub.de/*Foto1.jpg' => Http::response('', 404),
        ]);

        $response = app(\App\Http\Controllers\PhotoGalleryController::class)->proxyImage('2025', 1);

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $response);
        $this->assertStringEndsWith('images/galerie/2025/placeholder1.jpg', $response->getFile()->getPathname());
    }
}
