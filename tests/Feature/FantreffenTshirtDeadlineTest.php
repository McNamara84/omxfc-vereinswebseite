<?php

namespace Tests\Feature;

use App\Models\Veranstaltung;
use App\Models\VeranstaltungsMerchartikel;
use App\Services\FantreffenDeadlineService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesFantreffenFormToken;
use Tests\TestCase;

class FantreffenTshirtDeadlineTest extends TestCase
{
    use CreatesFantreffenFormToken;
    use RefreshDatabase;

    protected Veranstaltung $veranstaltung;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('app.testing_minimal_layout', true);

        $this->veranstaltung = Veranstaltung::create([
            'titel' => 'Deadline-Testevent',
            'slug' => 'deadline-testevent',
            'status' => 'veroeffentlicht',
            'anmeldung_aktiv' => true,
            'tshirt_aktiv' => true,
            'zahlung_aktiv' => true,
            'gastgebuehr' => 5,
            'tshirt_preis' => 25,
            'ist_highlight' => true,
        ]);

        $tshirt = $this->veranstaltung->merchartikel()->create([
            'bezeichnung' => 'T-Shirt',
            'preis' => 25,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        foreach (['XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL'] as $index => $groesse) {
            $tshirt->varianten()->create([
                'bezeichnung' => $groesse,
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }
    }

    protected function showUrl(): string
    {
        return route('veranstaltungen.show', ['veranstaltung' => $this->veranstaltung]);
    }

    protected function storeUrl(): string
    {
        return route('veranstaltungen.anmeldung.store', ['veranstaltung' => $this->veranstaltung]);
    }

    #[Test]
    public function test_tshirt_deadline_is_read_from_config()
    {
        $deadline = config('services.fantreffen.tshirt_deadline');
        $this->assertNotNull($deadline);
        $this->assertIsString($deadline);

        $parsedDeadline = Carbon::parse($deadline);
        $this->assertInstanceOf(Carbon::class, $parsedDeadline);
    }

    #[Test]
    public function test_tshirt_section_is_visible_before_deadline()
    {
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->addDays(30)->format('Y-m-d H:i:s'));

        $response = $this->withoutVite()->get($this->showUrl());

        $response->assertStatus(200);
        $response->assertSee('Bestellfrist:');
        $response->assertSee('Event-T-Shirt bestellen');
    }

    #[Test]
    public function test_tshirt_section_is_hidden_after_deadline()
    {
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->subDays(1)->format('Y-m-d H:i:s'));

        $response = $this->withoutVite()->get($this->showUrl());

        $response->assertStatus(200);
        $response->assertDontSee('T-Shirt nur bis');
        $response->assertViewHas('tshirtDeadlinePassed', true);
    }

    #[Test]
    public function test_days_until_deadline_is_calculated_correctly()
    {
        $deadline = Carbon::now()->addDays(10)->endOfDay();
        Config::set('services.fantreffen.tshirt_deadline', $deadline->format('Y-m-d H:i:s'));

        $response = $this->withoutVite()->get($this->showUrl());

        $response->assertStatus(200);
        $daysUntilDeadline = $response->viewData('daysUntilDeadline');
        $this->assertGreaterThanOrEqual(9, $daysUntilDeadline);
        $this->assertLessThanOrEqual(11, $daysUntilDeadline);
        $response->assertViewHas('tshirtDeadlinePassed', false);
    }

    #[Test]
    public function test_days_until_deadline_is_zero_when_deadline_passed()
    {
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->subDays(5)->format('Y-m-d H:i:s'));

        $response = $this->withoutVite()->get($this->showUrl());

        $response->assertStatus(200);
        $response->assertViewHas('daysUntilDeadline', 0);
        $response->assertViewHas('tshirtDeadlinePassed', true);
    }

