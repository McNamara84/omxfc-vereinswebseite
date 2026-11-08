<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Mail\WebsiteFeedbackReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(WebsiteFeedbackReceivedMailTestFixtures::class);

test('named feedback builds subject content and reply to', function (): void {
    $data = $this->feedback(reporterName: 'Mara Mitglied', reporterEmail: 'mara@example.com');
    $mail = new WebsiteFeedbackReceived($data);
    $envelope = $mail->envelope();
    expect($mail)->toBeInstanceOf(ShouldQueue::class)
        ->and($envelope->subject)->toBe('[Website-Feedback] Idee – Mitgliederbereich')
        ->and($envelope->replyTo)->toHaveCount(1)
        ->and($envelope->replyTo[0]->address)->toBe('mara@example.com')
        ->and($envelope->replyTo[0]->name)->toBe('Mara Mitglied')
        ->and($mail->content()->markdown)->toBe('emails.feedback.website-feedback')
        ->and($mail->content()->with['feedback'])->toBe($data);
});

test('anonymous feedback contains no reporter identity or reply to', function (): void {
    $mail = new WebsiteFeedbackReceived($this->feedback());
    $html = $mail->render();
    expect($mail->envelope()->replyTo)->toBe([])
        ->and($html)->toContain('Anonym eingereicht')->not->toContain('mara@example.com')->not->toContain('Mara Mitglied');
});

test('feedback message is escaped in the rendered mail', function (): void {
    $mail = new WebsiteFeedbackReceived($this->feedback(message: '<script>alert("x")</script> Gute Idee'));
    $html = $mail->render();
    expect($html)->not->toContain('<script>')
        ->toContain('&lt;script&gt;')
        ->toContain('Gute Idee');
});
