<?php

namespace Tests\Unit;

use App\Enums\MeetingRhythmType;
use App\Models\Meeting;
use App\Services\MeetingScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected MeetingScheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(MeetingScheduleService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_calculates_next_occurrence_for_monthly_nth_weekday(): void
    {
        Carbon::setTestNow('2026-05-14 12:00');

        $meeting = Meeting::factory()->make([
            'rhythm_type' => MeetingRhythmType::MonthlyNthWeekday,
            'week_of_month' => 3,
            'weekday' => 1,
            'time_from' => '20:00',
        ]);

        $next = $this->service->nextOccurrence($meeting);

        $this->assertNotNull($next);
        $this->assertTrue($next->equalTo(Carbon::parse('2026-05-18 20:00')));
        $this->assertSame('Monatlich am 3. Montag', $this->service->describe($meeting));
    }

    public function test_it_calculates_next_occurrence_for_monthly_day_of_month(): void
    {
        Carbon::setTestNow('2026-05-14 12:00');

        $meeting = Meeting::factory()->monthlyDayOfMonth(3)->make([
            'time_from' => '19:30',
        ]);

        $next = $this->service->nextOccurrence($meeting);

        $this->assertNotNull($next);
        $this->assertTrue($next->equalTo(Carbon::parse('2026-06-03 19:30')));
        $this->assertSame('Monatlich jeden 3.', $this->service->describe($meeting));
    }

    public function test_it_calculates_next_occurrence_for_every_n_weeks(): void
    {
        Carbon::setTestNow('2026-05-29 10:00');

        $meeting = Meeting::factory()->everyNWeeks(2, '2026-05-14')->make([
            'time_from' => '20:00',
        ]);

        $next = $this->service->nextOccurrence($meeting);

        $this->assertNotNull($next);
        $this->assertTrue($next->equalTo(Carbon::parse('2026-06-11 20:00')));
        $this->assertSame('Zweiwöchentlich beginnend am 14.05.2026', $this->service->describe($meeting));
    }

    public function test_it_returns_null_for_note_only_meetings(): void
    {
        $meeting = Meeting::factory()->noteOnly('Nach Abstimmung im Forum')->make();

        $this->assertNull($this->service->nextOccurrence($meeting));
        $this->assertSame('Nach Abstimmung im Forum', $this->service->describe($meeting));
    }
}