    #[Test]
    public function test_formatted_deadline_is_displayed_correctly()
    {
        Config::set('services.fantreffen.tshirt_deadline', '2026-02-28 23:59:59');
        Carbon::setTestNow(Carbon::create(2026, 2, 15, 12));

        try {
            $response = $this->withoutVite()->get($this->showUrl());

            $response->assertStatus(200);
            $response->assertSee('28. Februar 2026');
        } finally {
            Carbon::setTestNow();
        }
    }

    #[Test]
    public function test_controller_passes_deadline_data_to_view_before_deadline()
    {
        $deadline = Carbon::now()->addDays(20)->endOfDay();
        Config::set('services.fantreffen.tshirt_deadline', $deadline->format('Y-m-d H:i:s'));

        $response = $this->withoutVite()->get($this->showUrl());

        $response->assertStatus(200);
        $response->assertViewHas('tshirtDeadlinePassed', false);
        $response->assertViewHas('tshirtDeadlineFormatted');

        $daysUntilDeadline = $response->viewData('daysUntilDeadline');
        $this->assertGreaterThanOrEqual(19, $daysUntilDeadline);
        $this->assertLessThanOrEqual(21, $daysUntilDeadline);
    }

    #[Test]
    public function test_controller_passes_deadline_data_to_view_after_deadline()
    {
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->subDays(5)->format('Y-m-d H:i:s'));

        $response = $this->withoutVite()->get($this->showUrl());

        $response->assertStatus(200);
        $response->assertViewHas('tshirtDeadlinePassed', true);
        $response->assertViewHas('daysUntilDeadline', 0);
    }

    #[Test]
    public function test_controller_prevents_tshirt_order_after_deadline()
    {
        Mail::fake();
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->subDays(1)->format('Y-m-d H:i:s'));

        $response = $this->post($this->storeUrl(), [
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'tshirt_bestellt' => true,
            'tshirt_groesse' => 'L',
            'website' => '',
            '_form_token' => $this->validFormToken(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('tshirt_bestellt');
    }

    #[Test]
    public function test_controller_allows_registration_without_tshirt_after_deadline()
    {
        Mail::fake();
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->subDays(1)->format('Y-m-d H:i:s'));

        $response = $this->post($this->storeUrl(), [
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'tshirt_bestellt' => false,
            'website' => '',
            '_form_token' => $this->validFormToken(),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('fantreffen_anmeldungen', [
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'tshirt_bestellt' => false,
        ]);
    }

    #[Test]
    public function test_aria_alert_is_added_when_deadline_is_near()
    {
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->addDays(5)->format('Y-m-d H:i:s'));

        $response = $this->withoutVite()->get($this->showUrl());

        $response->assertStatus(200);
        $response->assertSee('role="alert"', false);
    }

    #[Test]
    public function test_aria_alert_is_not_added_when_deadline_is_far()
    {
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->addDays(30)->format('Y-m-d H:i:s'));

        $deadlineService = new FantreffenDeadlineService;
        $this->assertFalse($deadlineService->shouldShowAlert($this->veranstaltung));
        $this->assertGreaterThan(7, $deadlineService->getDaysRemaining($this->veranstaltung));

        $response = $this->withoutVite()->get($this->showUrl());
        $response->assertStatus(200);
        $response->assertDontSee('role="alert"', false);
    }

    #[Test]
    public function test_deadline_service_correctly_calculates_alert_threshold()
    {
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->addDays(7)->endOfDay()->format('Y-m-d H:i:s'));
        $service = new FantreffenDeadlineService;
        $this->assertTrue($service->shouldShowAlert($this->veranstaltung));

        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->addDays(8)->endOfDay()->format('Y-m-d H:i:s'));
        $service = new FantreffenDeadlineService;
        $this->assertFalse($service->shouldShowAlert($this->veranstaltung));
    }

    #[Test]
    public function test_deadline_service_hides_alert_without_deadline(): void
    {
        Config::set('services.fantreffen.tshirt_deadline', null);
        $this->veranstaltung->update(['tshirt_deadline' => null]);

        $service = new FantreffenDeadlineService;

        $this->assertFalse($service->shouldShowAlert($this->veranstaltung));
    }
}
