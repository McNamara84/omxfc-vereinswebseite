<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Enums\MeetingRhythmType;
use App\Models\Meeting;
use App\Services\MeetingScheduleService;
use Carbon\Carbon;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(MeetingScheduleServiceTestFixtures::class);

beforeEach(function (): void {
    $this->service = app(MeetingScheduleService::class);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('it calculates next occurrence for monthly nth weekday', function (): void {
    Carbon::setTestNow('2026-05-14 12:00');
    $meeting = Meeting::factory()->make(['rhythm_type' => MeetingRhythmType::MonthlyNthWeekday, 'week_of_month' => 3, 'weekday' => 1, 'time_from' => '20:00']);
    $next = $this->service->nextOccurrence($meeting);
    expect($next)->not->toBeNull()
        ->and($next->equalTo(Carbon::parse('2026-05-18 20:00')))->toBeTrue()
        ->and($this->service->describe($meeting))->toBe('Monatlich am 3. Montag');
});

test('it calculates next occurrence for monthly day of month', function (): void {
    Carbon::setTestNow('2026-05-14 12:00');
    $meeting = Meeting::factory()->monthlyDayOfMonth(3)->make(['time_from' => '19:30']);
    $next = $this->service->nextOccurrence($meeting);
    expect($next)->not->toBeNull()
        ->and($next->equalTo(Carbon::parse('2026-06-03 19:30')))->toBeTrue()
        ->and($this->service->describe($meeting))->toBe('Monatlich jeden 3.');
});

test('it calculates next occurrence for every n weeks', function (): void {
    Carbon::setTestNow('2026-05-29 10:00');
    $meeting = Meeting::factory()->everyNWeeks(2, '2026-05-14')->make(['time_from' => '20:00']);
    $next = $this->service->nextOccurrence($meeting);
    expect($next)->not->toBeNull()
        ->and($next->equalTo(Carbon::parse('2026-06-11 20:00')))->toBeTrue()
        ->and($this->service->describe($meeting))->toBe('Zweiwöchentlich beginnend am 14.05.2026');
});

test('it keeps local wall clock time across dst for weekly meetings', function (): void {
    Carbon::setTestNow('2026-03-31 12:00');
    $meeting = Meeting::factory()->everyNWeeks(1, '2026-03-25')->make(['time_from' => '20:00']);
    $next = $this->service->nextOccurrence($meeting);
    expect($next)->not->toBeNull()
        ->and($next->format('Y-m-d H:i'))->toBe('2026-04-01 20:00')
        ->and($next->timezoneName)->toBe('Europe/Berlin');
});

test('it returns null for calculable meeting without start time', function (): void {
    Carbon::setTestNow('2026-05-03 12:00');
    $meeting = Meeting::factory()->monthlyDayOfMonth(3)->make(['time_from' => null]);
    expect($this->service->nextOccurrence($meeting))->toBeNull();
});

test('it returns null for note only meetings', function (): void {
    $meeting = Meeting::factory()->noteOnly('Nach Abstimmung im Forum')->make();
    expect($this->service->nextOccurrence($meeting))->toBeNull()
        ->and($this->service->describe($meeting))->toBe('Nach Abstimmung im Forum');
});
