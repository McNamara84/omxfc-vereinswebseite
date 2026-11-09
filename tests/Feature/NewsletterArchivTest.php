<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\NewsletterAusgabeStatus;
use App\Models\NewsletterAusgabe;
use App\Models\User;
use App\Services\NewsletterImageService;
use App\Support\Navigation\NavigationBuilder;
use App\Support\NewsletterTopics;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(NewsletterArchivTestFixtures::class);

test('mitglied can view published newsletter archive index', function (): void {
    $mitglied = $this->actingMember();
    $ausgabe = NewsletterAusgabe::factory()->published()->create(['subject' => 'Newsletter Mai 2026']);
    $this->actingAs($mitglied)->get(route('newsletter.archiv.index'))->assertOk()->assertSee('Newsletter Mai 2026')->assertSee(route('newsletter.archiv.show', $ausgabe), false);
});

test('newsletter archive index view handles null topics defensively', function (): void {
    $ausgabe = NewsletterAusgabe::factory()->published()->make(['subject' => 'Ohne Themen', 'slug' => 'ohne-themen', 'topics' => null]);
    $this->view('newsletter.archiv.index', ['ausgaben' => new LengthAwarePaginator(collect([$ausgabe]), 1, 12)])->assertSee('Ohne Themen')->assertSee('Diese Ausgabe enthält aktuell noch keine Themenblöcke.');
});

test('newsletter archive index view handles missing topic title defensively', function (): void {
    $ausgabe = NewsletterAusgabe::factory()->published()->make(['subject' => 'Thema ohne Titel', 'slug' => 'thema-ohne-titel', 'topics' => [['content' => 'Nur Inhalt ohne Überschrift']]]);
    $this->view('newsletter.archiv.index', ['ausgaben' => new LengthAwarePaginator(collect([$ausgabe]), 1, 12)])->assertSee('Thema ohne Titel')->assertSee('Ohne Titel')->assertSee('Nur Inhalt ohne Überschrift');
});

test('mitglied can view published newsletter detail', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('newsletter-images/detailbild.jpg', 'bildinhalt');
    $mitglied = $this->actingMember();
    $ausgabe = NewsletterAusgabe::factory()->published()->create(['subject' => 'Detailausgabe', 'topics' => [['title' => 'Rundschau', 'content' => "Erste Zeile\nZweite Zeile\n\n**Wichtiger Hinweis**", 'images' => ['newsletter-images/detailbild.jpg']]]]);
    $this->actingAs($mitglied)->get(route('newsletter.archiv.show', $ausgabe))->assertOk()->assertSee('Detailausgabe')->assertSee('Rundschau')->assertSee('Erste Zeile')->assertSee('Zweite Zeile')->assertSee('<strong>Wichtiger Hinweis</strong>', false)->assertSee('/storage/newsletter-images/detailbild.jpg', false);
});

test('newsletter archive index uses markdown excerpt for first topic', function (): void {
    $mitglied = $this->actingMember();
    NewsletterAusgabe::factory()->published()->create(['subject' => 'Markdown-Ausgabe', 'topics' => [['title' => 'Formatierung', 'content' => '**Fett** mit [Link](https://example.com) und normalem Text']]]);
    $this->actingAs($mitglied)->get(route('newsletter.archiv.index'))->assertOk()->assertSee('Fett mit Link und normalem Text')->assertDontSee('**Fett**');
});

test('mitglieder do not see drafts in archive', function (): void {
    $mitglied = $this->actingMember();
    $entwurf = NewsletterAusgabe::factory()->create(['subject' => 'Interner Entwurf']);
    $this->actingAs($mitglied)->get(route('newsletter.archiv.index'))->assertOk()->assertDontSee('Interner Entwurf');
    $this->actingAs($mitglied)->get(route('newsletter.archiv.show', $entwurf))->assertNotFound();
});

