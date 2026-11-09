<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Mail\ErrorIncidentReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesErrorIncident;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesErrorIncident::class, RefreshDatabase::class);

test('it builds the subject content and text attachment', function (): void {
    $incident = $this->errorIncident(['suppressedOccurrences' => 4]);
    $mail = new ErrorIncidentReport($incident, 'bereinigter logauszug');
    expect($mail->envelope()->subject)->toContain('[PRODUCTION][Fehler] RuntimeException auf dashboard')
        ->and($mail->content()->markdown)->toBe('emails.errors.incident-report')
        ->and($mail->content()->with['incident'])->toBe($incident)
        ->and($mail)->not->toBeInstanceOf(ShouldQueue::class);
    $attachment = $mail->attachments()[0];
    $contents = $attachment->attachWith(fn (string $path): string => $path, fn (callable $data): string => $data());
    expect($attachment->as)->toBe('laravel-error-'.$incident->id.'.txt')
        ->and($attachment->mime)->toBe('text/plain')
        ->and($contents)->toBe('bereinigter logauszug');
});

test('rendered mail contains required debug information', function (): void {
    $incident = $this->errorIncident(['suppressedOccurrences' => 2]);
    $html = (new ErrorIncidentReport($incident, 'log'))->render();
    expect($html)->toContain($incident->id)
        ->toContain($incident->correlationId)
        ->toContain('Google Chrome')
        ->toContain('126.0.0.0')
        ->toContain('Rolle im Mitglieder-Team')
        ->toContain('2 identische Vorkommnisse');
});
