<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_termine_page_contains_calendar_links(): void
    {
        $this->get('/termine')
            ->assertOk()
            ->assertSee('calendar.google.com/calendar/embed')
            ->assertSee('calendar.google.com/calendar/u/0?cid=');
    }

    public function test_changelog_page_contains_release_notes_container(): void
    {
        $this->get('/changelog')
            ->assertOk()
            ->assertSee('release-notes');
    }
}
