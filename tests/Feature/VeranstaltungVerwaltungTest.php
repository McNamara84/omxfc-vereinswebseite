<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\FantreffenAnmeldung;
use App\Models\Veranstaltung;
use App\Models\VeranstaltungsAbschnitt;
use App\Models\VeranstaltungsMerchartikel;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(VeranstaltungVerwaltungTestFixtures::class);

beforeEach(function (): void {
    Config::set('app.testing_minimal_layout', true);
});

test('admin can open event management index', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $response = $this->withoutVite()->actingAs($admin)->get(route('admin.veranstaltungen.index'));
    $response->assertOk();
    $response->assertSee('Veranstaltungen verwalten');
    $response->assertSee('Jubiläumsfeier zu Band 700');
});

test('regular member cannot open event management index', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $response = $this->actingAs($member)->get(route('admin.veranstaltungen.index'));
    $response->assertForbidden();
});

test('management user with other active team can open event management index', function (string $roleValue): void {
    $user = $this->createUserWithRoleAndDifferentCurrentTeam(Role::from($roleValue));
    $response = $this->withoutVite()->actingAs($user)->get(route('admin.veranstaltungen.index'));
    $response->assertOk();
    $response->assertSee('Veranstaltungen verwalten');
})->with([[Role::Admin->value], [Role::Vorstand->value]]);

test('management user with other active team can open event registration list', function (string $roleValue): void {
    $user = $this->createUserWithRoleAndDifferentCurrentTeam(Role::from($roleValue));
    $veranstaltung = Veranstaltung::query()->where('slug', 'jubilaeumsfeier-band-700')->firstOrFail();
    $response = $this->withoutVite()->actingAs($user)->get(route('admin.veranstaltungen.anmeldungen', $veranstaltung));
    $response->assertOk();
    $response->assertSee('Anmeldungen');
})->with([[Role::Admin->value], [Role::Vorstand->value]]);

test('event management index marks archived events as still accessible for registration lists', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $response = $this->withoutVite()->actingAs($admin)->get(route('admin.veranstaltungen.index'));
    $response->assertOk();
    $response->assertSee('Archivierte Veranstaltung');
    $response->assertSee('Anmeldeliste weiterhin verfügbar.');
});

test('admin can create event with structured data', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $response = $this->actingAs($admin)->post(route('admin.veranstaltungen.store'), ['titel' => 'Leserabend 2027', 'slug' => 'leserabend-2027', 'status' => 'veroeffentlicht', 'veranstaltungsart' => 'Leserabend', 'untertitel' => 'Ein Abend für die Community', 'teaser' => 'Testevent mit dynamischen Feldern.', 'beschreibung' => 'Beschreibung im Markdown-Format', 'datum_von' => '2027-02-20 19:30:00', 'ort_name' => 'Bürgerzentrum Köln', 'ort_adresse' => 'Köln', 'anmeldung_aktiv' => '1', 'zahlung_aktiv' => '1', 'merch_deadline' => '2027-02-01 18:00:00', 'gastgebuehr' => '12.50', 'ist_highlight' => '1']);
    $veranstaltung = Veranstaltung::query()->where('slug', 'leserabend-2027')->first();
    $response->assertRedirect(route('admin.veranstaltungen.edit', $veranstaltung));
    expect($veranstaltung)->not->toBeNull()
        ->and($veranstaltung->titel)->toBe('Leserabend 2027')
        ->and($veranstaltung->anmeldung_aktiv)->toBeTrue()
        ->and($veranstaltung->zahlung_aktiv)->toBeTrue()
        ->and($veranstaltung->ist_highlight)->toBeTrue()
        ->and($veranstaltung->merch_deadline?->format('Y-m-d H:i:s'))->toBe('2027-02-01 18:00:00')
        ->and((float) $veranstaltung->tshirt_preis)->toBe(FantreffenAnmeldung::TSHIRT_PRICE)
        ->and(Veranstaltung::query()->where('slug', 'jubilaeumsfeier-band-700')->firstOrFail()->ist_highlight)->toBeFalse();
});

test('admin can add merchandise article without description', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $veranstaltung = Veranstaltung::query()->where('slug', 'jubilaeumsfeier-band-700')->firstOrFail();
    $this->actingAs($admin)->post(route('admin.veranstaltungen.merch.store', $veranstaltung), ['bezeichnung' => 'Poster', 'preis' => '7.50', 'sort_order' => 4, 'is_active' => '1'])->assertRedirect(route('admin.veranstaltungen.edit', $veranstaltung));
    $artikel = VeranstaltungsMerchartikel::query()->where('veranstaltung_id', $veranstaltung->id)->where('bezeichnung', 'Poster')->first();
    expect($artikel)->not->toBeNull()
        ->and($artikel->beschreibung)->toBeNull();
});

