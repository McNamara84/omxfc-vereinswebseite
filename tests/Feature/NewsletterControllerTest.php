<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\Newsletter;
use App\Models\NewsletterAusgabe;
use App\Services\NewsletterImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('authorized roles can view newsletter form', function (string $role): void {
    $user = $this->actingMember($role);
    $this->actingAs($user)->get(route('newsletter.create'))->assertOk()->assertDontSee('Newsletter testen')->assertSee('Markdown wird unterstützt')->assertSee('Bilder');
})->with([['Admin'], ['Vorstand']]);

test('unauthorized roles cannot view newsletter form', function (string $role): void {
    $user = $this->actingMember($role);
    $this->actingAs($user)->get(route('newsletter.create'))->assertForbidden();
})->with([['Mitglied'], ['Kassenwart']]);

test('authorized roles can send newsletter to selected roles', function (string $senderRole): void {
    Mail::fake();
    $sender = $this->actingMember($senderRole);
    $member = $this->actingMember('Mitglied');
    $board = $this->actingMember('Vorstand');
    $data = ['roles' => ['Mitglied', 'Vorstand'], 'subject' => 'Info', 'topics' => [['key' => 'topic-a', 'title' => 'A', 'content' => 'B']]];
    $response = $this->actingAs($sender)->post(route('newsletter.send'), $data);
    $response->assertRedirect(route('newsletter.create'));
    Mail::assertQueued(Newsletter::class, fn (Newsletter $mail) => $mail->hasTo($member->email) && $mail->subjectLine === 'Info');
    Mail::assertQueued(Newsletter::class, fn (Newsletter $mail) => $mail->hasTo($board->email));
    if ($senderRole === 'Vorstand') {
        Mail::assertQueued(Newsletter::class, fn (Newsletter $mail) => $mail->hasTo($sender->email));
    }
    Mail::assertQueuedCount($senderRole === 'Vorstand' ? 3 : 2);
    $this->assertDatabaseHas('newsletter_ausgaben', ['subject' => 'Info', 'status' => 'entwurf']);
})->with([['Admin'], ['Vorstand']]);

test('send validation errors', function (): void {
    $admin = $this->actingMember('Admin');
    $response = $this->actingAs($admin)->from(route('newsletter.create'))->post(route('newsletter.send'), []);
    $response->assertRedirect(route('newsletter.create'));
    $response->assertSessionHasErrors(['roles', 'subject', 'topics']);
});

test('send validation rejects empty roles array', function (): void {
    $admin = $this->actingMember('Admin');
    $response = $this->actingAs($admin)->from(route('newsletter.create'))->post(route('newsletter.send'), ['roles' => [], 'subject' => 'Info', 'topics' => [['key' => 'topic-a', 'title' => 'A', 'content' => 'B']]]);
    $response->assertRedirect(route('newsletter.create'));
    $response->assertSessionHasErrors(['roles']);
});

test('unauthorized roles cannot send newsletter', function (string $role): void {
    $user = $this->actingMember($role);
    $this->actingAs($user)->post(route('newsletter.send'), [])->assertForbidden();
})->with([['Mitglied'], ['Kassenwart']]);

test('newsletter is not archived when selected roles have no recipients', function (): void {
    Mail::fake();
    Storage::fake('public');
    $admin = $this->actingMember('Admin');
    $data = ['roles' => ['Ehrenmitglied'], 'subject' => 'Info', 'topics' => [['key' => 'topic-a', 'title' => 'A', 'content' => 'B', 'images' => [UploadedFile::fake()->image('niemand.jpg', 600, 400)]]]];
    $this->actingAs($admin)->post(route('newsletter.send'), $data)->assertRedirect(route('newsletter.create'))->assertSessionHas('status', 'Keine Empfänger für die ausgewählten Rollen gefunden.');
    Mail::assertNothingQueued();
    $this->assertDatabaseCount('newsletter_ausgaben', 0);
    expect(Storage::disk('public')->allFiles(NewsletterImageService::STORAGE_PATH))->toBe([]);
});

