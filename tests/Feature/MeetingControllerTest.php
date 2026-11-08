<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

afterEach(function (): void {
    Carbon::setTestNow();
});

test('unknown meeting is forbidden', function (): void {
    $this->actingAs($this->actingMember());
    $response = $this->post('/treffen/umleiten', ['meeting' => 'unknown']);
    $response->assertForbidden();
    expect($response->exception)->toBeInstanceOf(HttpException::class)
        ->and($response->exception?->getMessage())->toBe('Unbekanntes Meeting');
});

test('malformed meeting payload is forbidden', function (): void {
    $this->actingAs($this->actingMember());
    $response = $this->post('/treffen/umleiten', ['meeting' => ['maddraxikon']]);
    $response->assertForbidden();
    expect($response->exception)->toBeInstanceOf(HttpException::class)
        ->and($response->exception?->getMessage())->toBe('Unbekanntes Meeting');
});

test('meetings page returns seeded meeting data', function (): void {
    Carbon::setTestNow('2026-05-14 12:00');
    Meeting::query()->where('slug', 'maddraxikon')->update(['zoom_url' => 'https://example.com/zoom']);
    $this->actingAs($this->actingMember());
    $response = $this->get('/treffen');
    $response->assertOk()->assertSeeText('AG Maddraxikon')->assertSee('value="maddraxikon"', false);
    $meetings = $response->viewData('meetings');
    expect($meetings)->toHaveCount(4)
        ->and($meetings->first()->title)->toBe('AG Maddraxikon')
        ->and($meetings->first()->display_rhythm)->toBe('Monatlich am 3. Montag')
        ->and($meetings->first()->next_occurrence->equalTo(Carbon::parse('2026-05-18 20:00')))->toBeTrue();
});

test('meetings page handles note only without date', function (): void {
    Carbon::setTestNow('2026-05-14 12:00');
    $this->actingAs($this->actingMember());
    $meetings = $this->get('/treffen')->viewData('meetings');
    $last = $meetings->last();
    expect($last->title)->toBe('CHATDRAX 2.0 - Der MADDRAX-Online-Stammtisch')
        ->and($last->display_rhythm)->toBe('Jeden zweiten Dienstag nach einem Roman')
        ->and($last->next_occurrence)->toBeNull();
});

test('meetings page computes dates for all meetings', function (): void {
    Carbon::setTestNow('2026-05-14 12:00');
    $this->actingAs($this->actingMember());
    $meetings = $this->get('/treffen')->viewData('meetings');
    expect($meetings[0]->next_occurrence->equalTo(Carbon::parse('2026-05-18 20:00')))->toBeTrue()
        ->and($meetings[1]->next_occurrence->equalTo(Carbon::parse('2026-06-10 19:00')))->toBeTrue()
        ->and($meetings[2]->next_occurrence->equalTo(Carbon::parse('2026-06-03 20:00')))->toBeTrue();
});

test('valid meeting redirects to configured zoom url', function (): void {
    Meeting::query()->where('slug', 'maddraxikon')->update(['zoom_url' => 'https://example.com/zoom']);
    $this->actingAs($this->actingMember());
    $this->post('/treffen/umleiten', ['meeting' => 'maddraxikon'])->assertRedirect('https://example.com/zoom');
});

test('valid meeting redirects to configured fallback zoom url', function (): void {
    config()->set('services.meetings.zoom_links.maddraxikon', 'https://example.com/fallback');
    Meeting::query()->where('slug', 'maddraxikon')->update(['zoom_url' => null]);
    $this->actingAs($this->actingMember());
    $this->post('/treffen/umleiten', ['meeting' => 'maddraxikon'])->assertRedirect('https://example.com/fallback');
});

test('meetings page uses configured fallback zoom url', function (): void {
    config()->set('services.meetings.zoom_links.maddraxikon', 'https://example.com/fallback');
    Carbon::setTestNow('2026-05-14 12:00');
    Meeting::query()->where('slug', 'maddraxikon')->update(['zoom_url' => null]);
    $this->actingAs($this->actingMember());
    $this->get('/treffen')->assertOk()->assertSee('value="maddraxikon"', false)->assertSeeText('Zoom-Meeting betreten');
});

test('meetings page hides inactive meetings', function (): void {
    Meeting::factory()->inactive()->create(['title' => 'Veraltetes Meeting', 'slug' => 'veraltetes-meeting']);
    $this->actingAs($this->actingMember());
    $this->get('/treffen')->assertOk()->assertDontSeeText('Veraltetes Meeting');
});

test('meeting without configured zoom url is forbidden', function (): void {
    Meeting::query()->where('slug', 'maddraxikon')->update(['zoom_url' => null]);
    $this->actingAs($this->actingMember());
    $response = $this->post('/treffen/umleiten', ['meeting' => 'maddraxikon']);
    $response->assertForbidden();
    expect($response->exception)->toBeInstanceOf(HttpException::class)
        ->and($response->exception?->getMessage())->toBe('Für dieses Treffen ist noch kein Zoom-Link hinterlegt.');
});
