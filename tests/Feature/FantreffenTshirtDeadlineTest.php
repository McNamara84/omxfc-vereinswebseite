<?php

namespace Tests\Feature;

use App\Services\FantreffenDeadlineService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FantreffenTshirtDeadlineTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_tshirt_deadline_is_read_from_config()
    {
        // Verify the config key exists and has a valid format
        $deadline = config('services.fantreffen.tshirt_deadline');
        $this->assertNotNull($deadline);
        $this->assertIsString($deadline);

        // Verify it's a valid date
        $parsedDeadline = Carbon::parse($deadline);
        $this->assertInstanceOf(Carbon::class, $parsedDeadline);
    }

    #[Test]
    public function test_tshirt_section_is_visible_before_deadline()
    {
        // Set deadline to future
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->addDays(30)->format('Y-m-d H:i:s'));

        $response = $this->withoutVite()->get(route('fantreffen.2026'));

        $response->assertStatus(200);
        $response->assertSee('T-Shirt nur bis');
        $response->assertSee('Event-T-Shirt bestellen');
    }

    #[Test]
    public function test_tshirt_section_is_hidden_after_deadline()
    {
        // Set deadline to past
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->subDays(1)->format('Y-m-d H:i:s'));

        $response = $this->withoutVite()->get(route('fantreffen.2026'));

        $response->assertStatus(200);
        $response->assertDontSee('T-Shirt nur bis');
        $response->assertViewHas('tshirtDeadlinePassed', true);
    }

    #[Test]
    public function test_days_until_deadline_is_calculated_correctly()
    {
        // Set deadline to 10 days from now at end of day
        $deadline = Carbon::now()->addDays(10)->endOfDay();
        Config::set('services.fantreffen.tshirt_deadline', $deadline->format('Y-m-d H:i:s'));

        $response = $this->withoutVite()->get(route('fantreffen.2026'));

        $response->assertStatus(200);
        // Days calculation may vary by 1 day depending on time
        $daysUntilDeadline = $response->viewData('daysUntilDeadline');
        $this->assertGreaterThanOrEqual(9, $daysUntilDeadline);
        $this->assertLessThanOrEqual(11, $daysUntilDeadline);
        $response->assertViewHas('tshirtDeadlinePassed', false);
    }

    #[Test]
    public function test_days_until_deadline_is_zero_when_deadline_passed()
    {
        // Set deadline to past
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->subDays(5)->format('Y-m-d H:i:s'));

        $response = $this->withoutVite()->get(route('fantreffen.2026'));

        $response->assertStatus(200);
        $response->assertViewHas('daysUntilDeadline', 0);
        $response->assertViewHas('tshirtDeadlinePassed', true);
    }

    #[Test]
    public function test_formatted_deadline_is_displayed_correctly()
    {
        // Set a specific deadline
        Config::set('services.fantreffen.tshirt_deadline', '2026-02-28 23:59:59');

        $response = $this->withoutVite()->get(route('fantreffen.2026'));

        $response->assertStatus(200);
        $response->assertSee('28. Februar 2026');
    }

    #[Test]
    public function test_controller_passes_deadline_data_to_view_before_deadline()
    {
        // Set deadline to future at end of day
        $deadline = Carbon::now()->addDays(20)->endOfDay();
        Config::set('services.fantreffen.tshirt_deadline', $deadline->format('Y-m-d H:i:s'));

        $response = $this->withoutVite()->get(route('fantreffen.2026'));

        $response->assertStatus(200);
        $response->assertViewHas('tshirtDeadlinePassed', false);
        $response->assertViewHas('tshirtDeadlineFormatted');

        // Verify daysUntilDeadline is in reasonable range
        $daysUntilDeadline = $response->viewData('daysUntilDeadline');
        $this->assertGreaterThanOrEqual(19, $daysUntilDeadline);
        $this->assertLessThanOrEqual(21, $daysUntilDeadline);
    }

    #[Test]
    public function test_controller_passes_deadline_data_to_view_after_deadline()
    {
        // Set deadline to past
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->subDays(5)->format('Y-m-d H:i:s'));

        $response = $this->withoutVite()->get(route('fantreffen.2026'));

        $response->assertStatus(200);
        $response->assertViewHas('tshirtDeadlinePassed', true);
        $response->assertViewHas('daysUntilDeadline', 0);
    }

    #[Test]
    public function test_controller_prevents_tshirt_order_after_deadline()
    {
        Mail::fake();

        // Set deadline to past
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->subDays(1)->format('Y-m-d H:i:s'));

        $response = $this->post(route('fantreffen.2026.store'), [
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'tshirt_bestellt' => true,
            'tshirt_groesse' => 'L',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('tshirt_bestellt');
    }

    #[Test]
    public function test_controller_allows_registration_without_tshirt_after_deadline()
    {
        Mail::fake();

        // Set deadline to past
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->subDays(1)->format('Y-m-d H:i:s'));

        $response = $this->post(route('fantreffen.2026.store'), [
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'tshirt_bestellt' => false,
        ]);

        // Should succeed without T-shirt
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
        // Set deadline to 5 days from now (within 7-day threshold)
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->addDays(5)->format('Y-m-d H:i:s'));

        $response = $this->withoutVite()->get(route('fantreffen.2026'));

        $response->assertStatus(200);
        $response->assertSee('role="alert"', false);
    }

    #[Test]
    public function test_aria_alert_is_not_added_when_deadline_is_far()
    {
        // Set deadline to 30 days from now (beyond 7-day threshold)
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->addDays(30)->format('Y-m-d H:i:s'));

        // Use the service to verify the shouldShowAlert logic
        $deadlineService = new FantreffenDeadlineService;
        $this->assertFalse($deadlineService->shouldShowAlert());
        $this->assertGreaterThan(7, $deadlineService->getDaysRemaining());

        // Verify the page reflects this - no role="alert" should appear
        $response = $this->withoutVite()->get(route('fantreffen.2026'));
        $response->assertStatus(200);
        $response->assertDontSee('role="alert"', false);
    }

    #[Test]
    public function test_deadline_service_correctly_calculates_alert_threshold()
    {
        // Test at exactly 7 days (end of day) - should show alert
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->addDays(7)->endOfDay()->format('Y-m-d H:i:s'));
        $service = new FantreffenDeadlineService;
        $this->assertTrue($service->shouldShowAlert());

        // Test at 8 days (end of day) - should NOT show alert
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->addDays(8)->endOfDay()->format('Y-m-d H:i:s'));
        $service = new FantreffenDeadlineService;
        $this->assertFalse($service->shouldShowAlert());
    }
}
