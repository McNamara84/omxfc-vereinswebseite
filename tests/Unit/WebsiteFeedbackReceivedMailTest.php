<?php

namespace Tests\Unit;

use App\Data\WebsiteFeedback\WebsiteFeedbackData;
use App\Enums\WebsiteFeedbackCategory;
use App\Mail\WebsiteFeedbackReceived;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteFeedbackReceivedMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_named_feedback_builds_subject_content_and_reply_to(): void
    {
        $data = $this->feedback(
            reporterName: 'Mara Mitglied',
            reporterEmail: 'mara@example.com',
        );
        $mail = new WebsiteFeedbackReceived($data);
        $envelope = $mail->envelope();

        $this->assertInstanceOf(ShouldQueue::class, $mail);
        $this->assertSame('[Website-Feedback] Idee – Mitgliederbereich', $envelope->subject);
        $this->assertCount(1, $envelope->replyTo);
        $this->assertSame('mara@example.com', $envelope->replyTo[0]->address);
        $this->assertSame('Mara Mitglied', $envelope->replyTo[0]->name);
        $this->assertSame('emails.feedback.website-feedback', $mail->content()->markdown);
        $this->assertSame($data, $mail->content()->with['feedback']);
    }

    public function test_anonymous_feedback_contains_no_reporter_identity_or_reply_to(): void
    {
        $mail = new WebsiteFeedbackReceived($this->feedback());
        $html = $mail->render();

        $this->assertSame([], $mail->envelope()->replyTo);
        $this->assertStringContainsString('Anonym eingereicht', $html);
        $this->assertStringNotContainsString('mara@example.com', $html);
        $this->assertStringNotContainsString('Mara Mitglied', $html);
    }

    public function test_feedback_message_is_escaped_in_the_rendered_mail(): void
    {
        $mail = new WebsiteFeedbackReceived($this->feedback(message: '<script>alert("x")</script> Gute Idee'));
        $html = $mail->render();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('Gute Idee', $html);
    }

    private function feedback(
        string $message = 'Bitte ergänzt eine bessere Navigation.',
        ?string $reporterName = null,
        ?string $reporterEmail = null,
    ): WebsiteFeedbackData {
        return new WebsiteFeedbackData(
            category: WebsiteFeedbackCategory::Idea,
            message: $message,
            pageTitle: 'Mitgliederbereich',
            pageUrl: 'https://maddrax-fanclub.de/dashboard',
            submittedAt: new CarbonImmutable('2026-08-02 12:00:00', 'Europe/Berlin'),
            reporterName: $reporterName,
            reporterEmail: $reporterEmail,
        );
    }
}
