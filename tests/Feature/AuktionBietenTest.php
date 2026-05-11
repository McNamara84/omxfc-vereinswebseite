<?php

namespace Tests\Feature;

use App\Enums\AuktionsStatus;
use App\Enums\Role;
use App\Models\Auktion;
use App\Models\AuktionGebot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class AuktionBietenTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.testing_minimal_layout', true);
    }

    #[TestWith([Role::Mitglied])]
    #[TestWith([Role::Ehrenmitglied])]
    #[TestWith([Role::Mitwirkender])]
    public function test_eligible_roles_can_place_bids(Role $role): void
    {
        $user = $this->createUserWithRole($role);
        $auktion = Auktion::factory()->create([
            'startbetrag_cent' => 1000,
            'mindestschritt_cent' => 200,
        ]);

        $response = $this->actingAs($user)->post(route('auktionen.gebote.store', $auktion), [
            'betrag' => '10.00',
        ]);

        $response->assertRedirect(route('auktionen.show', $auktion));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('auktion_gebote', [
            'auktion_id' => $auktion->id,
            'user_id' => $user->id,
            'bieter_name' => $user->name,
            'betrag_cent' => 1000,
        ]);
    }

    #[TestWith([Role::Admin])]
    #[TestWith([Role::Vorstand])]
    #[TestWith([Role::Kassenwart])]
    public function test_administrative_roles_cannot_place_bids(Role $role): void
    {
        $user = $this->createUserWithRole($role);
        $auktion = Auktion::factory()->create();

        $response = $this->actingAs($user)->post(route('auktionen.gebote.store', $auktion), [
            'betrag' => '12.00',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('auktion_gebote', 0);
    }

    public function test_first_bid_must_meet_start_amount(): void
    {
        $member = $this->createUserWithRole(Role::Mitglied);
        $auktion = Auktion::factory()->create([
            'startbetrag_cent' => 1500,
            'mindestschritt_cent' => 100,
        ]);

        $response = $this->from(route('auktionen.show', $auktion))
            ->actingAs($member)
            ->post(route('auktionen.gebote.store', $auktion), [
                'betrag' => '14.00',
            ]);

        $response->assertRedirect(route('auktionen.show', $auktion));
        $response->assertSessionHasErrors('betrag');
        $this->assertDatabaseCount('auktion_gebote', 0);
    }

    #[TestWith(['1e3'])]
    #[TestWith(['10.999'])]
    public function test_bid_rejects_invalid_money_format(string $betrag): void
    {
        $member = $this->createUserWithRole(Role::Mitglied);
        $auktion = Auktion::factory()->create([
            'startbetrag_cent' => 1000,
            'mindestschritt_cent' => 100,
        ]);

        $response = $this->from(route('auktionen.show', $auktion))
            ->actingAs($member)
            ->post(route('auktionen.gebote.store', $auktion), [
                'betrag' => $betrag,
            ]);

        $response->assertRedirect(route('auktionen.show', $auktion));
        $response->assertSessionHasErrors('betrag');
        $this->assertSame('Bitte gib einen gültigen Euro-Betrag mit maximal zwei Nachkommastellen ein.', $response->getSession()->get('errors')->get('betrag')[0]);
        $this->assertDatabaseCount('auktion_gebote', 0);
    }

    public function test_follow_up_bid_must_reach_current_highest_plus_minimum_step(): void
    {
        $firstBidder = $this->createUserWithRole(Role::Mitglied);
        $secondBidder = $this->createUserWithRole(Role::Ehrenmitglied);
        $auktion = Auktion::factory()->create([
            'startbetrag_cent' => 1000,
            'mindestschritt_cent' => 200,
        ]);

        AuktionGebot::factory()->for($auktion)->for($firstBidder)->create([
            'bieter_name' => $firstBidder->name,
            'betrag_cent' => 1500,
        ]);

        $response = $this->from(route('auktionen.show', $auktion))
            ->actingAs($secondBidder)
            ->post(route('auktionen.gebote.store', $auktion), [
                'betrag' => '16.00',
            ]);

        $response->assertRedirect(route('auktionen.show', $auktion));
        $response->assertSessionHasErrors('betrag');
        $this->assertDatabaseCount('auktion_gebote', 1);

        $this->actingAs($secondBidder)
            ->post(route('auktionen.gebote.store', $auktion), [
                'betrag' => '17.00',
            ])
            ->assertRedirect(route('auktionen.show', $auktion))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('auktion_gebote', [
            'auktion_id' => $auktion->id,
            'user_id' => $secondBidder->id,
            'betrag_cent' => 1700,
        ]);
    }

    public function test_new_bid_after_zum_ersten_or_zum_zweiten_resets_status_to_laufend(): void
    {
        $member = $this->createUserWithRole(Role::Mitglied);
        $auktion = Auktion::factory()->create([
            'status' => AuktionsStatus::ZumZweiten,
            'startbetrag_cent' => 1000,
            'mindestschritt_cent' => 100,
        ]);

        $this->actingAs($member)
            ->post(route('auktionen.gebote.store', $auktion), [
                'betrag' => '10.00',
            ])
            ->assertRedirect(route('auktionen.show', $auktion))
            ->assertSessionHasNoErrors();

        $this->assertSame(AuktionsStatus::Laufend, $auktion->fresh()->status);
    }

    #[TestWith([AuktionsStatus::Verkauft])]
    #[TestWith([AuktionsStatus::NichtVerkauft])]
    public function test_closed_auctions_reject_new_bids(AuktionsStatus $status): void
    {
        $member = $this->createUserWithRole(Role::Mitglied);
        $auktion = Auktion::factory()->create([
            'status' => $status,
        ]);

        $response = $this->actingAs($member)->post(route('auktionen.gebote.store', $auktion), [
            'betrag' => '20.00',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('auktion_gebote', 0);
    }
}
