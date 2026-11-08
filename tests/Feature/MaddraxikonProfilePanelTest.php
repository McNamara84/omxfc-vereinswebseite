<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MaddraxikonAccountLinkStatus;
use App\Enums\MaddraxikonContributionStatus;
use App\Enums\MaddraxikonContributionType;
use App\Enums\Role;
use App\Livewire\Profile\MaddraxikonAccountPanel;
use App\Mail\MaddraxikonAccountLinked;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MaddraxikonProfilePanelTestFixtures::class);

beforeEach(function (): void {
    config(['maddraxikon.features.linking_enabled' => true, 'maddraxikon.base_url' => 'https://de.maddraxikon.com', 'maddraxikon.timezone' => 'Europe/Berlin', 'maddraxikon.wiki_key' => 'test-wiki', 'maddraxikon.consent_version' => 'ratings-consent-v2']);
});

test('profile settings render opt in panel for eligible member', function (): void {
    $member = $this->createMember();
    $this->actingAs($member)->get(route('profile.show'))->assertOk()->assertSeeLivewire(MaddraxikonAccountPanel::class)->assertSee('Maddraxikon & Baxx')->assertSee('Mit Maddraxikon verbinden')->assertSee('OAuth-Subject-ID')->assertSee('Sperrstatus')->assertSee('dauerhaft gespeichert');
});

test('privacy notice separates temporary and persisted oauth data', function (): void {
    $this->get(route('datenschutz'))->assertOk()->assertSee('nur kurzzeitig')->assertSee('opake OAuth-Subject-ID')->assertSee('lokale numerische Wiki-Nutzer-ID')->assertSee('beide Sperrstatus werden nicht dauerhaft gespeichert')->assertSee('kanonische Benutzername einer aktiven Maddraxikon-Verknüpfung')->assertSee('separate Kontaktfreigabe')->assertSee('Profilfreigabe automatisch deaktiviert');
});

test('panel shows active link and only own contributions', function (): void {
    $member = $this->createMember();
    $otherMember = $this->createMember();
    $link = MaddraxikonAccountLink::factory()->for($member)->create(['oauth_subject' => '42', 'wiki_user_id' => 42, 'wiki_username' => 'Wiki Mitglied']);
    $otherLink = MaddraxikonAccountLink::factory()->for($otherMember)->create();
    MaddraxikonContribution::factory()->for($link, 'accountLink')->for($member)->create(['page_title' => 'Eigener Artikel', 'revision_id' => 1234, 'status' => MaddraxikonContributionStatus::Rejected, 'type' => MaddraxikonContributionType::Edit]);
    MaddraxikonContribution::factory()->for($otherLink, 'accountLink')->for($otherMember)->create(['page_title' => 'Fremder privater Artikel', 'revision_id' => 9876]);
    Livewire::actingAs($member)->test(MaddraxikonAccountPanel::class)->assertSee('Verifiziert')->assertSee('Wiki Mitglied')->assertSee('Eigener Artikel')->assertSee('Abgelehnt: 1')->assertSee('steuerst du separat unter „Kontaktfreigabe“')->assertSee('Profilfreigabe wird')->assertSee('https://de.maddraxikon.com/index.php?diff=1234', escape: false)->assertDontSee('Fremder privater Artikel');
});

test('legacy active link offers explicit ratings reconsent', function (): void {
    $member = $this->createMember();
    MaddraxikonAccountLink::factory()->for($member)->create(['consent_version' => 'legacy-consent']);
    Livewire::actingAs($member)->test(MaddraxikonAccountPanel::class)->assertSee('erneute ausdrückliche Zustimmung')->assertSee('keine Bewertungen abgerufen, gespeichert oder angezeigt')->assertSee('Zustimmung zu Romanbewertungen aktualisieren')->assertSeeHtml('action="'.route('maddraxikon.oauth.start').'"')->assertSeeHtml('id="maddraxikon-ratings-reconsent"')->assertSeeHtml('name="consent"');
});