test('mitglied cannot view vorstand only newsletter archive entry', function (): void {
    $mitglied = $this->actingMember();
    $vorstandIntern = NewsletterAusgabe::factory()->published()->create(['subject' => 'Nur fuer Vorstand', 'recipient_roles' => ['Vorstand']]);
    $mitgliedIntern = NewsletterAusgabe::factory()->published()->create(['subject' => 'Fuer Mitglieder', 'recipient_roles' => ['Mitglied']]);
    $this->actingAs($mitglied)->get(route('newsletter.archiv.index'))->assertOk()->assertSee('Fuer Mitglieder')->assertDontSee('Nur fuer Vorstand');
    $this->actingAs($mitglied)->get(route('newsletter.archiv.show', $vorstandIntern))->assertNotFound();
    $this->actingAs($mitglied)->get(route('newsletter.archiv.show', $mitgliedIntern))->assertOk()->assertSee('Fuer Mitglieder');
});

test('admin can view published newsletter archive entry even when not recipient', function (): void {
    $admin = $this->actingMember('Admin');
    $mitgliedIntern = NewsletterAusgabe::factory()->published()->create(['subject' => 'Nur für Mitglieder sichtbar', 'recipient_roles' => ['Mitglied']]);
    $this->actingAs($admin)->get(route('newsletter.archiv.index'))->assertOk()->assertSee('Nur für Mitglieder sichtbar');
    $this->actingAs($admin)->get(route('newsletter.archiv.show', $mitgliedIntern))->assertOk()->assertSee('Nur für Mitglieder sichtbar');
});

test('guest is redirected from newsletter archive', function (): void {
    $ausgabe = NewsletterAusgabe::factory()->published()->create();
    $this->get(route('newsletter.archiv.index'))->assertRedirect(route('login'));
    $this->get(route('newsletter.archiv.show', $ausgabe))->assertRedirect(route('login'));
});

test('authenticated non member cannot view newsletter archive', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('newsletter.archiv.index'))->assertForbidden();
});

test('newsletter archive edit form escapes old topics in alpine payload', function (string $role): void {
    $user = $this->actingMember($role);
    $ausgabe = NewsletterAusgabe::factory()->create();
    $response = $this->actingAs($user)->withSession(['_old_input' => ['subject' => "Bob's Archiv-Ausgabe", 'slug' => 'bobs-archiv-ausgabe', 'recipient_roles' => ['Mitglied'], 'topics' => [['key' => 'topic-1', 'title' => "Bob's Thema", 'content' => "Text mit 'Zitat' und Markdown"]]]])->get(route('newsletter.archiv.admin.edit', $ausgabe));
    $response->assertOk();
    expect($response->getContent())->toContain('x-data="newsletterArchivForm(')
        ->and($response->getContent())->not->toContain("x-data='newsletterArchivForm(")
        ->and($response->getContent())->toContain('Bob\\\\u0027s Thema')
        ->and($response->getContent())->toContain('Text mit \\\\u0027Zitat\\\\u0027 und Markdown');
})->with([['Admin'], ['Vorstand']]);

test('authorized roles can view newsletter archive admin index', function (string $role): void {
    $user = $this->actingMember($role);
    NewsletterAusgabe::factory()->create(['subject' => 'Archiv Entwurf']);
    NewsletterAusgabe::factory()->published()->create(['subject' => 'Archiv Live']);
    $this->actingAs($user)->get(route('newsletter.archiv.admin.index'))->assertOk()->assertSee('Archiv Entwurf')->assertSee('Archiv Live');
})->with([['Admin'], ['Vorstand']]);

test('kassenwart cannot view newsletter archive admin index', function (): void {
    $kassenwart = $this->actingMember('Kassenwart');
    $this->actingAs($kassenwart)->get(route('newsletter.archiv.admin.index'))->assertForbidden();
});

test('authorized roles can create newsletter archive draft manually', function (string $role): void {
    $user = $this->actingMember($role);
    $this->actingAs($user)->post(route('newsletter.archiv.admin.store'))->assertRedirect();
    $this->assertDatabaseHas('newsletter_ausgaben', ['subject' => 'Neue Archiv-Ausgabe', 'status' => NewsletterAusgabeStatus::Entwurf->value, 'created_by' => $user->id]);
})->with([['Admin'], ['Vorstand']]);

test('kassenwart cannot create newsletter archive draft manually', function (): void {
    $kassenwart = $this->actingMember('Kassenwart');
    $this->actingAs($kassenwart)->post(route('newsletter.archiv.admin.store'))->assertForbidden();
});

