<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\FantreffenAnmeldung;
use App\Services\FantreffenDeadlineService;
use App\Services\FantreffenRegistrationService;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class)->use(FantreffenRegistrationServiceTestFixtures::class);

beforeEach(function (): void {
    $deadlineService = $this->createStub(FantreffenDeadlineService::class);
    $deadlineService->method('isPassed')->willReturn(false);
    $this->service = new FantreffenRegistrationService($deadlineService);
});

test('member without tshirt pays nothing', function (): void {
    $amount = $this->service->calculatePaymentAmount(merchSelection: false, isAuthenticated: true);
    expect($amount)->toBe(0.0);
});

test('member with tshirt pays tshirt price', function (): void {
    $amount = $this->service->calculatePaymentAmount(merchSelection: true, isAuthenticated: true);
    expect($amount)->toBe(FantreffenAnmeldung::TSHIRT_PRICE);
});

test('guest without tshirt pays guest fee', function (): void {
    $amount = $this->service->calculatePaymentAmount(merchSelection: false, isAuthenticated: false);
    expect($amount)->toBe(FantreffenAnmeldung::GUEST_FEE);
});

test('guest with tshirt pays guest fee plus tshirt', function (): void {
    $amount = $this->service->calculatePaymentAmount(merchSelection: true, isAuthenticated: false);
    $expected = FantreffenAnmeldung::GUEST_FEE + FantreffenAnmeldung::TSHIRT_PRICE;
    expect($amount)->toBe($expected);
});

test('calculate payment amount returns float', function (): void {
    $amount = $this->service->calculatePaymentAmount(false, true);
    expect($amount)->toBeFloat();
});

test('guest with multiple merch items pays guest fee plus all item prices', function (): void {
    $amount = $this->service->calculatePaymentAmount([['price' => 12.0], ['price' => 25.0]], false);
    expect($amount)->toBe(42.0);
});

test('validation rules for authenticated user', function (): void {
    $rules = $this->service->validationRules(isAuthenticated: true);
    // Authentifizierte User brauchen keine Pflichtfelder für Name/Email
    expect($rules)->not->toHaveKey('vorname');
    expect($rules)->not->toHaveKey('nachname')->not->toHaveKey('email');
    // Diese Felder sollten vorhanden sein
    expect($rules)->toHaveKey('mobile');
    expect($rules)->toHaveKeys(['merch', 'merch.*.variant_id', 'tshirt_bestellt', 'tshirt_groesse'])
        ->and($rules['merch.*.variant_id'])->toContain('integer');
});

test('validation rules for guest', function (): void {
    $rules = $this->service->validationRules(isAuthenticated: false);
    // Gäste müssen Name und Email angeben
    expect($rules)->toHaveKey('vorname');
    expect($rules)->toHaveKeys(['nachname', 'email']);
    // Prüfe dass diese required sind
    expect($rules['vorname'])->toContain('required');
    expect($rules['nachname'])->toContain('required')
        ->and($rules['email'])->toContain('required');
});

test('tshirt size required when tshirt ordered', function (): void {
    $rules = $this->service->validationRules(isAuthenticated: true);
    // Prüfe dass tshirt_groesse bei bestelltem T-Shirt required ist
    // Regex erlaubt Whitespace-Variationen im required_if-Format
    expect($rules['tshirt_groesse'])->toMatch('/required_if\s*:\s*tshirt_bestellt\s*,\s*true/');
});

test('validation messages are in german', function (): void {
    $messages = $this->service->validationMessages();
    // Prüfe dass deutsche Fehlermeldungen vorhanden sind
    expect($messages)->toHaveKey('vorname.required');
    expect($messages['vorname.required'])->toContain('Vorname')
        ->and($messages)->toHaveKey('email.email')
        ->and($messages['email.email'])->toContain('gültige');
});

test('can order tshirt before deadline', function (): void {
    $deadlineService = $this->createStub(FantreffenDeadlineService::class);
    $deadlineService->method('isPassed')->willReturn(false);
    $service = new FantreffenRegistrationService($deadlineService);
    expect($service->canOrderTshirt())->toBeTrue();
});

test('cannot order tshirt after deadline', function (): void {
    $deadlineService = $this->createStub(FantreffenDeadlineService::class);
    $deadlineService->method('isPassed')->willReturn(true);
    $service = new FantreffenRegistrationService($deadlineService);
    expect($service->canOrderTshirt())->toBeFalse();
});

test('can order merch with preloaded collection before deadline', function (): void {
    expect($this->service->canOrderMerch(orderableArtikel: new Collection([['id' => 1]])))->toBeTrue();
});

test('cannot order merch with empty preloaded collection', function (): void {
    expect($this->service->canOrderMerch(orderableArtikel: new Collection))->toBeFalse();
});
