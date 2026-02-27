<?php

namespace Tests\Feature;

use App\Models\Download;
use App\Models\Reward;
use App\Models\RewardPurchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadsTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesUserWithRole;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
    }

    public function test_index_shows_downloads_grouped_by_category(): void
    {
        $this->actingMember();

        Download::factory()->create(['title' => 'Bauanleitung A', 'category' => 'Klemmbaustein-Anleitungen']);
        Download::factory()->create(['title' => 'Story B', 'category' => 'Fanstories']);

        $response = $this->get('/downloads');

        $response->assertOk();
        $response->assertSee('Klemmbaustein-Anleitungen');
        $response->assertSee('Fanstories');
        $response->assertSee('Bauanleitung A');
        $response->assertSee('Story B');
    }

    public function test_download_succeeds_when_reward_is_purchased(): void
    {
        $user = $this->actingMember();

        $download = Download::factory()->create([
            'file_path' => 'downloads/test.pdf',
            'original_filename' => 'Test.pdf',
        ]);
        $reward = Reward::factory()->create(['download_id' => $download->id]);
        RewardPurchase::factory()->create([
            'user_id' => $user->id,
            'reward_id' => $reward->id,
        ]);

        Storage::disk('private')->put('downloads/test.pdf', 'dummy content');

        $response = $this->get('/downloads/herunterladen/'.$download->id);

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    public function test_download_fails_when_reward_not_purchased(): void
    {
        $this->actingMember();

        $download = Download::factory()->create([
            'file_path' => 'downloads/test.pdf',
        ]);
        Reward::factory()->create(['download_id' => $download->id]);

        Storage::disk('private')->put('downloads/test.pdf', 'dummy content');

        $response = $this->from('/downloads')->get('/downloads/herunterladen/'.$download->id);

        $response->assertRedirect('/downloads');
        $response->assertSessionHasErrors();
    }

    public function test_download_available_without_linked_reward(): void
    {
        $this->actingMember();

        $download = Download::factory()->create([
            'file_path' => 'downloads/free.pdf',
            'original_filename' => 'Free.pdf',
        ]);
        // No reward linked - download should be freely available

        Storage::disk('private')->put('downloads/free.pdf', 'dummy content');

        $response = $this->get('/downloads/herunterladen/'.$download->id);

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    public function test_download_fails_when_file_missing(): void
    {
        $user = $this->actingMember();

        $download = Download::factory()->create([
            'file_path' => 'downloads/nonexistent.pdf',
        ]);
        $reward = Reward::factory()->create(['download_id' => $download->id]);
        RewardPurchase::factory()->create([
            'user_id' => $user->id,
            'reward_id' => $reward->id,
        ]);

        // File not in storage

        $response = $this->from('/downloads')->get('/downloads/herunterladen/'.$download->id);

        $response->assertRedirect('/downloads');
        $response->assertSessionHasErrors();
    }

    public function test_inactive_downloads_are_not_shown(): void
    {
        $this->actingMember();

        Download::factory()->create(['title' => 'Sichtbar', 'is_active' => true]);
        Download::factory()->create(['title' => 'Versteckt', 'is_active' => false]);

        $response = $this->get('/downloads');

        $response->assertOk();
        $response->assertSee('Sichtbar');
        $response->assertDontSee('Versteckt');
    }

    public function test_guest_is_redirected_to_login_when_accessing_downloads_page(): void
    {
        $this->get('/downloads')->assertRedirect('/login');
    }

    public function test_guest_is_redirected_to_login_when_downloading_file(): void
    {
        $download = Download::factory()->create();

        $this->get('/downloads/herunterladen/'.$download->id)->assertRedirect('/login');
    }
}
