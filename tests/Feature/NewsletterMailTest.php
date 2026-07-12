<?php

namespace Tests\Feature;

use App\Mail\Newsletter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NewsletterMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_newsletter_mail_renders_markdown_images_and_new_greeting(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('newsletter-images/mailbild.jpg', 'mailbild');

        $mail = new Newsletter('Themenmail', [
            [
                'key' => 'topic-1',
                'title' => 'Thema',
                'content' => "**Markdown** Inhalt\nZweite Zeile",
                'images' => ['newsletter-images/mailbild.jpg'],
            ],
        ]);

        $html = $mail->render();

        $this->assertStringContainsString('<strong>Markdown</strong>', $html);
        $this->assertStringContainsString('Zweite Zeile', $html);
        $this->assertStringContainsString(url('/storage/newsletter-images/mailbild.jpg'), $html);
        $this->assertStringContainsString('Tuma sa feesa,<br>Tanja 1.Vorsitzende', $html);
    }
}
