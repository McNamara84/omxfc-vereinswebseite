<?php

namespace Tests\Feature;

use App\Mail\Newsletter;
use App\Models\NewsletterAusgabe;
use App\Services\NewsletterImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class NewsletterControllerTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    #[TestWith(['Admin'])]
    #[TestWith(['Vorstand'])]
    public function test_authorized_roles_can_view_newsletter_form(string $role): void
    {
        $user = $this->actingMember($role);

        $this->actingAs($user)
            ->get(route('newsletter.create'))
            ->assertOk()
            ->assertDontSee('Newsletter testen')
            ->assertSee('Markdown wird unterstützt')
            ->assertSee('Bilder');
    }

    #[TestWith(['Mitglied'])]
    #[TestWith(['Kassenwart'])]
    public function test_unauthorized_roles_cannot_view_newsletter_form(string $role): void
    {
        $user = $this->actingMember($role);

        $this->actingAs($user)
            ->get(route('newsletter.create'))
            ->assertForbidden();
    }

    #[TestWith(['Admin'])]
    #[TestWith(['Vorstand'])]
    public function test_authorized_roles_can_send_newsletter_to_selected_roles(string $senderRole): void
    {
        Mail::fake();

        $sender = $this->actingMember($senderRole);
        $member = $this->actingMember('Mitglied');
        $board = $this->actingMember('Vorstand');

        $data = [
            'roles' => ['Mitglied', 'Vorstand'],
            'subject' => 'Info',
            'topics' => [
                ['title' => 'A', 'content' => 'B'],
            ],
        ];

        $response = $this->actingAs($sender)->post(route('newsletter.send'), $data);

        $response->assertRedirect(route('newsletter.create'));

        Mail::assertQueued(Newsletter::class, function (Newsletter $mail) use ($member) {
            return $mail->hasTo($member->email) && $mail->subjectLine === 'Info';
        });
        Mail::assertQueued(Newsletter::class, function (Newsletter $mail) use ($board) {
            return $mail->hasTo($board->email);
        });

        if ($senderRole === 'Vorstand') {
            Mail::assertQueued(Newsletter::class, function (Newsletter $mail) use ($sender) {
                return $mail->hasTo($sender->email);
            });
        }

        Mail::assertQueuedCount($senderRole === 'Vorstand' ? 3 : 2);

        $this->assertDatabaseHas('newsletter_ausgaben', [
            'subject' => 'Info',
            'status' => 'entwurf',
        ]);
    }

    public function test_send_validation_errors(): void
    {
        $admin = $this->actingMember('Admin');

        $response = $this->actingAs($admin)
            ->from(route('newsletter.create'))
            ->post(route('newsletter.send'), []);

        $response->assertRedirect(route('newsletter.create'));
        $response->assertSessionHasErrors(['roles', 'subject', 'topics']);
    }

    public function test_send_validation_rejects_empty_roles_array(): void
    {
        $admin = $this->actingMember('Admin');

        $response = $this->actingAs($admin)
            ->from(route('newsletter.create'))
            ->post(route('newsletter.send'), [
                'roles' => [],
                'subject' => 'Info',
                'topics' => [
                    ['title' => 'A', 'content' => 'B'],
                ],
            ]);

        $response->assertRedirect(route('newsletter.create'));
        $response->assertSessionHasErrors(['roles']);
    }

    #[TestWith(['Mitglied'])]
    #[TestWith(['Kassenwart'])]
    public function test_unauthorized_roles_cannot_send_newsletter(string $role): void
    {
        $user = $this->actingMember($role);

        $this->actingAs($user)
            ->post(route('newsletter.send'), [])
            ->assertForbidden();
    }

    public function test_newsletter_is_not_archived_when_selected_roles_have_no_recipients(): void
    {
        Mail::fake();
        Storage::fake('public');

        $admin = $this->actingMember('Admin');

        $data = [
            'roles' => ['Ehrenmitglied'],
            'subject' => 'Info',
            'topics' => [
                [
                    'title' => 'A',
                    'content' => 'B',
                    'images' => [UploadedFile::fake()->image('niemand.jpg', 600, 400)],
                ],
            ],
        ];

        $this->actingAs($admin)
            ->post(route('newsletter.send'), $data)
            ->assertRedirect(route('newsletter.create'))
            ->assertSessionHas('status', 'Keine Empfänger für die ausgewählten Rollen gefunden.');

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('newsletter_ausgaben', 0);
        $this->assertSame([], Storage::disk('public')->allFiles(NewsletterImageService::STORAGE_PATH));
    }

    public function test_authorized_roles_can_send_newsletter_with_topic_images(): void
    {
        Mail::fake();
        Storage::fake('public');

        $admin = $this->actingMember('Admin');
        $member = $this->actingMember('Mitglied');

        $data = [
            'roles' => ['Mitglied'],
            'subject' => 'Mit Bild',
            'topics' => [
                [
                    'title' => 'Fotothema',
                    'content' => 'Text mit **Markdown**',
                    'images' => [
                        UploadedFile::fake()->image('eins.jpg', 800, 600),
                        UploadedFile::fake()->image('zwei.png', 640, 480),
                    ],
                ],
            ],
        ];

        $this->actingAs($admin)
            ->post(route('newsletter.send'), $data)
            ->assertRedirect(route('newsletter.create'));

        $ausgabe = NewsletterAusgabe::query()->firstOrFail();

        $this->assertSame('Mit Bild', $ausgabe->subject);
        $this->assertCount(2, $ausgabe->topics[0]['images']);

        foreach ($ausgabe->topics[0]['images'] as $path) {
            $this->assertTrue(Storage::disk('public')->exists($path));
            $this->assertStringStartsWith(NewsletterImageService::STORAGE_PATH.'/', $path);
        }

        Mail::assertQueued(Newsletter::class, function (Newsletter $mail) use ($member, $ausgabe) {
            return $mail->hasTo($member->email)
                && $mail->topics[0]['images'] === $ausgabe->topics[0]['images'];
        });
    }
}
