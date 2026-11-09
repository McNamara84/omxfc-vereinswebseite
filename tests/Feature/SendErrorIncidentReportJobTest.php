<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Jobs\SendErrorIncidentReport;
use App\Mail\ErrorIncidentReport;
use App\Models\Team;
use App\Models\User;
use App\Services\ErrorReporting\ErrorReportRecipientResolver;
use App\Services\ErrorReporting\LaravelLogExcerptExtractor;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(SendErrorIncidentReportJobTestFixtures::class);

test('it sends a separate mail to every central admin', function (): void {
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
    $job->handle(app(ErrorReportRecipientResolver::class), app(LaravelLogExcerptExtractor::class));
    Mail::assertSent(ErrorIncidentReport::class, 2);
    Mail::assertSent(ErrorIncidentReport::class, fn (ErrorIncidentReport $mail): bool => $mail->hasTo('admin-a@example.com'));
    Mail::assertSent(ErrorIncidentReport::class, fn (ErrorIncidentReport $mail): bool => $mail->hasTo('admin-b@example.com'));
    Mail::assertNotSent(ErrorIncidentReport::class, fn (ErrorIncidentReport $mail): bool => $mail->hasTo('member@example.com'));
    expect(Context::get('error_notification_delivery'))->toBeTrue();
});

test('it does not send when no admin recipient exists', function (): void {
    Mail::fake();
    $this->removeSeededAdmins(Team::membersTeam());
    $job = new SendErrorIncidentReport($this->errorIncident());
    $job->handle(app(ErrorReportRecipientResolver::class), app(LaravelLogExcerptExtractor::class));
    Mail::assertNothingSent();
});

test('failed delivery is logged without starting another report', function (): void {
    Log::spy();
    $incident = $this->errorIncident();
    $job = new SendErrorIncidentReport($incident);
    $job->failed(new RuntimeException('SMTP nicht erreichbar'));
    expect(Context::get('error_notification_delivery'))->toBeTrue();
    Log::shouldHaveReceived('critical')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, 'Queue-Versuchen') && $context['incident_id'] === $incident->id && $context['exception_message'] === 'SMTP nicht erreichbar');
});

test('it exposes operational queue tags', function (): void {
    $incident = $this->errorIncident();
    expect((new SendErrorIncidentReport($incident))->tags())->toBe(['error-reporting', 'incident:'.$incident->id, 'fingerprint:'.$incident->fingerprint]);
});
