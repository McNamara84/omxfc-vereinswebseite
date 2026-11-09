<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuktionsStatus;
use App\Enums\Role;
use App\Models\Auktion;
use App\Models\AuktionGebot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('app.testing_minimal_layout', true);
});

test('eligible roles can place bids', function (Role $role): void {
    $user = $this->createUserWithRole($role);
    $auktion = Auktion::factory()->create(['startbetrag_cent' => 1000, 'mindestschritt_cent' => 200]);
    $response = $this->actingAs($user)->post(route('auktionen.gebote.store', $auktion), ['betrag' => '10.00']);
    $response->assertRedirect(route('auktionen.show', $auktion));
    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('auktion_gebote', ['auktion_id' => $auktion->id, 'user_id' => $user->id, 'bieter_name' => $user->name, 'betrag_cent' => 1000]);
})->with([[Role::Mitglied], [Role::Ehrenmitglied], [Role::Mitwirkender], [Role::Admin], [Role::Vorstand], [Role::Kassenwart]]);

test('anwaerter cannot place bids', function (): void {
    $user = $this->createUserWithRole(Role::Anwaerter);
    $auktion = Auktion::factory()->create();
    $response = $this->actingAs($user)->post(route('auktionen.gebote.store', $auktion), ['betrag' => '12.00']);
    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors();
    $this->assertDatabaseCount('auktion_gebote', 0);
});

test('first bid must meet start amount', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $auktion = Auktion::factory()->create(['startbetrag_cent' => 1500, 'mindestschritt_cent' => 100]);
    $response = $this->from(route('auktionen.show', $auktion))->actingAs($member)->post(route('auktionen.gebote.store', $auktion), ['betrag' => '14.00']);
    $response->assertRedirect(route('auktionen.show', $auktion));
    $response->assertSessionHasErrors('betrag');
    $this->assertDatabaseCount('auktion_gebote', 0);
});

test('bid rejects invalid money format', function (string $betrag): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $auktion = Auktion::factory()->create(['startbetrag_cent' => 1000, 'mindestschritt_cent' => 100]);
    $response = $this->from(route('auktionen.show', $auktion))->actingAs($member)->post(route('auktionen.gebote.store', $auktion), ['betrag' => $betrag]);
    $response->assertRedirect(route('auktionen.show', $auktion));
    $response->assertSessionHasErrors('betrag');
    expect($response->getSession()->get('errors')->get('betrag'))->toHaveCount(1)
        ->and($response->getSession()->get('errors')->get('betrag')[0])->toBe('Bitte gib einen gültigen Euro-Betrag mit maximal zwei Nachkommastellen ein.');
    $this->assertDatabaseCount('auktion_gebote', 0);
})->with([['1e3'], ['10.999']]);

test('follow up bid must reach current highest plus minimum step', function (): void {
    $firstBidder = $this->createUserWithRole(Role::Mitglied);
    $secondBidder = $this->createUserWithRole(Role::Ehrenmitglied);
    $auktion = Auktion::factory()->create(['startbetrag_cent' => 1000, 'mindestschritt_cent' => 200]);
    AuktionGebot::factory()->for($auktion)->for($firstBidder)->create(['bieter_name' => $firstBidder->name, 'betrag_cent' => 1500]);
    $response = $this->from(route('auktionen.show', $auktion))->actingAs($secondBidder)->post(route('auktionen.gebote.store', $auktion), ['betrag' => '16.00']);
    $response->assertRedirect(route('auktionen.show', $auktion));
    $response->assertSessionHasErrors('betrag');
    $this->assertDatabaseCount('auktion_gebote', 1);
    $this->actingAs($secondBidder)->post(route('auktionen.gebote.store', $auktion), ['betrag' => '17.00'])->assertRedirect(route('auktionen.show', $auktion))->assertSessionHasNoErrors();
    $this->assertDatabaseHas('auktion_gebote', ['auktion_id' => $auktion->id, 'user_id' => $secondBidder->id, 'betrag_cent' => 1700]);
});

test('new bid after zum ersten or zum zweiten resets status to laufend', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $auktion = Auktion::factory()->create(['status' => AuktionsStatus::ZumZweiten, 'startbetrag_cent' => 1000, 'mindestschritt_cent' => 100]);
    $this->actingAs($member)->post(route('auktionen.gebote.store', $auktion), ['betrag' => '10.00'])->assertRedirect(route('auktionen.show', $auktion))->assertSessionHasNoErrors();
    expect($auktion->fresh()->status)->toBe(AuktionsStatus::Laufend);
});

test('closed auctions reject new bids', function (AuktionsStatus $status): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $auktion = Auktion::factory()->create(['status' => $status]);
    $response = $this->actingAs($member)->post(route('auktionen.gebote.store', $auktion), ['betrag' => '20.00']);
    $response->assertForbidden();
    $this->assertDatabaseCount('auktion_gebote', 0);
})->with([[AuktionsStatus::Verkauft], [AuktionsStatus::NichtVerkauft]]);
