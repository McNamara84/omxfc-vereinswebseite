<?php

namespace Tests\Feature;

use App\Enums\MaddraxikonAccountLinkStatus;
use App\Enums\MaddraxikonContributionStatus;
use App\Enums\MaddraxikonContributionType;
use App\Enums\Role;
use App\Livewire\Profile\MaddraxikonAccountPanel;
use App\Mail\MaddraxikonAccountLinked;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaddraxikonProfilePanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'maddraxikon.features.linking_enabled' => true,
            'maddraxikon.base_url' => 'https://de.maddraxikon.com',
            'maddraxikon.timezone' => 'Europe/Berlin',
        ]);
    }

    public function test_profile_settings_render_opt_in_panel_for_eligible_member(): void
    {
        $member = $this->createMember();

        $this->actingAs($member)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSeeLivewire(MaddraxikonAccountPanel::class)
            ->assertSee('Maddraxikon & Baxx')
            ->assertSee('Mit Maddraxikon verbinden')
            ->assertSee('OAuth-Subject-ID')
            ->assertSee('Sperrstatus')
            ->assertSee('dauerhaft gespeichert');
    }

    public function test_privacy_notice_separates_temporary_and_persisted_oauth_data(): void
    {
        $this->get(route('datenschutz'))
            ->assertOk()
            ->assertSee('nur kurzzeitig')
            ->assertSee('opake OAuth-Subject-ID')
            ->assertSee('lokale numerische Wiki-Nutzer-ID')
            ->assertSee('beide Sperrstatus werden nicht dauerhaft gespeichert');
    }

    public function test_panel_shows_active_link_and_only_own_contributions(): void
    {
        $member = $this->createMember();
        $otherMember = $this->createMember();
        $link = MaddraxikonAccountLink::factory()->for($member)->create([
            'oauth_subject' => '42',
            'wiki_user_id' => 42,
            'wiki_username' => 'Wiki Mitglied',
        ]);
        $otherLink = MaddraxikonAccountLink::factory()->for($otherMember)->create();

        MaddraxikonContribution::factory()
            ->for($link, 'accountLink')
            ->for($member)
            ->create([
                'page_title' => 'Eigener Artikel',
                'revision_id' => 1234,
                'status' => MaddraxikonContributionStatus::Rejected,
                'type' => MaddraxikonContributionType::Edit,
            ]);

        MaddraxikonContribution::factory()
            ->for($otherLink, 'accountLink')
            ->for($otherMember)
            ->create([
                'page_title' => 'Fremder privater Artikel',
                'revision_id' => 9876,
            ]);

        Livewire::actingAs($member)
            ->test(MaddraxikonAccountPanel::class)
            ->assertSee('Verifiziert')
            ->assertSee('Wiki Mitglied')
            ->assertSee('Eigener Artikel')
            ->assertSee('Abgelehnt: 1')
            ->assertSee('https://de.maddraxikon.com/index.php?diff=1234', escape: false)
            ->assertDontSee('Fremder privater Artikel');
    }

    public function test_external_wiki_values_are_escaped_in_panel(): void
    {
        $member = $this->createMember();
        $link = MaddraxikonAccountLink::factory()->for($member)->create([
            'wiki_username' => '<script>alert(1)</script>',
        ]);

        MaddraxikonContribution::factory()
            ->for($link, 'accountLink')
            ->for($member)
            ->create([
                'page_title' => '<img src=x onerror=alert(2)>',
            ]);

        Livewire::actingAs($member)
            ->test(MaddraxikonAccountPanel::class)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', escape: false)
            ->assertSee('&lt;img src=x onerror=alert(2)&gt;', escape: false)
            ->assertDontSee('<script>alert(1)</script>', escape: false)
            ->assertDontSee('<img src=x onerror=alert(2)>', escape: false);
    }

    public function test_disconnected_link_offers_reverification_and_keeps_history_visible(): void
    {
        $member = $this->createMember();
        $link = MaddraxikonAccountLink::factory()->disconnected()->for($member)->create([
            'wiki_username' => 'Getrenntes Konto',
        ]);
        MaddraxikonContribution::factory()
            ->for($link, 'accountLink')
            ->for($member)
            ->create(['page_title' => 'Früherer Beitrag']);

        Livewire::actingAs($member)
            ->test(MaddraxikonAccountPanel::class)
            ->assertSee('Die frühere Verbindung mit')
            ->assertSee('Getrenntes Konto')
            ->assertSee('Mit Maddraxikon verbinden')
            ->assertSee('Früherer Beitrag');
    }

    public function test_ineligible_user_sees_no_link_action(): void
    {
        $applicant = $this->createMember(Role::Anwaerter);

        Livewire::actingAs($applicant)
            ->test(MaddraxikonAccountPanel::class)
            ->assertSee('aktiven Vereinsmitgliedern')
            ->assertDontSee('Mit Maddraxikon verbinden');
    }

    public function test_public_profile_uses_verified_canonical_name_only_when_contact_release_is_enabled(): void
    {
        $viewer = $this->createMember();
        $target = $this->createMember(attributes: [
            'contact_release_maddraxikon' => true,
            'maddraxikon_username' => 'Unverifizierter Altname',
        ]);
        MaddraxikonAccountLink::factory()->for($target)->create([
            'wiki_username' => 'Kanonischer Wiki-Name',
        ]);

        $this->actingAs($viewer)
            ->get(route('profile.view', $target))
            ->assertOk()
            ->assertSee('Kanonischer Wiki-Name')
            ->assertSee('Benutzer:Kanonischer_Wiki-Name', escape: false)
            ->assertDontSee('Unverifizierter Altname');

        $target->forceFill(['contact_release_maddraxikon' => false])->save();

        $this->get(route('profile.view', $target))
            ->assertOk()
            ->assertDontSee('Kanonischer Wiki-Name')
            ->assertDontSee('Unverifizierter Altname');
    }

    public function test_information_mail_contains_no_internal_identity_or_token_data(): void
    {
        $mail = new MaddraxikonAccountLinked(
            wikiUsername: 'Wiki Mitglied',
            verifiedAt: CarbonImmutable::parse('2026-07-18 12:30:00', 'Europe/Berlin'),
        );

        $html = $mail->render();

        $this->assertStringContainsString('Wiki Mitglied', $html);
        $this->assertStringContainsString('Verknüpfung verwalten', $html);
        $this->assertStringContainsString('weder angefordert noch gespeichert', $html);
        $this->assertStringNotContainsString('oauth_subject', $html);
        $this->assertStringNotContainsString('access_token', $html);
        $this->assertStringNotContainsString('refresh_token', $html);
        $this->assertStringNotContainsString('wiki_user_id', $html);
    }

    public function test_information_mail_formats_spring_dst_in_maddraxikon_timezone(): void
    {
        $mail = new MaddraxikonAccountLinked(
            wikiUsername: 'Wiki Mitglied',
            verifiedAt: CarbonImmutable::parse(
                '2026-03-29 01:30:00',
                'UTC',
            ),
        );

        $html = $mail->render();

        $this->assertStringContainsString('03:30 Uhr', $html);
        $this->assertStringNotContainsString('01:30 Uhr', $html);
    }

    public function test_inactive_link_does_not_override_legacy_public_contact_name(): void
    {
        $viewer = $this->createMember();
        $target = $this->createMember(attributes: [
            'contact_release_maddraxikon' => true,
            'maddraxikon_username' => 'Bewusst freigegebener Altname',
        ]);
        MaddraxikonAccountLink::factory()->disconnected()->for($target)->create([
            'wiki_username' => 'Nicht mehr aktiver Name',
            'status' => MaddraxikonAccountLinkStatus::Disconnected,
        ]);

        $this->actingAs($viewer)
            ->get(route('profile.view', $target))
            ->assertOk()
            ->assertSee('Bewusst freigegebener Altname')
            ->assertDontSee('Nicht mehr aktiver Name');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createMember(
        Role $role = Role::Mitglied,
        array $attributes = [],
    ): User {
        $team = Team::membersTeam();
        $user = User::factory()->create(array_merge([
            'current_team_id' => $team->id,
        ], $attributes));
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }
}
