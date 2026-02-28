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

        $response = $this->get('/downloads/herunterladen/'.$download->slug);

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

        $response = $this->from('/downloads')->get('/downloads/herunterladen/'.$download->slug);

        $response->assertRedirect('/downloads');
        $response->assertSessionHasErrors();
    }

    public function test_download_with_inactive_reward_shows_nicht_verfuegbar_message(): void
    {
        $this->actingMember();

        $download = Download::factory()->create([
            'file_path' => 'downloads/locked.pdf',
        ]);
        Reward::factory()->create([
            'download_id' => $download->id,
            'is_active' => false,
        ]);

        Storage::disk('private')->put('downloads/locked.pdf', 'dummy content');

        $response = $this->from('/downloads')->get('/downloads/herunterladen/'.$download->slug);

        $response->assertRedirect('/downloads');
        $response->assertSessionHas('errors');
        $this->assertStringContainsString(
            'nicht verfügbar',
            $response->getSession()->get('errors')->first()
        );
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

        $response = $this->get('/downloads/herunterladen/'.$download->slug);

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

        $response = $this->from('/downloads')->get('/downloads/herunterladen/'.$download->slug);

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

    public function test_download_of_inactive_download_returns_404(): void
    {
        $this->actingMember();

        $download = Download::factory()->create([
            'file_path' => 'downloads/inactive.pdf',
            'is_active' => false,
        ]);

        Storage::disk('private')->put('downloads/inactive.pdf', 'dummy content');

        $response = $this->get('/downloads/herunterladen/'.$download->slug);

        $response->assertNotFound();
    }

    public function test_refunded_purchase_does_not_grant_download_access(): void
    {
        $user = $this->actingMember();

        $download = Download::factory()->create([
            'file_path' => 'downloads/refunded.pdf',
            'original_filename' => 'Refunded.pdf',
        ]);
        $reward = Reward::factory()->create(['download_id' => $download->id]);

        // Purchase wurde erstattet
        RewardPurchase::factory()->refunded()->create([
            'user_id' => $user->id,
            'reward_id' => $reward->id,
        ]);

        Storage::disk('private')->put('downloads/refunded.pdf', 'dummy content');

        $response = $this->from('/downloads')->get('/downloads/herunterladen/'.$download->slug);

        $response->assertRedirect('/downloads');
        $response->assertSessionHasErrors();
    }

    public function test_refunded_purchase_does_not_show_download_as_unlocked(): void
    {
        $user = $this->actingMember();

        $download = Download::factory()->create([
            'title' => 'Erstatteter Download',
            'file_path' => 'downloads/refunded-index.pdf',
        ]);
        $reward = Reward::factory()->create(['download_id' => $download->id]);

        // Purchase wurde erstattet
        RewardPurchase::factory()->refunded()->create([
            'user_id' => $user->id,
            'reward_id' => $reward->id,
        ]);

        $response = $this->get('/downloads');

        $response->assertOk();
        // Der Download sollte als gesperrt angezeigt werden (Freischalten-Link statt Herunterladen)
        $response->assertSee('Freischalten');
        $response->assertDontSee('Herunterladen');
    }

    public function test_active_purchase_with_inactive_reward_does_not_show_as_unlocked(): void
    {
        $user = $this->actingMember();

        $download = Download::factory()->create([
            'title' => 'Deaktivierter Reward Download',
            'file_path' => 'downloads/inactive-reward.pdf',
        ]);
        $reward = Reward::factory()->create([
            'download_id' => $download->id,
            'is_active' => false,
        ]);

        // Aktive Purchase existiert, aber Reward ist deaktiviert
        RewardPurchase::factory()->create([
            'user_id' => $user->id,
            'reward_id' => $reward->id,
        ]);

        $response = $this->get('/downloads');

        $response->assertOk();
        // Trotz aktiver Purchase sollte "Nicht verfügbar" statt "Herunterladen" angezeigt werden
        $response->assertSee('Nicht verfügbar');
        $response->assertSee('Deaktivierter Reward Download');
    }

    public function test_inactive_reward_shows_nicht_verfuegbar_instead_of_freischalten(): void
    {
        $user = $this->actingMember();

        $download = Download::factory()->create([
            'title' => 'Gesperrter Download',
            'file_path' => 'downloads/locked.pdf',
        ]);
        Reward::factory()->create([
            'download_id' => $download->id,
            'is_active' => false,
        ]);

        $response = $this->get('/downloads');

        $response->assertOk();
        $response->assertSee('Nicht verfügbar');
        // Der spezifische Download darf nicht als "Herunterladen" erscheinen
        $response->assertSee('Gesperrter Download');
    }

    public function test_guest_is_redirected_to_login_when_accessing_downloads_page(): void
    {
        $this->get('/downloads')->assertRedirect('/login');
    }

    public function test_guest_is_redirected_to_login_when_downloading_file(): void
    {
        $download = Download::factory()->create();

        $this->get('/downloads/herunterladen/'.$download->slug)->assertRedirect('/login');
    }
}
