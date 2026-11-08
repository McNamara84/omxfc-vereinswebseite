<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('termine page contains calendar links', function (): void {
    $this->get('/termine')->assertOk()->assertSee('calendar.google.com/calendar/embed')->assertSee('calendar.google.com/calendar/u/0?cid=');
});

test('changelog page contains release notes container', function (): void {
    $this->get('/changelog')->assertOk()->assertSeeLivewire('changelog');
});
