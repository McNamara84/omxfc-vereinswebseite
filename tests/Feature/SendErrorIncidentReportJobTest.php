<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Jobs\SendErrorIncidentReport;
use App\Mail\ErrorIncidentReport;
use App\Models\Team;
use App\Models\User;
use App\Services\ErrorReporting\ErrorReportRecipientResolver;
use App\Services\ErrorReporting\LaravelLogExcerptExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\Concerns\CreatesErrorIncident;
use Tests\TestCase;

class SendErrorIncidentReportJobTest extends TestCase
{
    use CreatesErrorIncident;
    use RefreshDatabase;

    public function test_it_sends_a_separate_mail_to_every_central_admin(): void
    {
        Mail::fake();
        $team = Team::membersTeam();
        $this->removeSeededAdmins($team);
        $adminA = User::factory()->create(['email' => 'admin-a@example.com']);
        $adminB = User::factory()->create(['email' => 'admin-b@example.com']);
        $member = User::factory()->create(['email' => 'member@example.com']);
        $team->users()->attach($adminA, ['role' => Role::Admin->value]);
        $team->users()->attach($adminB, ['role' => Role::Admin->value]);
        $team->users()->attach($member, ['role' => Role::Mitglied->value]);
        config(['error-reporting.log_path' => '/missing/laravel.log']);

        $job = new SendErrorIncidentReport($this->errorIncident());
        $job->handle(
            app(ErrorReportRecipientResolver::class),
            app(LaravelLogExcerptExtractor::class),
        );

        Mail::assertSent(ErrorIncidentReport::class, 2);
        Mail::assertSent(ErrorIncidentReport::class, fn (ErrorIncidentReport $mail): bool => $mail->hasTo('admin-a@example.com'));
        Mail::assertSent(ErrorIncidentReport::class, fn (ErrorIncidentReport $mail): bool => $mail->hasTo('admin-b@example.com'));
        Mail::assertNotSent(ErrorIncidentReport::class, fn (ErrorIncidentReport $mail): bool => $mail->hasTo('member@example.com'));
        $this->assertTrue(Context::get('error_notification_delivery'));
    }

    public function test_it_does_not_send_when_no_admin_recipient_exists(): void
    {
        Mail::fake();
        $this->removeSeededAdmins(Team::membersTeam());

        $job = new SendErrorIncidentReport($this->errorIncident());
        $job->handle(
            app(ErrorReportRecipientResolver::class),
            app(LaravelLogExcerptExtractor::class),
        );

        Mail::assertNothingSent();
    }

    public function test_failed_delivery_is_logged_without_starting_another_report(): void
    {
        Log::spy();
        $incident = $this->errorIncident();
        $job = new SendErrorIncidentReport($incident);

        $job->failed(new RuntimeException('SMTP nicht erreichbar'));

        $this->assertTrue(Context::get('error_notification_delivery'));
        Log::shouldHaveReceived('critical')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'Queue-Versuchen')
                && $context['incident_id'] === $incident->id
                && $context['exception_message'] === 'SMTP nicht erreichbar');
    }

    public function test_it_exposes_operational_queue_tags(): void
    {
        $incident = $this->errorIncident();

        $this->assertSame([
            'error-reporting',
            'incident:'.$incident->id,
            'fingerprint:'.$incident->fingerprint,
        ], (new SendErrorIncidentReport($incident))->tags());
    }

    private function removeSeededAdmins(Team $team): void
    {
        DB::table('team_user')
            ->where('team_id', $team->id)
            ->where('role', Role::Admin->value)
            ->delete();
    }
}