test('admin can add update and delete merchandise article with variants', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $veranstaltung = Veranstaltung::query()->where('slug', 'jubilaeumsfeier-band-700')->firstOrFail();
    $this->actingAs($admin)->post(route('admin.veranstaltungen.merch.store', $veranstaltung), ['bezeichnung' => 'Stoffbeutel', 'beschreibung' => 'Limitierter Beutel.', 'preis' => '12.50', 'sort_order' => 2, 'varianten' => "Rot\nBlau", 'is_active' => '1'])->assertRedirect(route('admin.veranstaltungen.edit', $veranstaltung));
    $artikel = VeranstaltungsMerchartikel::query()->where('veranstaltung_id', $veranstaltung->id)->where('bezeichnung', 'Stoffbeutel')->first();
    expect($artikel)->not->toBeNull()
        ->and($artikel->varianten()->count())->toBe(2);
    $this->actingAs($admin)->put(route('admin.veranstaltungen.merch.update', [$veranstaltung, $artikel]), ['bezeichnung' => 'Sammlerbeutel', 'beschreibung' => 'Aktualisierte Beschreibung.', 'preis' => '15.00', 'sort_order' => 1, 'varianten' => "Schwarz\nWeiß", 'is_active' => '1'])->assertRedirect(route('admin.veranstaltungen.edit', $veranstaltung));
    $artikel->refresh();
    expect($artikel->bezeichnung)->toBe('Sammlerbeutel')
        ->and($artikel->preis)->toBe('15.00')
        ->and($artikel->is_active)->toBeTrue()
        ->and($artikel->varianten()->where('is_active', true)->count())->toBe(2)
        ->and($artikel->varianten()->where('bezeichnung', 'Schwarz')->exists())->toBeTrue();
    $this->actingAs($admin)->put(route('admin.veranstaltungen.merch.update', [$veranstaltung, $artikel]), ['bezeichnung' => 'Sammlerbeutel', 'beschreibung' => 'Aktualisierte Beschreibung.', 'preis' => '15.00', 'sort_order' => 1, 'varianten' => "Schwarz\nWeiß", 'is_active' => '0'])->assertRedirect(route('admin.veranstaltungen.edit', $veranstaltung));
    $artikel->refresh();
    expect($artikel->is_active)->toBeFalse();
    $this->actingAs($admin)->delete(route('admin.veranstaltungen.merch.destroy', [$veranstaltung, $artikel]))->assertRedirect(route('admin.veranstaltungen.edit', $veranstaltung));
    $this->assertDatabaseMissing('veranstaltungs_merchartikel', ['id' => $artikel->id]);
});

test('invalid merch update does not repopulate all article forms with old values', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $veranstaltung = Veranstaltung::query()->where('slug', 'jubilaeumsfeier-band-700')->firstOrFail();
    $erstesArtikel = $veranstaltung->merchartikel()->create(['bezeichnung' => 'Erster Artikel', 'beschreibung' => 'Bestehende Beschreibung eins.', 'preis' => 9.5, 'sort_order' => 1, 'is_active' => true]);
    $zweitesArtikel = $veranstaltung->merchartikel()->create(['bezeichnung' => 'Zweiter Artikel', 'beschreibung' => 'Bestehende Beschreibung zwei.', 'preis' => 11.0, 'sort_order' => 2, 'is_active' => true]);
    $response = $this->withoutVite()->followingRedirects()->from(route('admin.veranstaltungen.edit', $veranstaltung))->actingAs($admin)->put(route('admin.veranstaltungen.merch.update', [$veranstaltung, $erstesArtikel]), ['merch_form_id' => (string) $erstesArtikel->id, 'bezeichnung' => '', 'beschreibung' => 'Fehlerhafte Zwischenbeschreibung', 'preis' => '14.00', 'sort_order' => 3, 'varianten' => "Fehlerhafte Variante A\nFehlerhafte Variante B", 'is_active' => '1']);
    $response->assertSee('Zweiter Artikel');
    $content = $response->getContent();
    expect($content)->toBeString()
        ->and(preg_match('/action="[^"]*\/merch\/'.$erstesArtikel->id.'".*?name="bezeichnung" value=""/s', $content))->toBe(1)
        ->and(preg_match('/action="[^"]*\/merch\/'.$erstesArtikel->id.'".*?Fehlerhafte Variante A/s', $content))->toBe(1)
        ->and(preg_match('/action="[^"]*\/merch\/'.$erstesArtikel->id.'".*?Fehlerhafte Zwischenbeschreibung/s', $content))->toBe(1)
        ->and(preg_match('/action="[^"]*\/merch\/'.$zweitesArtikel->id.'".*?Fehlerhafte Variante A/s', $content))->toBe(0);
});

test('admin can add and update markdown section', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $veranstaltung = Veranstaltung::query()->where('slug', 'jubilaeumsfeier-band-700')->firstOrFail();
    $this->actingAs($admin)->post(route('admin.veranstaltungen.abschnitte.store', $veranstaltung), ['titel' => 'FAQ', 'schluessel' => 'faq', 'markdown_inhalt' => "## Frage\nAntwort", 'sort_order' => 8, 'is_visible' => '1'])->assertRedirect(route('admin.veranstaltungen.edit', $veranstaltung));
    $abschnitt = VeranstaltungsAbschnitt::query()->where('veranstaltung_id', $veranstaltung->id)->where('schluessel', 'faq')->first();
    expect($abschnitt)->not->toBeNull();
    $this->actingAs($admin)->put(route('admin.veranstaltungen.abschnitte.update', [$veranstaltung, $abschnitt]), ['titel' => 'FAQ aktualisiert', 'schluessel' => 'faq', 'markdown_inhalt' => "## Neue Frage\nNeue Antwort", 'sort_order' => 3, 'is_visible' => '1'])->assertRedirect(route('admin.veranstaltungen.edit', $veranstaltung));
    $abschnitt->refresh();
    expect($abschnitt->titel)->toBe('FAQ aktualisiert')
        ->and($abschnitt->sort_order)->toBe(3)
        ->and($abschnitt->markdown_inhalt)->toContain('Neue Antwort');
});
