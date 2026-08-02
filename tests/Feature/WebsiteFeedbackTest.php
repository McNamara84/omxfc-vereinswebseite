<?php

namespace Tests\Feature;

use App\Data\WebsiteFeedback\WebsiteFeedbackData;
use App\Enums\Role;
use App\Livewire\WebsiteFeedback;
use App\Models\Team;
use App\Models\User;
use App\Services\WebsiteFeedback\FeedbackDeliveryService;
use App\Services\WebsiteFeedback\FeedbackSessionService;
use App\Services\WebsiteFeedback\FeedbackSubmissionThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class WebsiteFeedbackTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'http://localhost',
            'feedback.session_interval' => 1,
            'feedback.message_min_length' => 10,
            'feedback.message_max_length' => 5000,
            'feedback.rate_limit_per_hour' => 5,
        ]);
    }

    public function test_global_button_is_rendered_for_members_but_not_for_guests_or_applicants(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('website-feedback-trigger');

        $applicant = $this->createUserWithRole(Role::Anwaerter);
        $this->actingAs($applicant)
            ->get('/')
            ->assertOk()
            ->assertDontSee('website-feedback-trigger');

        $this->flushSession();
        $member = $this->createUserWithRole(Role::Mitglied);
        $this->actingAs($member)
            ->get('/')
            ->assertOk()
            ->assertSee('website-feedback-trigger');
    }

    public function test_named_feedback_is_queued_separately_for_admin_and_vorstand_and_hides_the_button(): void
    {
        $this->allowFeedbackSession(expectSubmitted: true);
        $this->removeSeededManagement();
        $admin = $this->createUserWithRole(Role::Admin);
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $kassenwart = $this->createUserWithRole(Role::Kassenwart);
        $sender = $this->actingMember(Role::Mitglied, [
            'name' => 'Mara Mitglied',
            'email' => 'mara@example.com',
        ]);
        $delivery = Mockery::mock(FeedbackDeliveryService::class);
        $delivery->shouldReceive('queue')
            ->once()
            ->withArgs(function (WebsiteFeedbackData $feedback, array $recipients) use ($admin, $vorstand, $kassenwart, $sender): bool {
                return $feedback->reporterName === 'Mara Mitglied'
                    && $feedback->reporterEmail === $sender->email
                    && $feedback->pageUrl === 'http://localhost/dashboard'
                    && ! in_array($kassenwart->email, $recipients, true)
                    && collect($recipients)->sort()->values()->all()
                        === collect([$admin->email, $vorstand->email])->sort()->values()->all();
            });
        $this->app->instance(FeedbackDeliveryService::class, $delivery);

        $component = Livewire::test(WebsiteFeedback::class)
            ->call('openFeedback', 'http://localhost/dashboard#karten', 'Dashboard')
            ->set('category', 'idea')
            ->set('message', 'Die Navigation könnte auf Mobilgeräten klarer sein.');

        $component->call('submit')
            ->assertSet('sent', true)
            ->assertSet('showModal', false)
            ->assertDontSee('website-feedback-trigger');

        $component->assertHasNoErrors();
    }

    public function test_anonymous_feedback_queue_payload_contains_no_sender_identity(): void
    {
        $this->allowFeedbackSession(expectSubmitted: true);
        $this->removeSeededManagement();
        $admin = $this->createUserWithRole(Role::Admin);
        $sender = $this->actingMember(Role::Mitglied, [
            'name' => 'Geheime Person',
            'email' => 'secret@example.com',
        ]);
        $delivery = Mockery::mock(FeedbackDeliveryService::class);
        $delivery->shouldReceive('queue')
            ->once()
            ->withArgs(function (WebsiteFeedbackData $feedback, array $recipients) use ($admin, $sender): bool {
                $serializedFeedback = serialize($feedback);

                return $recipients === [$admin->email]
                    && $feedback->isAnonymous()
                    && $feedback->reporterName === null
                    && $feedback->reporterEmail === null
                    && ! str_contains($serializedFeedback, $sender->email)
                    && ! str_contains($serializedFeedback, $sender->name)
                    && ! array_key_exists('userId', get_object_vars($feedback))
                    && ! array_key_exists('reporterId', get_object_vars($feedback));
            });
        $this->app->instance(FeedbackDeliveryService::class, $delivery);

        $component = Livewire::test(WebsiteFeedback::class)
            ->call('openFeedback', 'http://localhost/chronik', 'Chronik')
            ->set('category', 'problem')
            ->set('message', 'Auf der Chronikseite ist ein Inhalt schwer lesbar.')
            ->set('anonymous', true);

        $component->call('submit')
            ->assertHasNoErrors();
    }

    public function test_form_validates_category_and_message(): void
    {
        $this->allowFeedbackSession();
        $this->mockDeliveryWithoutCalls();
        $this->actingMember();

        Livewire::test(WebsiteFeedback::class)
            ->call('openFeedback', 'http://localhost/dashboard', 'Dashboard')
            ->set('category', 'invalid')
            ->set('message', 'kurz')
            ->call('submit')
            ->assertHasErrors(['category', 'message']);

    }

    public function test_opening_feedback_resets_a_previous_draft_before_updating_the_page_context(): void
    {
        $this->allowFeedbackSession();
        $this->actingMember();

        Livewire::test(WebsiteFeedback::class)
            ->call('openFeedback', 'http://localhost/chronik', 'Chronik')
            ->set('category', 'idea')
            ->set('message', 'Dieser Entwurf gehört zur Chronikseite.')
            ->set('anonymous', true)
            ->call('closeFeedback')
            ->call('openFeedback', 'http://localhost/dashboard', 'Dashboard')
            ->assertSet('category', '')
            ->assertSet('message', '')
            ->assertSet('anonymous', false)
            ->assertSet('pageUrl', 'http://localhost/dashboard')
            ->assertSet('pageTitle', 'Dashboard')
            ->assertSet('showModal', true);
    }

    public function test_external_feedback_page_url_is_rejected(): void
    {
        $this->allowFeedbackSession();
        $this->actingMember();

        Livewire::test(WebsiteFeedback::class)
            ->call('openFeedback', 'https://evil.example/phishing', 'Fremde Seite')
            ->assertStatus(422);
    }

    public function test_no_recipient_keeps_the_feature_available_and_does_not_queue_mail(): void
    {
        $this->allowFeedbackSession();
        $this->mockDeliveryWithoutCalls();
        $this->removeSeededManagement();
        $this->actingMember();

        Livewire::test(WebsiteFeedback::class)
            ->call('openFeedback', 'http://localhost/dashboard', 'Dashboard')
            ->set('category', 'other')
            ->set('message', 'Dies ist eine ausreichend lange Rückmeldung.')
            ->call('submit')
            ->assertHasErrors(['delivery'])
            ->assertSet('sent', false)
            ->assertSee('website-feedback-trigger');

    }

    public function test_rate_limit_blocks_queueing_without_consuming_the_session(): void
    {
        $sender = $this->actingMember();
        $this->allowFeedbackSession();
        $this->mockDeliveryWithoutCalls();
        config(['feedback.rate_limit_per_hour' => 1]);
        $throttle = Mockery::mock(FeedbackSubmissionThrottle::class);
        $throttle->shouldReceive('tooManyAttempts')
            ->once()
            ->withArgs(fn (User $user): bool => $user->is($sender))
            ->andReturnTrue();
        $throttle->shouldNotReceive('record');
        $this->app->instance(FeedbackSubmissionThrottle::class, $throttle);

        $component = Livewire::test(WebsiteFeedback::class)
            ->call('openFeedback', 'http://localhost/dashboard', 'Dashboard')
            ->set('category', 'praise')
            ->set('message', 'Die neue Vereinswebsite gefällt mir wirklich gut.');

        $component->call('submit')
            ->assertHasErrors(['delivery'])
            ->assertSet('sent', false);

    }

    public function test_already_submitted_session_cannot_invoke_the_action_again(): void
    {
        $this->allowFeedbackSession(available: false);
        $this->actingMember();

        Livewire::test(WebsiteFeedback::class)
            ->call('submit')
            ->assertStatus(403);
    }

    private function allowFeedbackSession(bool $available = true, bool $expectSubmitted = false): void
    {
        $sessions = Mockery::mock(FeedbackSessionService::class);
        $sessions->shouldReceive('register')->zeroOrMoreTimes();
        $sessions->shouldReceive('isAvailable')->zeroOrMoreTimes()->andReturn($available);

        if ($expectSubmitted) {
            $sessions->shouldReceive('markSubmitted')->once();
        } else {
            $sessions->shouldNotReceive('markSubmitted');
        }

        $this->app->instance(FeedbackSessionService::class, $sessions);
    }

    private function mockDeliveryWithoutCalls(): void
    {
        $delivery = Mockery::mock(FeedbackDeliveryService::class);
        $delivery->shouldNotReceive('queue');
        $this->app->instance(FeedbackDeliveryService::class, $delivery);
    }

    private function removeSeededManagement(): void
    {
        DB::table('team_user')
            ->where('team_id', Team::membersTeam()->id)
            ->whereIn('role', [Role::Admin->value, Role::Vorstand->value])
            ->delete();
    }
}
