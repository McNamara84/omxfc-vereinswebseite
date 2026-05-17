<?php

namespace Tests\Feature;

use App\Enums\NewsletterAusgabeStatus;
use App\Models\NewsletterAusgabe;
use App\Models\User;
use App\Services\NewsletterImageService;
use App\Support\NewsletterTopics;
use App\Support\Navigation\NavigationBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class NewsletterArchivTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_mitglied_can_view_published_newsletter_archive_index(): void
    {
        $mitglied = $this->actingMember();
        $ausgabe = NewsletterAusgabe::factory()->published()->create([
            'subject' => 'Newsletter Mai 2026',
        ]);

        $this->actingAs($mitglied)
            ->get(route('newsletter.archiv.index'))
            ->assertOk()
            ->assertSee('Newsletter Mai 2026')
            ->assertSee(route('newsletter.archiv.show', $ausgabe), false);
    }

    public function test_newsletter_archive_index_view_handles_null_topics_defensively(): void
    {
        $ausgabe = NewsletterAusgabe::factory()->published()->make([
            'subject' => 'Ohne Themen',
            'slug' => 'ohne-themen',
            'topics' => null,
        ]);

        $this->view('newsletter.archiv.index', [
            'ausgaben' => new LengthAwarePaginator(collect([$ausgabe]), 1, 12),
        ])
            ->assertSee('Ohne Themen')
            ->assertSee('Diese Ausgabe enthält aktuell noch keine Themenblöcke.');
    }

    public function test_newsletter_archive_index_view_handles_missing_topic_title_defensively(): void
    {
        $ausgabe = NewsletterAusgabe::factory()->published()->make([
            'subject' => 'Thema ohne Titel',
            'slug' => 'thema-ohne-titel',
            'topics' => [
                [
                    'content' => 'Nur Inhalt ohne Überschrift',
                ],
            ],
        ]);

        $this->view('newsletter.archiv.index', [
            'ausgaben' => new LengthAwarePaginator(collect([$ausgabe]), 1, 12),
        ])
            ->assertSee('Thema ohne Titel')
            ->assertSee('Ohne Titel')
            ->assertSee('Nur Inhalt ohne Überschrift');
    }

    public function test_mitglied_can_view_published_newsletter_detail(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('newsletter-images/detailbild.jpg', 'bildinhalt');

        $mitglied = $this->actingMember();
        $ausgabe = NewsletterAusgabe::factory()->published()->create([
            'subject' => 'Detailausgabe',
            'topics' => [
                [
                    'title' => 'Rundschau',
                    'content' => "Erste Zeile\nZweite Zeile\n\n**Wichtiger Hinweis**",
                    'images' => ['newsletter-images/detailbild.jpg'],
                ],
            ],
        ]);

        $this->actingAs($mitglied)
            ->get(route('newsletter.archiv.show', $ausgabe))
            ->assertOk()
            ->assertSee('Detailausgabe')
            ->assertSee('Rundschau')
            ->assertSee('Erste Zeile')
            ->assertSee('Zweite Zeile')
            ->assertSee('<strong>Wichtiger Hinweis</strong>', false)
            ->assertSee('/storage/newsletter-images/detailbild.jpg', false);
    }

    public function test_newsletter_archive_index_uses_markdown_excerpt_for_first_topic(): void
    {
        $mitglied = $this->actingMember();

        NewsletterAusgabe::factory()->published()->create([
            'subject' => 'Markdown-Ausgabe',
            'topics' => [
                [
                    'title' => 'Formatierung',
                    'content' => '**Fett** mit [Link](https://example.com) und normalem Text',
                ],
            ],
        ]);

        $this->actingAs($mitglied)
            ->get(route('newsletter.archiv.index'))
            ->assertOk()
            ->assertSee('Fett mit Link und normalem Text')
            ->assertDontSee('**Fett**');
    }

    public function test_mitglieder_do_not_see_drafts_in_archive(): void
    {
        $mitglied = $this->actingMember();
        $entwurf = NewsletterAusgabe::factory()->create([
            'subject' => 'Interner Entwurf',
        ]);

        $this->actingAs($mitglied)
            ->get(route('newsletter.archiv.index'))
            ->assertOk()
            ->assertDontSee('Interner Entwurf');

        $this->actingAs($mitglied)
            ->get(route('newsletter.archiv.show', $entwurf))
            ->assertNotFound();
    }

    public function test_mitglied_cannot_view_vorstand_only_newsletter_archive_entry(): void
    {
        $mitglied = $this->actingMember();
        $vorstandIntern = NewsletterAusgabe::factory()->published()->create([
            'subject' => 'Nur fuer Vorstand',
            'recipient_roles' => ['Vorstand'],
        ]);
        $mitgliedIntern = NewsletterAusgabe::factory()->published()->create([
            'subject' => 'Fuer Mitglieder',
            'recipient_roles' => ['Mitglied'],
        ]);

        $this->actingAs($mitglied)
            ->get(route('newsletter.archiv.index'))
            ->assertOk()
            ->assertSee('Fuer Mitglieder')
            ->assertDontSee('Nur fuer Vorstand');

        $this->actingAs($mitglied)
            ->get(route('newsletter.archiv.show', $vorstandIntern))
            ->assertNotFound();

        $this->actingAs($mitglied)
            ->get(route('newsletter.archiv.show', $mitgliedIntern))
            ->assertOk()
            ->assertSee('Fuer Mitglieder');
    }

    public function test_admin_can_view_published_newsletter_archive_entry_even_when_not_recipient(): void
    {
        $admin = $this->actingMember('Admin');
        $mitgliedIntern = NewsletterAusgabe::factory()->published()->create([
            'subject' => 'Nur für Mitglieder sichtbar',
            'recipient_roles' => ['Mitglied'],
        ]);

        $this->actingAs($admin)
            ->get(route('newsletter.archiv.index'))
            ->assertOk()
            ->assertSee('Nur für Mitglieder sichtbar');

        $this->actingAs($admin)
            ->get(route('newsletter.archiv.show', $mitgliedIntern))
            ->assertOk()
            ->assertSee('Nur für Mitglieder sichtbar');
    }

    public function test_guest_is_redirected_from_newsletter_archive(): void
    {
        $ausgabe = NewsletterAusgabe::factory()->published()->create();

        $this->get(route('newsletter.archiv.index'))
            ->assertRedirect(route('login'));

        $this->get(route('newsletter.archiv.show', $ausgabe))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_non_member_cannot_view_newsletter_archive(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('newsletter.archiv.index'))
            ->assertForbidden();
    }

    #[TestWith(['Admin'])]
    #[TestWith(['Vorstand'])]
    public function test_newsletter_archive_edit_form_escapes_old_topics_in_alpine_payload(string $role): void
    {
        $user = $this->actingMember($role);
        $ausgabe = NewsletterAusgabe::factory()->create();

        $response = $this->actingAs($user)
            ->withSession([
                '_old_input' => [
                    'subject' => "Bob's Archiv-Ausgabe",
                    'slug' => 'bobs-archiv-ausgabe',
                    'recipient_roles' => ['Mitglied'],
                    'topics' => [
                        [
                            'key' => 'topic-1',
                            'title' => "Bob's Thema",
                            'content' => "Text mit 'Zitat' und Markdown",
                        ],
                    ],
                ],
            ])
            ->get(route('newsletter.archiv.admin.edit', $ausgabe));

        $response->assertOk();

        $this->assertStringContainsString('x-data="newsletterArchivForm(', $response->getContent());
        $this->assertStringNotContainsString("x-data='newsletterArchivForm(", $response->getContent());
        $this->assertStringContainsString('Bob\\\\u0027s Thema', $response->getContent());
        $this->assertStringContainsString('Text mit \\\\u0027Zitat\\\\u0027 und Markdown', $response->getContent());
    }

    #[TestWith(['Admin'])]
    #[TestWith(['Vorstand'])]
    public function test_authorized_roles_can_view_newsletter_archive_admin_index(string $role): void
    {
        $user = $this->actingMember($role);

        NewsletterAusgabe::factory()->create(['subject' => 'Archiv Entwurf']);
        NewsletterAusgabe::factory()->published()->create(['subject' => 'Archiv Live']);

        $this->actingAs($user)
            ->get(route('newsletter.archiv.admin.index'))
            ->assertOk()
            ->assertSee('Archiv Entwurf')
            ->assertSee('Archiv Live');
    }

    public function test_kassenwart_cannot_view_newsletter_archive_admin_index(): void
    {
        $kassenwart = $this->actingMember('Kassenwart');

        $this->actingAs($kassenwart)
            ->get(route('newsletter.archiv.admin.index'))
            ->assertForbidden();
    }

    #[TestWith(['Admin'])]
    #[TestWith(['Vorstand'])]
    public function test_authorized_roles_can_create_newsletter_archive_draft_manually(string $role): void
    {
        $user = $this->actingMember($role);

        $this->actingAs($user)
            ->post(route('newsletter.archiv.admin.store'))
            ->assertRedirect();

        $this->assertDatabaseHas('newsletter_ausgaben', [
            'subject' => 'Neue Archiv-Ausgabe',
            'status' => NewsletterAusgabeStatus::Entwurf->value,
            'created_by' => $user->id,
        ]);
    }

    public function test_kassenwart_cannot_create_newsletter_archive_draft_manually(): void
    {
        $kassenwart = $this->actingMember('Kassenwart');

        $this->actingAs($kassenwart)
            ->post(route('newsletter.archiv.admin.store'))
            ->assertForbidden();
    }

    #[TestWith(['Admin'])]
    #[TestWith(['Vorstand'])]
    public function test_authorized_roles_can_view_newsletter_archive_edit_form(string $role): void
    {
        $user = $this->actingMember($role);
        $ausgabe = NewsletterAusgabe::factory()->create([
            'subject' => 'Bearbeitbare Ausgabe',
        ]);

        $this->actingAs($user)
            ->get(route('newsletter.archiv.admin.edit', $ausgabe))
            ->assertOk()
            ->assertSee('Newsletter-Ausgabe bearbeiten')
            ->assertSee('Bearbeitbare Ausgabe')
            ->assertSee('Themenblöcke');
    }

    #[TestWith(['Admin'])]
    #[TestWith(['Vorstand'])]
    public function test_authorized_roles_can_update_newsletter_archive_entry(string $role): void
    {
        $user = $this->actingMember($role);
        $ausgabe = NewsletterAusgabe::factory()->create([
            'subject' => 'Alte Ausgabe',
            'slug' => 'alte-ausgabe',
        ]);

        $this->actingAs($user)
            ->put(route('newsletter.archiv.admin.update', $ausgabe), [
                'subject' => 'Neue Ausgabe',
                'slug' => 'neue-ausgabe',
                'recipient_roles' => ['Mitglied', 'Vorstand'],
                'sent_at' => '2026-05-17 12:00',
                'topics' => [
                    ['key' => 'topic-update', 'title' => 'Update', 'content' => 'Archivtext'],
                ],
            ])
            ->assertRedirect(route('newsletter.archiv.admin.edit', $ausgabe->fresh()));

        $this->assertDatabaseHas('newsletter_ausgaben', [
            'id' => $ausgabe->id,
            'subject' => 'Neue Ausgabe',
            'slug' => 'neue-ausgabe',
        ]);
    }

    #[TestWith(['Admin'])]
    #[TestWith(['Vorstand'])]
    public function test_authorized_roles_can_update_newsletter_archive_entry_images(string $role): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('newsletter-images/altbild.jpg', 'alt');

        $user = $this->actingMember($role);
        $ausgabe = NewsletterAusgabe::factory()->create([
            'topics' => [
                [
                    'key' => 'topic-1',
                    'title' => 'Mit Bild',
                    'content' => 'Alttext',
                    'images' => ['newsletter-images/altbild.jpg'],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->put(route('newsletter.archiv.admin.update', $ausgabe), [
                'subject' => 'Mit Bild aktualisiert',
                'slug' => 'mit-bild-aktualisiert',
                'recipient_roles' => ['Mitglied'],
                'sent_at' => '2026-05-17 12:00',
                'topics' => [
                    [
                        'key' => 'topic-1',
                        'title' => 'Mit Bild',
                        'content' => 'Neutext',
                        'remove_images' => ['newsletter-images/altbild.jpg'],
                        'images' => [UploadedFile::fake()->image('neu.jpg', 700, 500)],
                    ],
                ],
            ])
            ->assertRedirect(route('newsletter.archiv.admin.edit', $ausgabe->fresh()));

        $ausgabe->refresh();

        $this->assertSame('Mit Bild aktualisiert', $ausgabe->subject);
        $this->assertCount(1, $ausgabe->topics[0]['images']);
        $this->assertStringStartsWith(NewsletterImageService::STORAGE_PATH.'/', $ausgabe->topics[0]['images'][0]);
        $this->assertFalse(Storage::disk('public')->exists('newsletter-images/altbild.jpg'));
        $this->assertTrue(Storage::disk('public')->exists($ausgabe->topics[0]['images'][0]));
    }

    #[TestWith(['Admin'])]
    #[TestWith(['Vorstand'])]
    public function test_authorized_roles_preserve_images_when_legacy_topic_keys_are_rotated(string $role): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('newsletter-images/legacy.jpg', 'legacy');

        $user = $this->actingMember($role);
        $ausgabe = NewsletterAusgabe::factory()->create([
            'topics' => [
                [
                    'key' => 'legacy-topic-0',
                    'title' => 'Mit Legacy-Key',
                    'content' => 'Alttext',
                    'images' => ['newsletter-images/legacy.jpg'],
                ],
            ],
        ]);

        $this->actingAs($user)
            ->put(route('newsletter.archiv.admin.update', $ausgabe), [
                'subject' => 'Legacy aktualisiert',
                'slug' => 'legacy-aktualisiert',
                'recipient_roles' => ['Mitglied'],
                'sent_at' => '2026-05-17 12:00',
                'topics' => [
                    [
                        'key' => 'legacy-topic-0',
                        'title' => 'Mit Legacy-Key',
                        'content' => 'Neutext',
                    ],
                ],
            ])
            ->assertRedirect(route('newsletter.archiv.admin.edit', $ausgabe->fresh()));

        $ausgabe->refresh();

        $this->assertSame(['newsletter-images/legacy.jpg'], $ausgabe->topics[0]['images']);
        $this->assertNotSame('legacy-topic-0', $ausgabe->topics[0]['key']);
        $this->assertFalse(NewsletterTopics::usesLegacyKey($ausgabe->topics[0]['key']));
        $this->assertTrue(Storage::disk('public')->exists('newsletter-images/legacy.jpg'));
    }

    #[TestWith(['Admin'])]
    #[TestWith(['Vorstand'])]
    public function test_authorized_roles_can_update_newsletter_archive_entry_with_long_colliding_slug(string $role): void
    {
        $user = $this->actingMember($role);
        $longSlug = str_repeat('a', 255);
        NewsletterAusgabe::factory()->create([
            'slug' => $longSlug,
        ]);
        $ausgabe = NewsletterAusgabe::factory()->create([
            'slug' => 'kollision',
        ]);

        $this->actingAs($user)
            ->put(route('newsletter.archiv.admin.update', $ausgabe), [
                'subject' => 'Lange Kollision',
                'slug' => $longSlug,
                'recipient_roles' => ['Mitglied'],
                'sent_at' => '2026-05-17 12:00',
                'topics' => [
                    ['key' => 'topic-long', 'title' => 'Update', 'content' => 'Langer Slug mit Suffix'],
                ],
            ])
            ->assertRedirect(route('newsletter.archiv.admin.edit', $ausgabe->fresh()));

        $ausgabe->refresh();

        $this->assertLessThanOrEqual(255, strlen($ausgabe->slug));
        $this->assertStringEndsWith('-2', $ausgabe->slug);
    }

    #[TestWith(['Admin'])]
    #[TestWith(['Vorstand'])]
    public function test_authorized_roles_can_publish_newsletter_archive_entry(string $role): void
    {
        $user = $this->actingMember($role);
        $ausgabe = NewsletterAusgabe::factory()->create([
            'status' => NewsletterAusgabeStatus::Entwurf,
            'published_at' => null,
        ]);

        $this->actingAs($user)
            ->post(route('newsletter.archiv.admin.publish', $ausgabe))
            ->assertRedirect(route('newsletter.archiv.admin.edit', $ausgabe->fresh()));

        $ausgabe->refresh();

        $this->assertSame(NewsletterAusgabeStatus::Veroeffentlicht, $ausgabe->status);
        $this->assertNotNull($ausgabe->published_at);
    }

    #[TestWith(['Admin'])]
    #[TestWith(['Vorstand'])]
    public function test_authorized_roles_can_update_newsletter_archive_entry_without_sent_at(string $role): void
    {
        $user = $this->actingMember($role);
        $ausgabe = NewsletterAusgabe::factory()->create([
            'subject' => 'Ohne Versandzeit',
            'sent_at' => null,
        ]);

        $this->actingAs($user)
            ->put(route('newsletter.archiv.admin.update', $ausgabe), [
                'subject' => 'Ohne Versandzeit aktualisiert',
                'slug' => 'ohne-versandzeit-aktualisiert',
                'recipient_roles' => ['Mitglied'],
                'sent_at' => '',
                'topics' => [
                    ['key' => 'topic-ohne-sent-at', 'title' => 'Update', 'content' => 'Weiter ohne Zeitpunkt'],
                ],
            ])
            ->assertRedirect(route('newsletter.archiv.admin.edit', $ausgabe->fresh()));

        $ausgabe->refresh();

        $this->assertSame('Ohne Versandzeit aktualisiert', $ausgabe->subject);
        $this->assertNull($ausgabe->sent_at);
    }

    #[TestWith(['Admin'])]
    #[TestWith(['Vorstand'])]
    public function test_authorized_roles_cannot_update_newsletter_archive_entry_with_duplicate_topic_keys(string $role): void
    {
        $user = $this->actingMember($role);
        $ausgabe = NewsletterAusgabe::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('newsletter.archiv.admin.edit', $ausgabe))
            ->put(route('newsletter.archiv.admin.update', $ausgabe), [
                'subject' => 'Doppelte Keys',
                'slug' => 'doppelte-keys',
                'recipient_roles' => ['Mitglied'],
                'sent_at' => '2026-05-17 12:00',
                'topics' => [
                    ['key' => 'duplicate-key', 'title' => 'A', 'content' => 'B'],
                    ['key' => 'duplicate-key', 'title' => 'C', 'content' => 'D'],
                ],
            ]);

        $response->assertRedirect(route('newsletter.archiv.admin.edit', $ausgabe));
        $response->assertSessionHasErrors(['topics.1.key']);
    }

    public function test_navigation_builder_places_newsletter_archive_only_in_authenticated_verein_section(): void
    {
        $mitglied = $this->createUserWithRole('Mitglied');
        $builder = app(NavigationBuilder::class);

        $guestNavigation = $builder->build(null);
        $authNavigation = $builder->build($mitglied->load('teams', 'ownedTeams'));

        $guestVereinItems = $this->sectionItemTitles($guestNavigation, 'Verein');
        $authVereinItems = $this->sectionItemTitles($authNavigation, 'Verein');

        $this->assertNotContains('Newsletter-Archiv', $guestVereinItems);
        $this->assertContains('Newsletter-Archiv', $authVereinItems);
        $this->assertSame(
            array_search('Protokolle', $authVereinItems, true) - 1,
            array_search('Newsletter-Archiv', $authVereinItems, true)
        );
    }

    public function test_newsletter_ausgaben_generate_unique_slugs_from_subject(): void
    {
        $ersteAusgabe = NewsletterAusgabe::factory()->create([
            'subject' => 'Doppelter Betreff',
            'slug' => null,
        ]);
        $zweiteAusgabe = NewsletterAusgabe::factory()->create([
            'subject' => 'Doppelter Betreff',
            'slug' => null,
        ]);

        $this->assertSame('doppelter-betreff', $ersteAusgabe->slug);
        $this->assertSame('doppelter-betreff-2', $zweiteAusgabe->slug);
    }

    public function test_newsletter_ausgaben_truncate_generated_slugs_to_database_limit(): void
    {
        $longSubject = str_repeat('Extrem langer Newsletter Betreff ', 20);

        $ersteAusgabe = NewsletterAusgabe::factory()->create([
            'subject' => $longSubject,
            'slug' => null,
        ]);
        $zweiteAusgabe = NewsletterAusgabe::factory()->create([
            'subject' => $longSubject,
            'slug' => null,
        ]);

        $this->assertLessThanOrEqual(255, strlen($ersteAusgabe->slug));
        $this->assertLessThanOrEqual(255, strlen($zweiteAusgabe->slug));
        $this->assertStringEndsWith('-2', $zweiteAusgabe->slug);
    }

    /**
     * @param  array<string, mixed>  $navigation
     * @return array<int, string>
     */
    private function sectionItemTitles(array $navigation, string $sectionTitle): array
    {
        $section = collect($navigation['sections'] ?? [])->firstWhere('title', $sectionTitle);

        return collect($section['items'] ?? [])->pluck('title')->all();
    }
}