<?php

namespace Tests\Unit;

use App\Models\FantreffenAnmeldung;
use App\Services\FantreffenDeadlineService;
use App\Services\FantreffenRegistrationService;
use PHPUnit\Framework\TestCase;

/**
 * Unit-Tests für den FantreffenRegistrationService.
 *
 * Testet die isolierte Business-Logik ohne Datenbankzugriff.
 *
 * HINWEIS: Dieser Test erweitert absichtlich PHPUnit\Framework\TestCase statt Tests\TestCase,
 * da er ein echter Unit-Test ist, der keine Datenbank- oder HTTP-Infrastruktur benötigt.
 * Alle Abhängigkeiten werden per Mock bereitgestellt. Dies entspricht dem Unit-Test-Pattern
 * und vermeidet unnötigen Overhead durch das Laravel-Test-Setup.
 */
class FantreffenRegistrationServiceTest extends TestCase
{
    private FantreffenRegistrationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $deadlineService = $this->createMock(FantreffenDeadlineService::class);
        $deadlineService->method('isPassed')->willReturn(false);

        $this->service = new FantreffenRegistrationService($deadlineService);
    }

    // ============================================
    // calculatePaymentAmount() Tests
    // ============================================

    public function test_member_without_tshirt_pays_nothing(): void
    {
        $amount = $this->service->calculatePaymentAmount(
            tshirtBestellt: false,
            isAuthenticated: true
        );

        $this->assertSame(0.0, $amount);
    }

    public function test_member_with_tshirt_pays_tshirt_price(): void
    {
        $amount = $this->service->calculatePaymentAmount(
            tshirtBestellt: true,
            isAuthenticated: true
        );

        $this->assertSame(FantreffenAnmeldung::TSHIRT_PRICE, $amount);
    }

    public function test_guest_without_tshirt_pays_guest_fee(): void
    {
        $amount = $this->service->calculatePaymentAmount(
            tshirtBestellt: false,
            isAuthenticated: false
        );

        $this->assertSame(FantreffenAnmeldung::GUEST_FEE, $amount);
    }

    public function test_guest_with_tshirt_pays_guest_fee_plus_tshirt(): void
    {
        $amount = $this->service->calculatePaymentAmount(
            tshirtBestellt: true,
            isAuthenticated: false
        );

        $expected = FantreffenAnmeldung::GUEST_FEE + FantreffenAnmeldung::TSHIRT_PRICE;
        $this->assertSame($expected, $amount);
    }

    public function test_calculate_payment_amount_returns_float(): void
    {
        $amount = $this->service->calculatePaymentAmount(false, true);

        $this->assertIsFloat($amount);
    }

    // ============================================
    // validationRules() Tests
    // ============================================

    public function test_validation_rules_for_authenticated_user(): void
    {
        $rules = $this->service->validationRules(isAuthenticated: true);

        // Authentifizierte User brauchen keine Pflichtfelder für Name/Email
        $this->assertArrayNotHasKey('vorname', $rules);
        $this->assertArrayNotHasKey('nachname', $rules);
        $this->assertArrayNotHasKey('email', $rules);

        // Diese Felder sollten vorhanden sein
        $this->assertArrayHasKey('mobile', $rules);
        $this->assertArrayHasKey('tshirt_bestellt', $rules);
        $this->assertArrayHasKey('tshirt_groesse', $rules);
    }

    public function test_validation_rules_for_guest(): void
    {
        $rules = $this->service->validationRules(isAuthenticated: false);

        // Gäste müssen Name und Email angeben
        $this->assertArrayHasKey('vorname', $rules);
        $this->assertArrayHasKey('nachname', $rules);
        $this->assertArrayHasKey('email', $rules);

        // Prüfe dass diese required sind
        $this->assertStringContainsString('required', $rules['vorname']);
        $this->assertStringContainsString('required', $rules['nachname']);
        $this->assertStringContainsString('required', $rules['email']);
    }

    public function test_tshirt_size_required_when_tshirt_ordered(): void
    {
        $rules = $this->service->validationRules(isAuthenticated: true);

        $this->assertStringContainsString('required_if:tshirt_bestellt,true', $rules['tshirt_groesse']);
    }

    // ============================================
    // validationMessages() Tests
    // ============================================

    public function test_validation_messages_are_in_german(): void
    {
        $messages = $this->service->validationMessages();

        // Prüfe dass deutsche Fehlermeldungen vorhanden sind
        $this->assertArrayHasKey('vorname.required', $messages);
        $this->assertStringContainsString('Vorname', $messages['vorname.required']);

        $this->assertArrayHasKey('email.email', $messages);
        $this->assertStringContainsString('gültige', $messages['email.email']);
    }

    // ============================================
    // canOrderTshirt() Tests
    // ============================================

    public function test_can_order_tshirt_before_deadline(): void
    {
        $deadlineService = $this->createMock(FantreffenDeadlineService::class);
        $deadlineService->method('isPassed')->willReturn(false);

        $service = new FantreffenRegistrationService($deadlineService);

        $this->assertTrue($service->canOrderTshirt());
    }

    public function test_cannot_order_tshirt_after_deadline(): void
    {
        $deadlineService = $this->createMock(FantreffenDeadlineService::class);
        $deadlineService->method('isPassed')->willReturn(true);

        $service = new FantreffenRegistrationService($deadlineService);

        $this->assertFalse($service->canOrderTshirt());
    }
}
