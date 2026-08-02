<?php

namespace Tests\Unit;

use App\Mail\ErrorIncidentReport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesErrorIncident;
use Tests\TestCase;

class ErrorIncidentReportMailTest extends TestCase
{
    use CreatesErrorIncident;
    use RefreshDatabase;

    public function test_it_builds_the_subject_content_and_text_attachment(): void
    {
        $incident = $this->errorIncident(['suppressedOccurrences' => 4]);
        $mail = new ErrorIncidentReport($incident, 'bereinigter logauszug');

        $this->assertStringContainsString('[PRODUCTION][Fehler] RuntimeException auf dashboard', $mail->envelope()->subject);
        $this->assertSame('emails.errors.incident-report', $mail->content()->markdown);
        $this->assertSame($incident, $mail->content()->with['incident']);
        $this->assertNotInstanceOf(ShouldQueue::class, $mail);

        $attachment = $mail->attachments()[0];
        $contents = $attachment->attachWith(
            fn (string $path): string => $path,
            fn (callable $data): string => $data(),
        );

        $this->assertSame('laravel-error-'.$incident->id.'.txt', $attachment->as);
        $this->assertSame('text/plain', $attachment->mime);
        $this->assertSame('bereinigter logauszug', $contents);
    }

    public function test_rendered_mail_contains_required_debug_information(): void
    {
        $incident = $this->errorIncident(['suppressedOccurrences' => 2]);

        $html = (new ErrorIncidentReport($incident, 'log'))->render();

        $this->assertStringContainsString($incident->id, $html);
        $this->assertStringContainsString($incident->correlationId, $html);
        $this->assertStringContainsString('Google Chrome', $html);
        $this->assertStringContainsString('126.0.0.0', $html);
        $this->assertStringContainsString('Rolle im Mitglieder-Team', $html);
        $this->assertStringContainsString('2 identische Vorkommnisse', $html);
    }
}
