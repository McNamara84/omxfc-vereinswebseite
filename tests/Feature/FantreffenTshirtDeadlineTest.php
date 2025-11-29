<?php

namespace Tests\Feature;

use App\Livewire\FantreffenAnmeldung;
use App\Services\FantreffenDeadlineService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class FantreffenTshirtDeadlineTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function tshirt_deadline_is_read_from_config()
    {
        // Verify the config key exists and has a valid format
        $deadline = config('services.fantreffen.tshirt_deadline');
        $this->assertNotNull($deadline);
        $this->assertIsString($deadline);
        
        // Verify it's a valid date
        $parsedDeadline = Carbon::parse($deadline);
        $this->assertInstanceOf(Carbon::class, $parsedDeadline);
    }

    /** @test */
    public function tshirt_section_is_visible_before_deadline()
    {
        // Set deadline to future
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->addDays(30)->format('Y-m-d H:i:s'));

        Livewire::test(FantreffenAnmeldung::class)
            ->assertSee('T-Shirt nur bis')
            ->assertSee('Event-T-Shirt bestellen');
    }

    /** @test */
    public function tshirt_section_is_hidden_after_deadline()
    {
        // Set deadline to past
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->subDays(1)->format('Y-m-d H:i:s'));

        Livewire::test(FantreffenAnmeldung::class)
            ->assertDontSee('Event-T-Shirt bestellen')
            ->assertSet('tshirtDeadlinePassed', true);
    }

    /** @test */
    public function days_until_deadline_is_calculated_correctly()
    {
        // Set deadline to 10 days from now at end of day
        $deadline = Carbon::now()->addDays(10)->endOfDay();
        Config::set('services.fantreffen.tshirt_deadline', $deadline->format('Y-m-d H:i:s'));

        $component = Livewire::test(FantreffenAnmeldung::class);
        
        // Days calculation may vary by 1 day depending on time
        $daysUntilDeadline = $component->get('daysUntilDeadline');
        $this->assertGreaterThanOrEqual(9, $daysUntilDeadline);
        $this->assertLessThanOrEqual(11, $daysUntilDeadline);
        $component->assertSet('tshirtDeadlinePassed', false);
    }

    /** @test */
    public function days_until_deadline_is_zero_when_deadline_passed()
    {
        // Set deadline to past
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->subDays(5)->format('Y-m-d H:i:s'));

        Livewire::test(FantreffenAnmeldung::class)
            ->assertSet('daysUntilDeadline', 0)
            ->assertSet('tshirtDeadlinePassed', true);
    }

    /** @test */
    public function formatted_deadline_is_displayed_correctly()
    {
        // Set a specific deadline
        Config::set('services.fantreffen.tshirt_deadline', '2026-02-28 23:59:59');

        Livewire::test(FantreffenAnmeldung::class)
            ->assertSee('28. Februar 2026');
    }

    /** @test */
    public function livewire_component_provides_correct_deadline_data_before_deadline()
    {
        // Set deadline to 15 days from now at end of day to ensure consistent calculation
        $deadline = Carbon::now()->addDays(15)->endOfDay();
        Config::set('services.fantreffen.tshirt_deadline', $deadline->format('Y-m-d H:i:s'));

        $component = Livewire::test(FantreffenAnmeldung::class);
        
        $component->assertSet('tshirtDeadlinePassed', false);
        // Days calculation may vary by 1 day depending on time, so check it's in reasonable range
        $daysUntilDeadline = $component->get('daysUntilDeadline');
        $this->assertGreaterThanOrEqual(14, $daysUntilDeadline);
        $this->assertLessThanOrEqual(16, $daysUntilDeadline);
        $this->assertNotEmpty($component->get('tshirtDeadlineFormatted'));
    }

    /** @test */
    public function livewire_component_provides_correct_deadline_data_after_deadline()
    {
        // Set deadline to past
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->subDays(5)->format('Y-m-d H:i:s'));

        Livewire::test(FantreffenAnmeldung::class)
            ->assertSet('tshirtDeadlinePassed', true)
            ->assertSet('daysUntilDeadline', 0);
    }

    /** @test */
    public function controller_passes_deadline_data_to_view_before_deadline()
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

    /** @test */
    public function controller_passes_deadline_data_to_view_after_deadline()
    {
        // Set deadline to past
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->subDays(5)->format('Y-m-d H:i:s'));

        $response = $this->withoutVite()->get(route('fantreffen.2026'));
        
        $response->assertStatus(200);
        $response->assertViewHas('tshirtDeadlinePassed', true);
        $response->assertViewHas('daysUntilDeadline', 0);
    }

    /** @test */
    public function livewire_component_prevents_tshirt_order_after_deadline()
    {
        // Set deadline to past
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->subDays(1)->format('Y-m-d H:i:s'));

        Livewire::test(FantreffenAnmeldung::class)
            ->set('vorname', 'Max')
            ->set('nachname', 'Mustermann')
            ->set('email', 'max@example.com')
            ->set('tshirt_bestellt', true)
            ->set('tshirt_groesse', 'L')
            ->call('submit')
            ->assertSee('Die Deadline für T-Shirt-Bestellungen ist leider abgelaufen.');
    }

    /** @test */
    public function controller_prevents_tshirt_order_after_deadline()
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

    /** @test */
    public function controller_allows_registration_without_tshirt_after_deadline()
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

    /** @test */
    public function aria_alert_is_added_when_deadline_is_near()
    {
        // Set deadline to 5 days from now (within 7-day threshold)
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->addDays(5)->format('Y-m-d H:i:s'));

        Livewire::test(FantreffenAnmeldung::class)
            ->assertSeeHtml('role="alert"');
    }

    /** @test */
    public function aria_alert_is_not_added_when_deadline_is_far()
    {
        // Set deadline to 30 days from now (beyond 7-day threshold)
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->addDays(30)->format('Y-m-d H:i:s'));

        // Use the service to verify the shouldShowAlert logic
        $deadlineService = new FantreffenDeadlineService();
        $this->assertFalse($deadlineService->shouldShowAlert());
        $this->assertGreaterThan(7, $deadlineService->getDaysRemaining());
        
        // Verify the component reflects this - no role="alert" should appear
        $component = Livewire::test(FantreffenAnmeldung::class);
        $component->assertDontSeeHtml('role="alert"');
    }

    /** @test */
    public function deadline_service_correctly_calculates_alert_threshold()
    {
        // Test at exactly 7 days - should show alert
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->addDays(7)->format('Y-m-d H:i:s'));
        $service = new FantreffenDeadlineService();
        $this->assertTrue($service->shouldShowAlert());
        
        // Test at 8 days - should NOT show alert
        Config::set('services.fantreffen.tshirt_deadline', Carbon::now()->addDays(8)->endOfDay()->format('Y-m-d H:i:s'));
        $service = new FantreffenDeadlineService();
        $this->assertFalse($service->shouldShowAlert());
    }
}
