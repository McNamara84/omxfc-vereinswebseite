<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AuktionsStatus;
use App\Enums\Role;
use App\Http\Controllers\AuktionVerwaltungController;
use App\Http\Requests\UpdateAuktionRequest;
use App\Models\Auktion;
use App\Models\AuktionGebot;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('app.testing_minimal_layout', true);
});

test('admin can open auction management index', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $bieter = $this->createUserWithRole(Role::Mitglied);
    $auktion = Auktion::factory()->create();
    AuktionGebot::factory()->for($auktion)->for($bieter)->create(['bieter_name' => 'Index Bieter', 'betrag_cent' => 2700]);
    $response = $this->withoutVite()->actingAs($admin)->get(route('admin.auktionen.index'));
    $response->assertOk();
    $response->assertSee('Auktionen verwalten');
    $geladeneAuktion = $response->viewData('auktionen')->firstWhere('id', $auktion->id);
    expect($geladeneAuktion)->not->toBeNull()
        ->and($geladeneAuktion->relationLoaded('hoechstgebotRelation'))->toBeTrue()
        ->and($geladeneAuktion->relationLoaded('verkauftesGebot'))->toBeFalse()
        ->and($geladeneAuktion->relationLoaded('gebote'))->toBeFalse()
        ->and($geladeneAuktion->hoechstgebot()?->bieter_name)->toBe('Index Bieter');
});

test('admin edit view loads bid history without unused relations', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $bieter = $this->createUserWithRole(Role::Mitglied);
    $auktion = Auktion::factory()->create();
    AuktionGebot::factory()->for($auktion)->for($bieter)->create(['bieter_name' => 'Bearbeitungs Bieter', 'betrag_cent' => 2700]);
    $response = $this->withoutVite()->actingAs($admin)->get(route('admin.auktionen.edit', $auktion));
    $response->assertOk();
    $html = $response->getContent();
    expect($html)->toMatch('/<input[^>]*id="startbetrag"[^>]*type="text"[^>]*inputmode="decimal"[^>]*>/i')
        ->toMatch('/<input[^>]*id="mindestschritt"[^>]*type="text"[^>]*inputmode="decimal"[^>]*>/i');
    $this->assertDoesNotMatchRegularExpression('/<input[^>]*id="startbetrag"[^>]*type="number"[^>]*>/i', $html);
    $this->assertDoesNotMatchRegularExpression('/<input[^>]*id="mindestschritt"[^>]*type="number"[^>]*>/i', $html);
    $geladeneAuktion = $response->viewData('auktion');
    expect($geladeneAuktion->relationLoaded('gebote'))->toBeTrue()
        ->and($geladeneAuktion->relationLoaded('verkauftesGebot'))->toBeFalse()
        ->and($geladeneAuktion->relationLoaded('verkauftAnUser'))->toBeFalse()
        ->and($geladeneAuktion->gebote->first()->relationLoaded('user'))->toBeFalse();
});

test('member cannot open auction management index', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $response = $this->actingAs($member)->get(route('admin.auktionen.index'));
    $response->assertForbidden();
});

test('member can open auction index', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $response = $this->withoutVite()->actingAs($member)->get(route('auktionen.index'));
    $response->assertOk();
    $response->assertSee('Auktionen');
});

test('admin can create new auction', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $response = $this->actingAs($admin)->post(route('admin.auktionen.store'), ['titel' => 'Limitierte Sonderausgabe', 'beschreibung_markdown' => '## Rares Sammlerstueck', 'startbetrag' => '15.00', 'mindestschritt' => '2.50']);
    $auktion = Auktion::query()->where('titel', 'Limitierte Sonderausgabe')->first();
    $response->assertRedirect(route('admin.auktionen.edit', $auktion));
    expect($auktion)->not->toBeNull()
        ->and($auktion->startbetrag_cent)->toBe(1500)
        ->and($auktion->mindestschritt_cent)->toBe(250)
        ->and($auktion->status)->toBe(AuktionsStatus::Laufend);
});