test('authorized roles can view newsletter archive edit form', function (string $role): void {
    $user = $this->actingMember($role);
    $ausgabe = NewsletterAusgabe::factory()->create(['subject' => 'Bearbeitbare Ausgabe']);
    $this->actingAs($user)->get(route('newsletter.archiv.admin.edit', $ausgabe))->assertOk()->assertSee('Newsletter-Ausgabe bearbeiten')->assertSee('Bearbeitbare Ausgabe')->assertSee('Themenblöcke');
})->with([['Admin'], ['Vorstand']]);

test('authorized roles can update newsletter archive entry', function (string $role): void {
    $user = $this->actingMember($role);
    $ausgabe = NewsletterAusgabe::factory()->create(['subject' => 'Alte Ausgabe', 'slug' => 'alte-ausgabe']);
    $this->actingAs($user)->put(route('newsletter.archiv.admin.update', $ausgabe), ['subject' => 'Neue Ausgabe', 'slug' => 'neue-ausgabe', 'recipient_roles' => ['Mitglied', 'Vorstand'], 'sent_at' => '2026-05-17 12:00', 'topics' => [['key' => 'topic-update', 'title' => 'Update', 'content' => 'Archivtext']]])->assertRedirect(route('newsletter.archiv.admin.edit', $ausgabe->fresh()));
    $this->assertDatabaseHas('newsletter_ausgaben', ['id' => $ausgabe->id, 'subject' => 'Neue Ausgabe', 'slug' => 'neue-ausgabe']);
})->with([['Admin'], ['Vorstand']]);

test('authorized roles can update newsletter archive entry images', function (string $role): void {
    Storage::fake('public');
    Storage::disk('public')->put('newsletter-images/altbild.jpg', 'alt');
    $user = $this->actingMember($role);
    $ausgabe = NewsletterAusgabe::factory()->create(['topics' => [['key' => 'topic-1', 'title' => 'Mit Bild', 'content' => 'Alttext', 'images' => ['newsletter-images/altbild.jpg']]]]);
    $this->actingAs($user)->put(route('newsletter.archiv.admin.update', $ausgabe), ['subject' => 'Mit Bild aktualisiert', 'slug' => 'mit-bild-aktualisiert', 'recipient_roles' => ['Mitglied'], 'sent_at' => '2026-05-17 12:00', 'topics' => [['key' => 'topic-1', 'title' => 'Mit Bild', 'content' => 'Neutext', 'remove_images' => ['newsletter-images/altbild.jpg'], 'images' => [UploadedFile::fake()->image('neu.jpg', 700, 500)]]]])->assertRedirect(route('newsletter.archiv.admin.edit', $ausgabe->fresh()));
    $ausgabe->refresh();
    expect($ausgabe->subject)->toBe('Mit Bild aktualisiert')
        ->and($ausgabe->topics[0]['images'])->toHaveCount(1)
        ->and($ausgabe->topics[0]['images'][0])->toStartWith(NewsletterImageService::STORAGE_PATH.'/')
        ->and(Storage::disk('public')->exists('newsletter-images/altbild.jpg'))->toBeFalse()
        ->and(Storage::disk('public')->exists($ausgabe->topics[0]['images'][0]))->toBeTrue();
})->with([['Admin'], ['Vorstand']]);

test('authorized roles preserve images when legacy topic keys are rotated', function (string $role): void {
    Storage::fake('public');
    Storage::disk('public')->put('newsletter-images/legacy.jpg', 'legacy');
    $user = $this->actingMember($role);
    $ausgabe = NewsletterAusgabe::factory()->create(['topics' => [['key' => 'legacy-topic-0', 'title' => 'Mit Legacy-Key', 'content' => 'Alttext', 'images' => ['newsletter-images/legacy.jpg']]]]);
    $this->actingAs($user)->put(route('newsletter.archiv.admin.update', $ausgabe), ['subject' => 'Legacy aktualisiert', 'slug' => 'legacy-aktualisiert', 'recipient_roles' => ['Mitglied'], 'sent_at' => '2026-05-17 12:00', 'topics' => [['key' => 'legacy-topic-0', 'title' => 'Mit Legacy-Key', 'content' => 'Neutext']]])->assertRedirect(route('newsletter.archiv.admin.edit', $ausgabe->fresh()));
    $ausgabe->refresh();
    expect($ausgabe->topics[0]['images'])->toBe(['newsletter-images/legacy.jpg'])
        ->and($ausgabe->topics[0]['key'])->not->toBe('legacy-topic-0')
        ->and(NewsletterTopics::usesLegacyKey($ausgabe->topics[0]['key']))->toBeFalse()
        ->and(Storage::disk('public')->exists('newsletter-images/legacy.jpg'))->toBeTrue();
})->with([['Admin'], ['Vorstand']]);

