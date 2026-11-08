<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Mail\Newsletter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('newsletter mail renders markdown images and new greeting', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('newsletter-images/mailbild.jpg', 'mailbild');
    $mail = new Newsletter('Themenmail', [['key' => 'topic-1', 'title' => 'Thema', 'content' => "**Markdown** Inhalt\nZweite Zeile", 'images' => ['newsletter-images/mailbild.jpg']]]);
    $html = $mail->render();
    expect($html)->toContain('<strong>Markdown</strong>')
        ->toContain('Zweite Zeile')
        ->toContain(url('/storage/newsletter-images/mailbild.jpg'))
        ->toContain('Tuma sa feesa,<br>Tanja 1.Vorsitzende');
});