test('admin cannot create auction with invalid money format', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $response = $this->from(route('admin.auktionen.create'))->actingAs($admin)->post(route('admin.auktionen.store'), ['titel' => 'Ungültige Werte', 'beschreibung_markdown' => 'Test', 'startbetrag' => '1e3', 'mindestschritt' => '1.999']);
    $response->assertRedirect(route('admin.auktionen.create'));
    $response->assertSessionHasErrors(['startbetrag', 'mindestschritt']);
    $errors = $response->getSession()->get('errors');
    expect($errors->get('startbetrag'))->toHaveCount(1)
        ->and($errors->get('mindestschritt'))->toHaveCount(1)
        ->and($errors->get('startbetrag')[0])->toBe('Bitte gib einen gültigen Euro-Betrag mit maximal zwei Nachkommastellen ein.')
        ->and($errors->get('mindestschritt')[0])->toBe('Bitte gib einen gültigen Euro-Betrag mit maximal zwei Nachkommastellen ein.');
    $this->assertDatabaseMissing('auktionen', ['titel' => 'Ungültige Werte']);
});

test('admin cannot create auction with zero minimum step uses umlaut message', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $response = $this->from(route('admin.auktionen.create'))->actingAs($admin)->post(route('admin.auktionen.store'), ['titel' => 'Kein Mindestschritt', 'beschreibung_markdown' => 'Test', 'startbetrag' => '0.00', 'mindestschritt' => '0.00']);
    $response->assertRedirect(route('admin.auktionen.create'));
    $response->assertSessionHasErrors('mindestschritt');
    expect($response->getSession()->get('errors')->get('mindestschritt')[0])->toBe('Der Mindestschritt muss größer als 0,00 € sein.');
});

test('admin cannot create auction with negative startbetrag and receives single format message', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $response = $this->from(route('admin.auktionen.create'))->actingAs($admin)->post(route('admin.auktionen.store'), ['titel' => 'Negativer Start', 'beschreibung_markdown' => 'Test', 'startbetrag' => '-1.00', 'mindestschritt' => '1.00']);
    $response->assertRedirect(route('admin.auktionen.create'));
    $response->assertSessionHasErrors('startbetrag');
    $errors = $response->getSession()->get('errors');
    expect($errors->get('startbetrag'))->toHaveCount(1)
        ->and($errors->get('startbetrag')[0])->toBe('Bitte gib einen gültigen Euro-Betrag mit maximal zwei Nachkommastellen ein.');
});

test('admin can update auction before first bid', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $auktion = Auktion::factory()->create(['titel' => 'Vorher', 'startbetrag_cent' => 1000, 'mindestschritt_cent' => 100]);
    $this->actingAs($admin)->put(route('admin.auktionen.update', $auktion), ['titel' => 'Nachher', 'beschreibung_markdown' => 'Aktualisiert', 'startbetrag' => '12.00', 'mindestschritt' => '1.50'])->assertRedirect(route('admin.auktionen.edit', $auktion));
    $auktion->refresh();
    expect($auktion->titel)->toBe('Nachher')
        ->and($auktion->beschreibung_markdown)->toBe('Aktualisiert')
        ->and($auktion->startbetrag_cent)->toBe(1200)
        ->and($auktion->mindestschritt_cent)->toBe(150);
});

test('auction with bids rejects changes to startbetrag and mindestschritt', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $bieter = $this->createUserWithRole(Role::Mitglied);
    $auktion = Auktion::factory()->create(['startbetrag_cent' => 1000, 'mindestschritt_cent' => 100]);
    AuktionGebot::factory()->for($auktion)->for($bieter)->create(['bieter_name' => $bieter->name, 'betrag_cent' => 1200]);
    $response = $this->from(route('admin.auktionen.edit', $auktion))->actingAs($admin)->put(route('admin.auktionen.update', $auktion), ['titel' => 'Geaenderter Titel', 'beschreibung_markdown' => 'Neue Beschreibung', 'startbetrag' => '30.00', 'mindestschritt' => '5.00']);
    $response->assertRedirect(route('admin.auktionen.edit', $auktion));
    $response->assertSessionHasErrors(['startbetrag', 'mindestschritt']);
    $errors = $response->getSession()->get('errors');
    expect($errors->get('startbetrag')[0])->toBe('Der Startbetrag kann nach dem ersten Gebot nicht mehr geändert werden.')
        ->and($errors->get('mindestschritt')[0])->toBe('Der Mindestschritt kann nach dem ersten Gebot nicht mehr geändert werden.');
    $auktion->refresh();
    expect($auktion->startbetrag_cent)->toBe(1000)
        ->and($auktion->mindestschritt_cent)->toBe(100);
});