test('update cleans up uploaded images when persisting fails', function (string $role): void {
    Storage::fake('public');
    Storage::disk('public')->put('newsletter-images/bestand.jpg', 'bestand');
    $user = $this->actingMember($role);
    $ausgabe = NewsletterAusgabe::factory()->create(['topics' => [['key' => 'topic-bestand', 'title' => 'Mit Bestand', 'content' => 'Alttext', 'images' => ['newsletter-images/bestand.jpg']]]]);
    NewsletterAusgabe::flushEventListeners();
    NewsletterAusgabe::updating(static function (): void {
        throw new \RuntimeException('Update fehlgeschlagen.');
    });
    $this->withoutExceptionHandling();
    try {
        $this->actingAs($user)->put(route('newsletter.archiv.admin.update', $ausgabe), ['subject' => 'Fehler beim Speichern', 'slug' => 'fehler-beim-speichern', 'recipient_roles' => ['Mitglied'], 'sent_at' => '2026-05-17 12:00', 'topics' => [['key' => 'topic-bestand', 'title' => 'Mit Bestand', 'content' => 'Neutext', 'images' => [UploadedFile::fake()->image('neu.jpg', 700, 500)]]]]);
        $this->fail('Das Update hätte mit einer Persistenz-Exception abbrechen müssen.');
    } catch (\RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Update fehlgeschlagen.');
    } finally {
        NewsletterAusgabe::flushEventListeners();
        NewsletterAusgabe::clearBootedModels();
    }
    $ausgabe->refresh();
    expect($ausgabe->topics[0]['images'])->toBe(['newsletter-images/bestand.jpg'])
        ->and(Storage::disk('public')->allFiles(NewsletterImageService::STORAGE_PATH))->toBe(['newsletter-images/bestand.jpg']);
})->with([['Admin'], ['Vorstand']]);

test('authorized roles can update newsletter archive entry with long colliding slug', function (string $role): void {
    $user = $this->actingMember($role);
    $longSlug = str_repeat('a', 255);
    NewsletterAusgabe::factory()->create(['slug' => $longSlug]);
    $ausgabe = NewsletterAusgabe::factory()->create(['slug' => 'kollision']);
    $this->actingAs($user)->put(route('newsletter.archiv.admin.update', $ausgabe), ['subject' => 'Lange Kollision', 'slug' => $longSlug, 'recipient_roles' => ['Mitglied'], 'sent_at' => '2026-05-17 12:00', 'topics' => [['key' => 'topic-long', 'title' => 'Update', 'content' => 'Langer Slug mit Suffix']]])->assertRedirect(route('newsletter.archiv.admin.edit', $ausgabe->fresh()));
    $ausgabe->refresh();
    expect(strlen($ausgabe->slug))->toBeLessThanOrEqual(255)
        ->and($ausgabe->slug)->toEndWith('-2');
})->with([['Admin'], ['Vorstand']]);

test('authorized roles can publish newsletter archive entry', function (string $role): void {
    $user = $this->actingMember($role);
    $ausgabe = NewsletterAusgabe::factory()->create(['status' => NewsletterAusgabeStatus::Entwurf, 'published_at' => null]);
    $this->actingAs($user)->post(route('newsletter.archiv.admin.publish', $ausgabe))->assertRedirect(route('newsletter.archiv.admin.edit', $ausgabe->fresh()));
    $ausgabe->refresh();
    expect($ausgabe->status)->toBe(NewsletterAusgabeStatus::Veroeffentlicht)
        ->and($ausgabe->published_at)->not->toBeNull();
})->with([['Admin'], ['Vorstand']]);