test('authorized roles can send newsletter with topic images', function (): void {
    Mail::fake();
    Storage::fake('public');
    $admin = $this->actingMember('Admin');
    $member = $this->actingMember('Mitglied');
    $data = ['roles' => ['Mitglied'], 'subject' => 'Mit Bild', 'topics' => [['key' => 'topic-a', 'title' => 'Fotothema', 'content' => 'Text mit **Markdown**', 'images' => [UploadedFile::fake()->image('eins.jpg', 800, 600), UploadedFile::fake()->image('zwei.png', 640, 480)]]]];
    $this->actingAs($admin)->post(route('newsletter.send'), $data)->assertRedirect(route('newsletter.create'));
    $ausgabe = NewsletterAusgabe::query()->firstOrFail();
    expect($ausgabe->subject)->toBe('Mit Bild')
        ->and($ausgabe->topics[0]['images'])->toHaveCount(2);
    foreach ($ausgabe->topics[0]['images'] as $path) {
        expect(Storage::disk('public')->exists($path))->toBeTrue()
            ->and($path)->toStartWith(NewsletterImageService::STORAGE_PATH.'/');
    }
    Mail::assertQueued(Newsletter::class, fn (Newsletter $mail) => $mail->hasTo($member->email) && $mail->topics[0]['images'] === $ausgabe->topics[0]['images']);
});

test('send cleans up uploaded images when queueing throws', function (): void {
    Storage::fake('public');
    $admin = $this->actingMember('Admin');
    $empfaenger = $this->createUserWithRole('Ehrenmitglied');
    Mail::shouldReceive('to')->once()->with($empfaenger->email)->andReturnSelf();
    Mail::shouldReceive('queue')->once()->andThrow(new \RuntimeException('Queue fehlgeschlagen.'));
    $this->withoutExceptionHandling();
    try {
        $this->actingAs($admin)->post(route('newsletter.send'), ['roles' => ['Ehrenmitglied'], 'subject' => 'Mit Fehler', 'topics' => [['key' => 'topic-fehler', 'title' => 'Fotothema', 'content' => 'Text', 'images' => [UploadedFile::fake()->image('fehlerbild.jpg', 800, 600)]]]]);
        $this->fail('Der Versand hätte mit einer Queue-Exception abbrechen müssen.');
    } catch (\RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Queue fehlgeschlagen.');
    }
    $this->assertDatabaseCount('newsletter_ausgaben', 0);
    expect(Storage::disk('public')->allFiles(NewsletterImageService::STORAGE_PATH))->toBe([]);
});

test('send validation rejects duplicate topic keys', function (): void {
    $admin = $this->actingMember('Admin');
    $response = $this->actingAs($admin)->from(route('newsletter.create'))->post(route('newsletter.send'), ['roles' => ['Mitglied'], 'subject' => 'Doppelte Keys', 'topics' => [['key' => 'duplicate-key', 'title' => 'A', 'content' => 'B'], ['key' => 'duplicate-key', 'title' => 'C', 'content' => 'D']]]);
    $response->assertRedirect(route('newsletter.create'));
    $response->assertSessionHasErrors(['topics.1.key']);
});

test('newsletter form escapes old topics in alpine payload', function (): void {
    $admin = $this->actingMember('Admin');
    $response = $this->actingAs($admin)->withSession(['_old_input' => ['subject' => "Bob's Newsletter", 'roles' => ['Mitglied'], 'topics' => [['key' => 'topic-a', 'title' => "Bob's Thema", 'content' => "Text mit 'Zitat' und Markdown"]]]])->get(route('newsletter.create'));
    $response->assertOk();
    expect($response->getContent())->toContain('x-data="newsletterForm(')
        ->and($response->getContent())->not->toContain("x-data='newsletterForm(")
        ->and($response->getContent())->toContain('Bob\\\\u0027s Thema')
        ->and($response->getContent())->toContain('Text mit \\\\u0027Zitat\\\\u0027 und Markdown');
});
