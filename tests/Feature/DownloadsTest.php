<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Download;
use App\Models\Reward;
use App\Models\RewardPurchase;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('private');
});

afterEach(function (): void {
    Mockery::close();
});

test('index shows downloads grouped by category', function (): void {
    $this->actingMember();
    Download::factory()->create(['title' => 'Bauanleitung A', 'category' => 'Klemmbaustein-Anleitungen']);
    Download::factory()->create(['title' => 'Story B', 'category' => 'Fanstories']);
    $response = $this->get('/downloads');
    $response->assertOk();
    $response->assertSee('Klemmbaustein-Anleitungen');
    $response->assertSee('Fanstories');
    $response->assertSee('Bauanleitung A');
    $response->assertSee('Story B');
});

test('download succeeds when reward is purchased', function (): void {
    $user = $this->actingMember();
    $download = Download::factory()->create(['file_path' => 'downloads/test.pdf', 'original_filename' => 'Test.pdf']);
    $reward = Reward::factory()->create(['download_id' => $download->id]);
    RewardPurchase::factory()->create(['user_id' => $user->id, 'reward_id' => $reward->id]);
    Storage::disk('private')->put('downloads/test.pdf', 'dummy content');
    $response = $this->get('/downloads/herunterladen/'.$download->slug);
    $response->assertOk();
    $response->assertHeader('content-disposition');
});

test('download fails when reward not purchased', function (): void {
    $this->actingMember();
    $download = Download::factory()->create(['file_path' => 'downloads/test.pdf']);
    Reward::factory()->create(['download_id' => $download->id]);
    Storage::disk('private')->put('downloads/test.pdf', 'dummy content');
    $response = $this->from('/downloads')->get('/downloads/herunterladen/'.$download->slug);
    $response->assertRedirect('/downloads');
    $response->assertSessionHasErrors();
    expect($response->getSession()->get('errors')->first())->toContain('im Bereich Belohnungen einlösen');
});

test('downloads page points locked files to rewards redeem flow', function (): void {
    $this->actingMember();
    $download = Download::factory()->create(['title' => 'Belohnter Download', 'category' => 'Spezialinhalte']);
    Reward::factory()->create(['download_id' => $download->id, 'is_active' => true]);
    $response = $this->get('/downloads');
    $response->assertOk();
    $response->assertSee('Belohnungen einlösen');
    $response->assertSee('Belohnung nötig');
});

test('download with inactive reward shows nicht verfuegbar message', function (): void {
    $this->actingMember();
    $download = Download::factory()->create(['file_path' => 'downloads/locked.pdf']);
    Reward::factory()->create(['download_id' => $download->id, 'is_active' => false]);
    Storage::disk('private')->put('downloads/locked.pdf', 'dummy content');
    $response = $this->from('/downloads')->get('/downloads/herunterladen/'.$download->slug);
    $response->assertRedirect('/downloads');
    $response->assertSessionHas('errors');
    expect($response->getSession()->get('errors')->first())->toContain('nicht verfügbar');
});

test('download available without linked reward', function (): void {
    $this->actingMember();
    $download = Download::factory()->create(['file_path' => 'downloads/free.pdf', 'original_filename' => 'Free.pdf']);
    // No reward linked - download should be freely available
    Storage::disk('private')->put('downloads/free.pdf', 'dummy content');
    $response = $this->get('/downloads/herunterladen/'.$download->slug);
    $response->assertOk();
    $response->assertHeader('content-disposition');
});

test('seeded rulebooks are listed in the download area', function (): void {
    $this->actingMember();
    $response = $this->get('/downloads');
    $response->assertOk();
    $response->assertSeeText('Rollenspiel-Regelwerke');
    $response->assertSeeText('Rollenspiel-Regelwerk 2001');
    $response->assertSeeText('Rollenspiel-Regelwerk 2007');
});

test('bundled rulebook download is restored when private file is missing', function (): void {
    $this->actingMember();
    $download = Download::query()->where('slug', 'rollenspiel-regelwerk-2001')->firstOrFail();
    expect(Storage::disk('private')->exists($download->file_path))->toBeFalse();
    $response = $this->get('/downloads/herunterladen/'.$download->slug);
    $response->assertOk();
    $response->assertHeader('content-disposition');
    expect(Storage::disk('private')->exists($download->file_path))->toBeTrue();
});

test('bundled rulebook restoration uses file path not original filename', function (): void {
    $this->actingMember();
    $download = Download::query()->where('slug', 'rollenspiel-regelwerk-2001')->firstOrFail();
    $download->update(['original_filename' => 'Regelwerk-von-Uwe-Simon.pdf']);
    expect(Storage::disk('private')->exists($download->file_path))->toBeFalse();
    $response = $this->get('/downloads/herunterladen/'.$download->slug);
    $response->assertOk();
    $response->assertHeader('content-disposition');
    expect(Storage::disk('private')->exists($download->file_path))->toBeTrue();
});

test('bundled rulebook restoration rejects traversal paths', function (): void {
    $this->actingMember();
    $download = Download::query()->where('slug', 'rollenspiel-regelwerk-2001')->firstOrFail();
    $download->update(['file_path' => 'downloads/../views/pages/downloads.blade.php', 'original_filename' => 'downloads.blade.php']);
    $response = $this->from('/downloads')->get('/downloads/herunterladen/'.$download->slug);
    $response->assertRedirect('/downloads');
    $response->assertSessionHasErrors();
    expect($response->getSession()->get('errors')->first())->toContain('Die Datei existiert nicht.');
});