test('authorized roles can update newsletter archive entry without sent at', function (string $role): void {
    $user = $this->actingMember($role);
    $ausgabe = NewsletterAusgabe::factory()->create(['subject' => 'Ohne Versandzeit', 'sent_at' => null]);
    $this->actingAs($user)->put(route('newsletter.archiv.admin.update', $ausgabe), ['subject' => 'Ohne Versandzeit aktualisiert', 'slug' => 'ohne-versandzeit-aktualisiert', 'recipient_roles' => ['Mitglied'], 'sent_at' => '', 'topics' => [['key' => 'topic-ohne-sent-at', 'title' => 'Update', 'content' => 'Weiter ohne Zeitpunkt']]])->assertRedirect(route('newsletter.archiv.admin.edit', $ausgabe->fresh()));
    $ausgabe->refresh();
    expect($ausgabe->subject)->toBe('Ohne Versandzeit aktualisiert')
        ->and($ausgabe->sent_at)->toBeNull();
})->with([['Admin'], ['Vorstand']]);

test('authorized roles cannot update newsletter archive entry with duplicate topic keys', function (string $role): void {
    $user = $this->actingMember($role);
    $ausgabe = NewsletterAusgabe::factory()->create();
    $response = $this->actingAs($user)->from(route('newsletter.archiv.admin.edit', $ausgabe))->put(route('newsletter.archiv.admin.update', $ausgabe), ['subject' => 'Doppelte Keys', 'slug' => 'doppelte-keys', 'recipient_roles' => ['Mitglied'], 'sent_at' => '2026-05-17 12:00', 'topics' => [['key' => 'duplicate-key', 'title' => 'A', 'content' => 'B'], ['key' => 'duplicate-key', 'title' => 'C', 'content' => 'D']]]);
    $response->assertRedirect(route('newsletter.archiv.admin.edit', $ausgabe));
    $response->assertSessionHasErrors(['topics.1.key']);
})->with([['Admin'], ['Vorstand']]);

test('navigation builder places newsletter archive only in authenticated verein section', function (): void {
    $mitglied = $this->createUserWithRole('Mitglied');
    $builder = app(NavigationBuilder::class);
    $guestNavigation = $builder->build(null);
    $authNavigation = $builder->build($mitglied->load('teams', 'ownedTeams'));
    $guestVereinItems = $this->sectionItemTitles($guestNavigation, 'Verein');
    $authVereinItems = $this->sectionItemTitles($authNavigation, 'Verein');
    expect($guestVereinItems)->not->toContain('Newsletter-Archiv')
        ->and($authVereinItems)->toContain('Newsletter-Archiv')
        ->and(array_search('Newsletter-Archiv', $authVereinItems, true))->toBe(array_search('Protokolle', $authVereinItems, true) - 1);
});

test('newsletter ausgaben generate unique slugs from subject', function (): void {
    $ersteAusgabe = NewsletterAusgabe::factory()->create(['subject' => 'Doppelter Betreff', 'slug' => null]);
    $zweiteAusgabe = NewsletterAusgabe::factory()->create(['subject' => 'Doppelter Betreff', 'slug' => null]);
    expect($ersteAusgabe->slug)->toBe('doppelter-betreff')
        ->and($zweiteAusgabe->slug)->toBe('doppelter-betreff-2');
});

test('newsletter ausgaben truncate generated slugs to database limit', function (): void {
    $longSubject = str_repeat('a', 255);
    $ersteAusgabe = NewsletterAusgabe::factory()->create(['subject' => $longSubject, 'slug' => null]);
    $zweiteAusgabe = NewsletterAusgabe::factory()->create(['subject' => $longSubject, 'slug' => null]);
    expect(strlen($ersteAusgabe->slug))->toBeLessThanOrEqual(255)
        ->and(strlen($zweiteAusgabe->slug))->toBeLessThanOrEqual(255)
        ->and($zweiteAusgabe->slug)->toEndWith('-2');
});
