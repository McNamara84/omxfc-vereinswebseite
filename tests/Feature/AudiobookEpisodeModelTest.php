<?php

namespace Tests\Feature;

use App\Models\AudiobookEpisode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AudiobookEpisodeModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_episode_type_accessor(): void
    {
        $special = AudiobookEpisode::create([
            'episode_number' => 'SE1',
            'title' => 'Special',
            'author' => 'Author',
            'planned_release_date' => '2025-01-01',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 0,
            'roles_filled' => 0,
            'notes' => null,
        ]);

        $regular = AudiobookEpisode::create([
            'episode_number' => 'F1',
            'title' => 'Regular',
            'author' => 'Author',
            'planned_release_date' => '2025-01-01',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 0,
            'roles_filled' => 0,
            'notes' => null,
        ]);

        $this->assertSame('se', $special->episode_type);
        $this->assertTrue($special->isSpecialEdition());
        $this->assertSame('regular', $regular->episode_type);
        $this->assertFalse($regular->isSpecialEdition());
    }

    public function test_all_roles_filled_accessor(): void
    {
        $complete = AudiobookEpisode::create([
            'episode_number' => 'F2',
            'title' => 'Complete',
            'author' => 'Author',
            'planned_release_date' => '2025-01-01',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 2,
            'roles_filled' => 2,
            'notes' => null,
        ]);

        $incomplete = AudiobookEpisode::create([
            'episode_number' => 'F3',
            'title' => 'Incomplete',
            'author' => 'Author',
            'planned_release_date' => '2025-01-01',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 2,
            'roles_filled' => 1,
            'notes' => null,
        ]);

        $this->assertTrue($complete->all_roles_filled);
        $this->assertFalse($incomplete->all_roles_filled);
    }

    public function test_release_year_accessor(): void
    {
        $fullDate = AudiobookEpisode::create([
            'episode_number' => 'F4',
            'title' => 'Full',
            'author' => 'Author',
            'planned_release_date' => '15.06.2024',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 0,
            'roles_filled' => 0,
            'notes' => null,
        ]);

        $monthYear = AudiobookEpisode::create([
            'episode_number' => 'F5',
            'title' => 'MonthYear',
            'author' => 'Author',
            'planned_release_date' => '06.2024',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 0,
            'roles_filled' => 0,
            'notes' => null,
        ]);

        $yearOnly = AudiobookEpisode::create([
            'episode_number' => 'F6',
            'title' => 'YearOnly',
            'author' => 'Author',
            'planned_release_date' => '2024',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 0,
            'roles_filled' => 0,
            'notes' => null,
        ]);

        $this->assertSame(2024, $fullDate->release_year);
        $this->assertSame(2024, $monthYear->release_year);
        $this->assertSame(2024, $yearOnly->release_year);
    }

    public function test_release_year_accessor_handles_invalid_date(): void
    {
        $invalid = AudiobookEpisode::create([
            'episode_number' => 'F7',
            'title' => 'Invalid',
            'author' => 'Author',
            'planned_release_date' => 'not-a-date',
            'status' => 'Skripterstellung',
            'responsible_user_id' => null,
            'progress' => 0,
            'roles_total' => 0,
            'roles_filled' => 0,
            'notes' => null,
        ]);

        $this->assertNull($invalid->planned_release_date_parsed);
        $this->assertNull($invalid->release_year);
    }
}
