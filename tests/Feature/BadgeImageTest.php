<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Models\User;
use App\Models\Team;

class BadgeImageTest extends TestCase
{
    use RefreshDatabase;
    private string $filePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filePath = public_path('images/badges/test.svg');
        (new Filesystem)->ensureDirectoryExists(dirname($this->filePath));
    }

    protected function tearDown(): void
    {
        if (file_exists($this->filePath)) {
            unlink($this->filePath);
        }
        parent::tearDown();
    }

    private function actingMember(): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Mitglied']);
        return $user;
    }

    public function test_existing_badge_image_is_served(): void
    {
        file_put_contents($this->filePath, '<svg></svg>');
        $this->actingAs($this->actingMember());

        $response = $this->get('/abzeichen/test.svg');

        $response->assertOk();
        $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
    }

    public function test_nonexistent_badge_image_returns_404(): void
    {
        $this->actingAs($this->actingMember());

        $response = $this->get('/abzeichen/does-not-exist.svg');

        $response->assertNotFound();
    }
}