test('auction with bids invalid money format does not add duplicate locked field errors', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $bieter = $this->createUserWithRole(Role::Mitglied);
    $auktion = Auktion::factory()->create(['startbetrag_cent' => 1000, 'mindestschritt_cent' => 100]);
    AuktionGebot::factory()->for($auktion)->for($bieter)->create(['bieter_name' => $bieter->name, 'betrag_cent' => 1200]);
    $response = $this->from(route('admin.auktionen.edit', $auktion))->actingAs($admin)->put(route('admin.auktionen.update', $auktion), ['titel' => 'Geänderter Titel', 'beschreibung_markdown' => 'Neue Beschreibung', 'startbetrag' => '1e3', 'mindestschritt' => '1.999']);
    $response->assertRedirect(route('admin.auktionen.edit', $auktion));
    $response->assertSessionHasErrors(['startbetrag', 'mindestschritt']);
    $errors = $response->getSession()->get('errors');
    expect($errors->get('startbetrag'))->toHaveCount(1)
        ->and($errors->get('mindestschritt'))->toHaveCount(1)
        ->and($errors->get('startbetrag')[0])->toBe('Bitte gib einen gültigen Euro-Betrag mit maximal zwei Nachkommastellen ein.')
        ->and($errors->get('mindestschritt')[0])->toBe('Bitte gib einen gültigen Euro-Betrag mit maximal zwei Nachkommastellen ein.');
});

test('auction with bids still allows title and description updates', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $bieter = $this->createUserWithRole(Role::Mitglied);
    $auktion = Auktion::factory()->create(['titel' => 'Alt', 'beschreibung_markdown' => 'Alttext', 'startbetrag_cent' => 1000, 'mindestschritt_cent' => 100]);
    AuktionGebot::factory()->for($auktion)->for($bieter)->create(['bieter_name' => $bieter->name, 'betrag_cent' => 1300]);
    $this->actingAs($admin)->put(route('admin.auktionen.update', $auktion), ['titel' => 'Neu', 'beschreibung_markdown' => 'Neutext'])->assertRedirect(route('admin.auktionen.edit', $auktion))->assertSessionHasNoErrors();
    $auktion->refresh();
    expect($auktion->titel)->toBe('Neu')
        ->and($auktion->beschreibung_markdown)->toBe('Neutext')
        ->and($auktion->startbetrag_cent)->toBe(1000)
        ->and($auktion->mindestschritt_cent)->toBe(100);
});

test('update rechecks locked money fields when bid arrives after initial authorization', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $bieter = $this->createUserWithRole(Role::Mitglied);
    $auktion = Auktion::factory()->create(['titel' => 'Vorher', 'beschreibung_markdown' => 'Alttext', 'startbetrag_cent' => 1000, 'mindestschritt_cent' => 100]);
    $this->actingAs($admin);
    $controller = $this->partialMock(AuktionVerwaltungController::class, function (MockInterface $mock) use ($auktion, $bieter): void {
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('authorize')->once()->andReturnUsing(function () use ($auktion, $bieter): void {
            AuktionGebot::factory()->for($auktion)->for($bieter)->create(['bieter_name' => $bieter->name, 'betrag_cent' => 1200]);
        });
    });
    $request = UpdateAuktionRequest::create('/admin/auktionen/'.$auktion->id, 'PUT', ['titel' => 'Nachher', 'beschreibung_markdown' => 'Neutext', 'startbetrag' => '30.00', 'mindestschritt' => '5.00']);
    try {
        $controller->update($request, $auktion);
        $this->fail('Expected locked money field validation to abort the update.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['startbetrag' => ['Der Startbetrag kann nach dem ersten Gebot nicht mehr geändert werden.'], 'mindestschritt' => ['Der Mindestschritt kann nach dem ersten Gebot nicht mehr geändert werden.']]);
    }
    $auktion->refresh();
    expect($auktion->titel)->toBe('Vorher')
        ->and($auktion->beschreibung_markdown)->toBe('Alttext')
        ->and($auktion->startbetrag_cent)->toBe(1000)
        ->and($auktion->mindestschritt_cent)->toBe(100);
});

test('admin can delete auction without bids', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $auktion = Auktion::factory()->create();
    $this->actingAs($admin)->delete(route('admin.auktionen.destroy', $auktion))->assertRedirect(route('admin.auktionen.index'))->assertSessionHas('success', 'Auktion gelöscht.');
    $this->assertDatabaseMissing('auktionen', ['id' => $auktion->id]);
});

