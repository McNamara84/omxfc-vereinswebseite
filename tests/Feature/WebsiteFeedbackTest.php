<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Data\WebsiteFeedback\WebsiteFeedbackData;
use App\Enums\Role;
use App\Livewire\WebsiteFeedback;
use App\Models\User;
use App\Services\WebsiteFeedback\FeedbackDeliveryService;
use App\Services\WebsiteFeedback\FeedbackSubmissionThrottle;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(WebsiteFeedbackTestFixtures::class);

beforeEach(function (): void {
    config(['app.url' => 'http://localhost', 'feedback.session_interval' => 1, 'feedback.message_min_length' => 10, 'feedback.message_max_length' => 5000, 'feedback.rate_limit_per_hour' => 5]);
});

test('global button is rendered for members but not for guests or applicants', function (): void {
    $this->get('/')->assertOk()->assertDontSee('website-feedback-trigger');
    $applicant = $this->createUserWithRole(Role::Anwaerter);
    $this->actingAs($applicant)->get('/')->assertOk()->assertDontSee('website-feedback-trigger');
    $this->flushSession();
    $member = $this->createUserWithRole(Role::Mitglied);
    $this->actingAs($member)->get('/dashboard')->assertOk()->assertSee('website-feedback-trigger');
});

test('named feedback is queued separately for admin and vorstand and hides the button', function (): void {
    $this->allowFeedbackSession(expectSubmitted: true);
    $this->removeSeededManagement();
    $admin = $this->createUserWithRole(Role::Admin);
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $kassenwart = $this->createUserWithRole(Role::Kassenwart);
    $sender = $this->actingMember(Role::Mitglied, ['name' => 'Mara Mitglied', 'email' => 'mara@example.com']);
    $delivery = Mockery::mock(FeedbackDeliveryService::class);
    $delivery->shouldReceive('queue')->once()->withArgs(fn (WebsiteFeedbackData $feedback, array $recipients): bool => $feedback->reporterName === 'Mara Mitglied' && $feedback->reporterEmail === $sender->email && $feedback->pageUrl === 'http://localhost/dashboard' && ! in_array($kassenwart->email, $recipients, true) && collect($recipients)->sort()->values()->all() === collect([$admin->email, $vorstand->email])->sort()->values()->all());
    $this->app->instance(FeedbackDeliveryService::class, $delivery);
    $component = Livewire::test(WebsiteFeedback::class)->call('openFeedback', 'http://localhost/dashboard#karten', 'Dashboard')->set('category', 'idea')->set('message', 'Die Navigation könnte auf Mobilgeräten klarer sein.');
    $component->call('submit')->assertSet('sent', true)->assertSet('showModal', false)->assertDontSee('website-feedback-trigger');
    $component->assertHasNoErrors();
});

test('anonymous feedback queue payload contains no sender identity', function (): void {
    $this->allowFeedbackSession(expectSubmitted: true);
    $this->removeSeededManagement();
    $admin = $this->createUserWithRole(Role::Admin);
    $sender = $this->actingMember(Role::Mitglied, ['name' => 'Geheime Person', 'email' => 'secret@example.com']);
    $delivery = Mockery::mock(FeedbackDeliveryService::class);
    $delivery->shouldReceive('queue')->once()->withArgs(function (WebsiteFeedbackData $feedback, array $recipients) use ($admin, $sender): bool {
        $serializedFeedback = serialize($feedback);

        return $recipients === [$admin->email] && $feedback->isAnonymous() && $feedback->reporterName === null && $feedback->reporterEmail === null && ! str_contains($serializedFeedback, $sender->email) && ! str_contains($serializedFeedback, $sender->name) && ! array_key_exists('userId', get_object_vars($feedback)) && ! array_key_exists('reporterId', get_object_vars($feedback));
    });
    $this->app->instance(FeedbackDeliveryService::class, $delivery);
    $component = Livewire::test(WebsiteFeedback::class)->call('openFeedback', 'http://localhost/chronik', 'Chronik')->set('category', 'problem')->set('message', 'Auf der Chronikseite ist ein Inhalt schwer lesbar.')->set('anonymous', true);
    $component->call('submit')->assertHasNoErrors();
});

test('form validates category and message', function (): void {
    $this->allowFeedbackSession();
    $this->mockDeliveryWithoutCalls();
    $this->actingMember();
    Livewire::test(WebsiteFeedback::class)->call('openFeedback', 'http://localhost/dashboard', 'Dashboard')->set('category', 'invalid')->set('message', 'kurz')->call('submit')->assertHasErrors(['category', 'message']);
});

test('opening feedback resets a previous draft before updating the page context', function (): void {
    $this->allowFeedbackSession();
    $this->actingMember();
    Livewire::test(WebsiteFeedback::class)->call('openFeedback', 'http://localhost/chronik', 'Chronik')->set('category', 'idea')->set('message', 'Dieser Entwurf gehört zur Chronikseite.')->set('anonymous', true)->call('closeFeedback')->call('openFeedback', 'http://localhost/dashboard', 'Dashboard')->assertSet('category', '')->assertSet('message', '')->assertSet('anonymous', false)->assertSet('pageUrl', 'http://localhost/dashboard')->assertSet('pageTitle', 'Dashboard')->assertSet('showModal', true);
});

test('external feedback page url is rejected', function (): void {
    $this->allowFeedbackSession();
    $this->actingMember();
    Livewire::test(WebsiteFeedback::class)->call('openFeedback', 'https://evil.example/phishing', 'Fremde Seite')->assertStatus(422);
});

test('no recipient keeps the feature available and does not queue mail', function (): void {
    $this->allowFeedbackSession();
    $this->mockDeliveryWithoutCalls();
    $this->removeSeededManagement();
    $this->actingMember();
    Livewire::test(WebsiteFeedback::class)->call('openFeedback', 'http://localhost/dashboard', 'Dashboard')->set('category', 'other')->set('message', 'Dies ist eine ausreichend lange Rückmeldung.')->call('submit')->assertHasErrors(['delivery'])->assertSet('sent', false)->assertSee('website-feedback-trigger');
});

test('rate limit blocks queueing without consuming the session', function (): void {
    $sender = $this->actingMember();
    $this->allowFeedbackSession();
    $this->mockDeliveryWithoutCalls();
    config(['feedback.rate_limit_per_hour' => 1]);
    $throttle = Mockery::mock(FeedbackSubmissionThrottle::class);
    $throttle->shouldReceive('tooManyAttempts')->once()->withArgs(fn (User $user): bool => $user->is($sender))->andReturnTrue();
    $throttle->shouldNotReceive('record');
    $this->app->instance(FeedbackSubmissionThrottle::class, $throttle);
    $component = Livewire::test(WebsiteFeedback::class)->call('openFeedback', 'http://localhost/dashboard', 'Dashboard')->set('category', 'praise')->set('message', 'Die neue Vereinswebsite gefällt mir wirklich gut.');
    $component->call('submit')->assertHasErrors(['delivery'])->assertSet('sent', false);
});

test('already submitted session cannot invoke the action again', function (): void {
    $this->allowFeedbackSession(available: false);
    $this->actingMember();
    Livewire::test(WebsiteFeedback::class)->call('submit')->assertStatus(403);
});
