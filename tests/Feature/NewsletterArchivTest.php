<?php

namespace Tests\Feature;

use App\Enums\NewsletterAusgabeStatus;
use App\Models\NewsletterAusgabe;
use App\Models\User;
use App\Support\Navigation\NavigationBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
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
        $mitglied = $this->actingMember();
        $ausgabe = NewsletterAusgabe::factory()->published()->create([
            'subject' => 'Detailausgabe',
            'topics' => [
                [
                    'title' => 'Rundschau',
                    'content' => "Erste Zeile\nZweite Zeile",
                ],
            ],
        ]);

        $this->actingAs($mitglied)
            ->get(route('newsletter.archiv.show', $ausgabe))
            ->assertOk()
            ->assertSee('Detailausgabe')
            ->assertSee('Rundschau')
            ->assertSee('Erste Zeile<br />', false)
            ->assertSee('Zweite Zeile');
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

    public function test_admin_can_view_newsletter_archive_admin_index(): void
    {
        $admin = $this->actingMember('Admin');

        NewsletterAusgabe::factory()->create(['subject' => 'Archiv Entwurf']);
        NewsletterAusgabe::factory()->published()->create(['subject' => 'Archiv Live']);

        $this->actingAs($admin)
            ->get(route('newsletter.archiv.admin.index'))
            ->assertOk()
            ->assertSee('Archiv Entwurf')
            ->assertSee('Archiv Live');
    }

    public function test_admin_can_view_newsletter_archive_edit_form(): void
    {
        $admin = $this->actingMember('Admin');
        $ausgabe = NewsletterAusgabe::factory()->create([
            'subject' => 'Bearbeitbare Ausgabe',
        ]);

        $this->actingAs($admin)
            ->get(route('newsletter.archiv.admin.edit', $ausgabe))
            ->assertOk()
            ->assertSee('Newsletter-Ausgabe bearbeiten')
            ->assertSee('Bearbeitbare Ausgabe')
            ->assertSee('Themenblöcke');
    }

    public function test_admin_can_update_newsletter_archive_entry(): void
    {
        $admin = $this->actingMember('Admin');
        $ausgabe = NewsletterAusgabe::factory()->create([
            'subject' => 'Alte Ausgabe',
            'slug' => 'alte-ausgabe',
        ]);

        $this->actingAs($admin)
            ->put(route('newsletter.archiv.admin.update', $ausgabe), [
                'subject' => 'Neue Ausgabe',
                'slug' => 'neue-ausgabe',
                'recipient_roles' => ['Mitglied', 'Vorstand'],
                'sent_at' => '2026-05-17 12:00',
                'topics' => [
                    ['title' => 'Update', 'content' => 'Archivtext'],
                ],
            ])
            ->assertRedirect(route('newsletter.archiv.admin.edit', $ausgabe->fresh()));

        $this->assertDatabaseHas('newsletter_ausgaben', [
            'id' => $ausgabe->id,
            'subject' => 'Neue Ausgabe',
            'slug' => 'neue-ausgabe',
        ]);
    }

    public function test_admin_can_update_newsletter_archive_entry_with_long_colliding_slug(): void
    {
        $admin = $this->actingMember('Admin');
        $longSlug = str_repeat('a', 255);
        NewsletterAusgabe::factory()->create([
            'slug' => $longSlug,
        ]);
        $ausgabe = NewsletterAusgabe::factory()->create([
            'slug' => 'kollision',
        ]);

        $this->actingAs($admin)
            ->put(route('newsletter.archiv.admin.update', $ausgabe), [
                'subject' => 'Lange Kollision',
                'slug' => $longSlug,
                'recipient_roles' => ['Mitglied'],
                'sent_at' => '2026-05-17 12:00',
                'topics' => [
                    ['title' => 'Update', 'content' => 'Langer Slug mit Suffix'],
                ],
            ])
            ->assertRedirect(route('newsletter.archiv.admin.edit', $ausgabe->fresh()));

        $ausgabe->refresh();

        $this->assertLessThanOrEqual(255, strlen($ausgabe->slug));
        $this->assertStringEndsWith('-2', $ausgabe->slug);
    }

    public function test_admin_can_publish_newsletter_archive_entry(): void
    {
        $admin = $this->actingMember('Admin');
        $ausgabe = NewsletterAusgabe::factory()->create([
            'status' => NewsletterAusgabeStatus::Entwurf,
            'published_at' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('newsletter.archiv.admin.publish', $ausgabe))
            ->assertRedirect(route('newsletter.archiv.admin.edit', $ausgabe->fresh()));

        $ausgabe->refresh();

        $this->assertSame(NewsletterAusgabeStatus::Veroeffentlicht, $ausgabe->status);
        $this->assertNotNull($ausgabe->published_at);
    }

    public function test_admin_can_update_newsletter_archive_entry_without_sent_at(): void
    {
        $admin = $this->actingMember('Admin');
        $ausgabe = NewsletterAusgabe::factory()->create([
            'subject' => 'Ohne Versandzeit',
            'sent_at' => null,
        ]);

        $this->actingAs($admin)
            ->put(route('newsletter.archiv.admin.update', $ausgabe), [
                'subject' => 'Ohne Versandzeit aktualisiert',
                'slug' => 'ohne-versandzeit-aktualisiert',
                'recipient_roles' => ['Mitglied'],
                'sent_at' => '',
                'topics' => [
                    ['title' => 'Update', 'content' => 'Weiter ohne Zeitpunkt'],
                ],
            ])
            ->assertRedirect(route('newsletter.archiv.admin.edit', $ausgabe->fresh()));

        $ausgabe->refresh();

        $this->assertSame('Ohne Versandzeit aktualisiert', $ausgabe->subject);
        $this->assertNull($ausgabe->sent_at);
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