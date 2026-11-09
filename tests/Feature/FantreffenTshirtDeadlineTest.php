<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Veranstaltung;
use App\Services\FantreffenDeadlineService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(FantreffenTshirtDeadlineTestFixtures::class);

beforeEach(function (): void {
    Config::set('app.testing_minimal_layout', true);
    $this->veranstaltung = Veranstaltung::create(['titel' => 'Deadline-Testevent', 'slug' => 'deadline-testevent', 'status' => 'veroeffentlicht', 'anmeldung_aktiv' => true, 'tshirt_aktiv' => true, 'zahlung_aktiv' => true, 'gastgebuehr' => 5, 'tshirt_preis' => 25, 'ist_highlight' => true]);
    $tshirt = $this->veranstaltung->merchartikel()->create(['bezeichnung' => 'T-Shirt', 'preis' => 25, 'sort_order' => 0, 'is_active' => true]);
    foreach (['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'] as $index => $groesse) {
        $tshirt->varianten()->create(['bezeichnung' => $groesse, 'sort_order' => $index, 'is_active' => true]);
    }
});

test('tshirt deadline is read from config', function () {
    $deadline = config('services.fantreffen.tshirt_deadline');
    expect($deadline)->not->toBeNull()
        ->toBeString();
    $parsedDeadline = Carbon::parse($deadline);
    expect($parsedDeadline)->toBeInstanceOf(Carbon::class);
});

test('tshirt section is visible before deadline', function () {
    Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->addDays(30)->format('Y-m-d H:i:s'));
    $response = $this->withoutVite()->get($this->showUrl());
    $response->assertStatus(200);
    $response->assertSee('Bestellfrist:');
    $response->assertSee('Event-T-Shirt bestellen');
});

test('tshirt section is hidden after deadline', function () {
    Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->subDays(1)->format('Y-m-d H:i:s'));
    $response = $this->withoutVite()->get($this->showUrl());
    $response->assertStatus(200);
    $response->assertDontSee('T-Shirt nur bis');
    $response->assertViewHas('tshirtDeadlinePassed', true);
});

test('days until deadline is calculated correctly', function () {
    $deadline = Carbon::now()->addDays(10)->endOfDay();
    Config::set('services.fantreffen.tshirt_deadline', $deadline->format('Y-m-d H:i:s'));
    $response = $this->withoutVite()->get($this->showUrl());
    $response->assertStatus(200);
    $daysUntilDeadline = $response->viewData('daysUntilDeadline');
    expect($daysUntilDeadline)->toBeGreaterThanOrEqual(9)
        ->toBeLessThanOrEqual(11);
    $response->assertViewHas('tshirtDeadlinePassed', false);
});

test('days until deadline is zero when deadline passed', function () {
    Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->subDays(5)->format('Y-m-d H:i:s'));
    $response = $this->withoutVite()->get($this->showUrl());
    $response->assertStatus(200);
    $response->assertViewHas('daysUntilDeadline', 0);
    $response->assertViewHas('tshirtDeadlinePassed', true);
});

test('formatted deadline is displayed correctly', function () {
    Config::set('services.fantreffen.tshirt_deadline', '2026-02-28 23:59:59');
    Carbon::setTestNow(Carbon::create(2026, 2, 15, 12));
    try {
        $response = $this->withoutVite()->get($this->showUrl());
        $response->assertStatus(200);
        $response->assertSee('28. Februar 2026');
    } finally {
        Carbon::setTestNow();
    }
});

test('controller passes deadline data to view before deadline', function () {
    $deadline = Carbon::now()->addDays(20)->endOfDay();
    Config::set('services.fantreffen.tshirt_deadline', $deadline->format('Y-m-d H:i:s'));
    $response = $this->withoutVite()->get($this->showUrl());
    $response->assertStatus(200);
    $response->assertViewHas('tshirtDeadlinePassed', false);
    $response->assertViewHas('tshirtDeadlineFormatted');
    $daysUntilDeadline = $response->viewData('daysUntilDeadline');
    expect($daysUntilDeadline)->toBeGreaterThanOrEqual(19)
        ->toBeLessThanOrEqual(21);
});

test('controller passes deadline data to view after deadline', function () {
    Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->subDays(5)->format('Y-m-d H:i:s'));
    $response = $this->withoutVite()->get($this->showUrl());
    $response->assertStatus(200);
    $response->assertViewHas('tshirtDeadlinePassed', true);
    $response->assertViewHas('daysUntilDeadline', 0);
});

test('controller prevents tshirt order after deadline', function () {
    Mail::fake();
    Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->subDays(1)->format('Y-m-d H:i:s'));
    $response = $this->post($this->storeUrl(), ['vorname' => 'Max', 'nachname' => 'Mustermann', 'email' => 'max@example.com', 'tshirt_bestellt' => true, 'tshirt_groesse' => 'L', 'website' => '', '_form_token' => $this->validFormToken()]);
    $response->assertRedirect();
    $response->assertSessionHasErrors('tshirt_bestellt');
});

test('controller allows registration without tshirt after deadline', function () {
    Mail::fake();
    Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->subDays(1)->format('Y-m-d H:i:s'));
    $response = $this->post($this->storeUrl(), ['vorname' => 'Max', 'nachname' => 'Mustermann', 'email' => 'max@example.com', 'tshirt_bestellt' => false, 'website' => '', '_form_token' => $this->validFormToken()]);
    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('fantreffen_anmeldungen', ['vorname' => 'Max', 'nachname' => 'Mustermann', 'email' => 'max@example.com', 'tshirt_bestellt' => false]);
});

test('aria alert is added when deadline is near', function () {
    Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->addDays(5)->format('Y-m-d H:i:s'));
    $response = $this->withoutVite()->get($this->showUrl());
    $response->assertStatus(200);
    $response->assertSee('role="alert"', false);
});

test('aria alert is not added when deadline is far', function () {
    Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->addDays(30)->format('Y-m-d H:i:s'));
    $deadlineService = new FantreffenDeadlineService;
    expect($deadlineService->shouldShowAlert($this->veranstaltung))->toBeFalse()
        ->and($deadlineService->getDaysRemaining($this->veranstaltung))->toBeGreaterThan(7);
    $response = $this->withoutVite()->get($this->showUrl());
    $response->assertStatus(200);
    $response->assertDontSee('role="alert"', false);
});

test('deadline service correctly calculates alert threshold', function () {
    Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->addDays(7)->endOfDay()->format('Y-m-d H:i:s'));
    $service = new FantreffenDeadlineService;
    expect($service->shouldShowAlert($this->veranstaltung))->toBeTrue();
    Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->addDays(8)->endOfDay()->format('Y-m-d H:i:s'));
    $service = new FantreffenDeadlineService;
    expect($service->shouldShowAlert($this->veranstaltung))->toBeFalse();
});

test('deadline service hides alert without deadline', function (): void {
    Config::set('services.fantreffen.tshirt_deadline', null);
    $this->veranstaltung->update(['tshirt_deadline' => null]);
    $service = new FantreffenDeadlineService;
    expect($service->shouldShowAlert($this->veranstaltung))->toBeFalse();
});