test('current consent needs no reconsent and wrong wiki is blocked', function (): void {
    $member = $this->createMember();
    $link = MaddraxikonAccountLink::factory()->for($member)->create();
    Livewire::actingAs($member)->test(MaddraxikonAccountPanel::class)->assertDontSee('Zustimmung zu Romanbewertungen aktualisieren');
    $link->update(['wiki_key' => 'another-wiki']);
    Livewire::actingAs($member)->test(MaddraxikonAccountPanel::class)->assertSee('aktuell konfigurierten')->assertSee('Maddraxikon-Instanz. Deshalb')->assertSee('Romanbewertungen abgerufen, gespeichert oder angezeigt')->assertDontSee('Zustimmung zu Romanbewertungen aktualisieren');
});

test('external wiki values are escaped in panel', function (): void {
    $member = $this->createMember();
    $link = MaddraxikonAccountLink::factory()->for($member)->create(['wiki_username' => '<script>alert(1)</script>']);
    MaddraxikonContribution::factory()->for($link, 'accountLink')->for($member)->create(['page_title' => '<img src=x onerror=alert(2)>']);
    Livewire::actingAs($member)->test(MaddraxikonAccountPanel::class)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', escape: false)->assertSee('&lt;img src=x onerror=alert(2)&gt;', escape: false)->assertDontSee('<script>alert(1)</script>', escape: false)->assertDontSee('<img src=x onerror=alert(2)>', escape: false);
});

test('disconnected link offers reverification and keeps history visible', function (): void {
    $member = $this->createMember();
    $link = MaddraxikonAccountLink::factory()->disconnected()->for($member)->create(['wiki_username' => 'Getrenntes Konto']);
    MaddraxikonContribution::factory()->for($link, 'accountLink')->for($member)->create(['page_title' => 'Früherer Beitrag']);
    Livewire::actingAs($member)->test(MaddraxikonAccountPanel::class)->assertSee('Die frühere Verbindung mit')->assertSee('Getrenntes Konto')->assertSee('Mit Maddraxikon verbinden')->assertSee('Früherer Beitrag');
});

test('ineligible user sees no link action', function (): void {
    $applicant = $this->createMember(Role::Anwaerter);
    Livewire::actingAs($applicant)->test(MaddraxikonAccountPanel::class)->assertSee('aktiven Vereinsmitgliedern')->assertDontSee('Mit Maddraxikon verbinden');
});

test('public profile uses verified canonical name only when contact release is enabled', function (): void {
    $viewer = $this->createMember();
    $target = $this->createMember(attributes: ['contact_release_maddraxikon' => true]);
    MaddraxikonAccountLink::factory()->for($target)->create(['wiki_username' => 'Kanonischer Wiki-Name']);
    $this->actingAs($viewer)->get(route('profile.view', $target))->assertOk()->assertSee('Kanonischer Wiki-Name')->assertSee('Benutzer:Kanonischer_Wiki-Name', escape: false);
    $target->forceFill(['contact_release_maddraxikon' => false])->save();
    $this->get(route('profile.view', $target))->assertOk()->assertDontSee('Kanonischer Wiki-Name');
});

test('information mail contains no internal identity or token data', function (): void {
    $mail = new MaddraxikonAccountLinked(wikiUsername: 'Wiki Mitglied', verifiedAt: CarbonImmutable::parse('2026-07-18 12:30:00', 'Europe/Berlin'));
    $html = $mail->render();
    expect($html)->toContain('Wiki Mitglied')
        ->toContain('Verknüpfung verwalten')
        ->toContain('weder angefordert noch gespeichert')->not->toContain('oauth_subject')->not->toContain('access_token')->not->toContain('refresh_token')->not->toContain('wiki_user_id');
});

test('information mail formats spring dst in maddraxikon timezone', function (): void {
    $mail = new MaddraxikonAccountLinked(wikiUsername: 'Wiki Mitglied', verifiedAt: CarbonImmutable::parse('2026-03-29 01:30:00', 'UTC'));
    $html = $mail->render();
    expect($html)->toContain('03:30 Uhr')->not->toContain('01:30 Uhr');
});

test('inactive link never exposes a profile contact', function (): void {
    $viewer = $this->createMember();
    $target = $this->createMember(attributes: ['contact_release_maddraxikon' => true]);
    MaddraxikonAccountLink::factory()->disconnected()->for($target)->create(['wiki_username' => 'Nicht mehr aktiver Name', 'status' => MaddraxikonAccountLinkStatus::Disconnected]);
    $this->actingAs($viewer)->get(route('profile.view', $target))->assertOk()->assertDontSee('Nicht mehr aktiver Name');
});