test('admin cannot delete auction with bids', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $bieter = $this->createUserWithRole(Role::Mitglied);
    $auktion = Auktion::factory()->create();
    AuktionGebot::factory()->for($auktion)->for($bieter)->create(['bieter_name' => $bieter->name]);
    $this->actingAs($admin)->delete(route('admin.auktionen.destroy', $auktion))->assertForbidden();
});

test('destroy rechecks bid existence when bid arrives after initial authorization', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $bieter = $this->createUserWithRole(Role::Mitglied);
    $auktion = Auktion::factory()->create();
    $this->actingAs($admin);
    $controller = $this->partialMock(AuktionVerwaltungController::class, function (MockInterface $mock) use ($auktion, $bieter): void {
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('authorize')->once()->andReturnUsing(function () use ($auktion, $bieter): void {
            AuktionGebot::factory()->for($auktion)->for($bieter)->create(['bieter_name' => $bieter->name, 'betrag_cent' => 1200]);
        });
    });
    try {
        $controller->destroy($auktion);
        $this->fail('Expected destroy to abort when a bid appears after authorization.');
    } catch (AuthorizationException) {
        // Expected: the locked re-check blocks deleting the auction.
    }
    $this->assertDatabaseHas('auktionen', ['id' => $auktion->id]);
    $this->assertDatabaseHas('auktion_gebote', ['auktion_id' => $auktion->id]);
});

test('only vorstand can progress and sell auction to highest bidder', function (): void {
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $admin = $this->createUserWithRole(Role::Admin);
    $bieter = $this->createUserWithRole(Role::Mitglied);
    $auktion = Auktion::factory()->create();
    $gebot = AuktionGebot::factory()->for($auktion)->for($bieter)->create(['bieter_name' => 'Hoechstbieter', 'betrag_cent' => 3200]);
    $this->actingAs($admin)->post(route('admin.auktionen.zum-ersten', $auktion))->assertForbidden();
    $this->actingAs($vorstand)->post(route('admin.auktionen.zum-ersten', $auktion))->assertRedirect();
    expect($auktion->fresh()->status)->toBe(AuktionsStatus::ZumErsten);
    $this->actingAs($vorstand)->post(route('admin.auktionen.zum-zweiten', $auktion))->assertRedirect();
    expect($auktion->fresh()->status)->toBe(AuktionsStatus::ZumZweiten);
    $this->actingAs($vorstand)->post(route('admin.auktionen.verkaufen', $auktion))->assertRedirect()->assertSessionHas('success', 'Auktion wurde an das aktuelle Höchstgebot verkauft.');
    $auktion->refresh();
    expect($auktion->status)->toBe(AuktionsStatus::Verkauft)
        ->and($auktion->verkauft_an_user_id)->toBe($bieter->id)
        ->and($auktion->verkauft_gebot_id)->toBe($gebot->id)
        ->and($auktion->verkauft_at)->not->toBeNull();
});

test('vorstand cannot sell before zum zweiten and receives umlaut message', function (): void {
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $auktion = Auktion::factory()->create();
    $response = $this->from(route('admin.auktionen.edit', $auktion))->actingAs($vorstand)->post(route('admin.auktionen.verkaufen', $auktion));
    $response->assertRedirect(route('admin.auktionen.edit', $auktion));
    $response->assertSessionHasErrors('status');
    expect($response->getSession()->get('errors')->get('status')[0])->toBe('Verkaufen ist erst nach "Zum zweiten" möglich.');
});

test('non vorstand management roles cannot advance auction', function (Role $role): void {
    $manager = $this->createUserWithRole($role);
    $auktion = Auktion::factory()->create();
    $this->actingAs($manager)->post(route('admin.auktionen.zum-ersten', $auktion))->assertForbidden();
})->with([[Role::Admin], [Role::Kassenwart]]);

test('vorstand can mark auction as not sold after zum zweiten', function (): void {
    $vorstand = $this->createUserWithRole(Role::Vorstand);
    $auktion = Auktion::factory()->create(['status' => AuktionsStatus::ZumZweiten]);
    $this->actingAs($vorstand)->post(route('admin.auktionen.nicht-verkauft', $auktion))->assertRedirect();
    $auktion->refresh();
    expect($auktion->status)->toBe(AuktionsStatus::NichtVerkauft)
        ->and($auktion->verkauft_at)->not->toBeNull();
});