test('bundled rulebook restore falls back to missing file message when private write fails', function (): void {
    $this->actingMember();
    Exceptions::fake();
    $download = Download::query()->where('slug', 'rollenspiel-regelwerk-2001')->firstOrFail();
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('exists')->with($download->file_path)->twice()->andReturn(false, false);
    $disk->shouldReceive('put')->once()->with($download->file_path, Mockery::on(static fn ($stream) => is_resource($stream)))->andThrow(new \RuntimeException('Disk voll'));
    Storage::shouldReceive('disk')->with('private')->times(3)->andReturn($disk);
    $response = $this->from('/downloads')->get('/downloads/herunterladen/'.$download->slug);
    $response->assertRedirect('/downloads');
    $response->assertSessionHasErrors();
    expect($response->getSession()->get('errors')->first())->toContain('Die Datei existiert nicht.');
    Exceptions::assertReported(static fn (\RuntimeException $exception) => $exception->getMessage() === 'Disk voll');
});

test('download fails when file missing', function (): void {
    $user = $this->actingMember();
    $download = Download::factory()->create(['file_path' => 'downloads/nonexistent.pdf']);
    $reward = Reward::factory()->create(['download_id' => $download->id]);
    RewardPurchase::factory()->create(['user_id' => $user->id, 'reward_id' => $reward->id]);
    // File not in storage
    $response = $this->from('/downloads')->get('/downloads/herunterladen/'.$download->slug);
    $response->assertRedirect('/downloads');
    $response->assertSessionHasErrors();
});

test('inactive downloads are not shown', function (): void {
    $this->actingMember();
    Download::factory()->create(['title' => 'Sichtbar', 'is_active' => true]);
    Download::factory()->create(['title' => 'Versteckt', 'is_active' => false]);
    $response = $this->get('/downloads');
    $response->assertOk();
    $response->assertSee('Sichtbar');
    $response->assertDontSee('Versteckt');
});

test('download of inactive download returns 404', function (): void {
    $this->actingMember();
    $download = Download::factory()->create(['file_path' => 'downloads/inactive.pdf', 'is_active' => false]);
    Storage::disk('private')->put('downloads/inactive.pdf', 'dummy content');
    $response = $this->get('/downloads/herunterladen/'.$download->slug);
    $response->assertNotFound();
});

test('refunded purchase does not grant download access', function (): void {
    $user = $this->actingMember();
    $download = Download::factory()->create(['file_path' => 'downloads/refunded.pdf', 'original_filename' => 'Refunded.pdf']);
    $reward = Reward::factory()->create(['download_id' => $download->id]);
    // Purchase wurde erstattet
    RewardPurchase::factory()->refunded()->create(['user_id' => $user->id, 'reward_id' => $reward->id]);
    Storage::disk('private')->put('downloads/refunded.pdf', 'dummy content');
    $response = $this->from('/downloads')->get('/downloads/herunterladen/'.$download->slug);
    $response->assertRedirect('/downloads');
    $response->assertSessionHasErrors();
});

test('refunded purchase does not show download as unlocked', function (): void {
    $user = $this->actingMember();
    $download = Download::factory()->create(['title' => 'Erstatteter Download', 'file_path' => 'downloads/refunded-index.pdf']);
    $reward = Reward::factory()->create(['download_id' => $download->id]);
    // Purchase wurde erstattet
    RewardPurchase::factory()->refunded()->create(['user_id' => $user->id, 'reward_id' => $reward->id]);
    $response = $this->get('/downloads');
    $response->assertOk();
    // Der Download sollte als gesperrt angezeigt werden (Freischalten-Link statt Herunterladen)
    $response->assertSee('Freischalten');
    $response->assertDontSee(route('downloads.download', $download), false);
});

test('active purchase with inactive reward does not show as unlocked', function (): void {
    $user = $this->actingMember();
    $download = Download::factory()->create(['title' => 'Deaktivierter Reward Download', 'file_path' => 'downloads/inactive-reward.pdf']);
    $reward = Reward::factory()->create(['download_id' => $download->id, 'is_active' => false]);
    // Aktive Purchase existiert, aber Reward ist deaktiviert
    RewardPurchase::factory()->create(['user_id' => $user->id, 'reward_id' => $reward->id]);
    $response = $this->get('/downloads');
    $response->assertOk();
    // Trotz aktiver Purchase sollte "Nicht verfügbar" statt "Herunterladen" angezeigt werden
    $response->assertSee('Nicht verfügbar');
    $response->assertSee('Deaktivierter Reward Download');
});

test('inactive reward shows nicht verfuegbar instead of freischalten', function (): void {
    $user = $this->actingMember();
    $download = Download::factory()->create(['title' => 'Gesperrter Download', 'file_path' => 'downloads/locked.pdf']);
    Reward::factory()->create(['download_id' => $download->id, 'is_active' => false]);
    $response = $this->get('/downloads');
    $response->assertOk();
    $response->assertSee('Nicht verfügbar');
    // Der spezifische Download darf nicht als "Herunterladen" erscheinen
    $response->assertSee('Gesperrter Download');
});

test('guest is redirected to login when accessing downloads page', function (): void {
    $this->get('/downloads')->assertRedirect('/login');
});

test('guest is redirected to login when downloading file', function (): void {
    $download = Download::factory()->create();
    $this->get('/downloads/herunterladen/'.$download->slug)->assertRedirect('/login');
});
