<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\ReviewComment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class StatistikTest extends TestCase
{
    use RefreshDatabase;

    private string $testStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStoragePath = base_path('storage/testing');
        $this->app->useStoragePath($this->testStoragePath);
        File::ensureDirectoryExists($this->testStoragePath.'/app/private');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testStoragePath);

        parent::tearDown();
    }

    private function actingMemberWithPoints(int $points): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Mitglied->value]);
        $user->incrementTeamPoints($points);

        return $user;
    }

    private function createDataFile(): void
    {
        $data = [
            [
                'nummer' => 1,
                'titel' => 'Roman1',
                'text' => ['Author1'],
                'bewertung' => 4.0,
                'stimmen' => 10,
                'personen' => ['Char1', 'Char2'],
                'schlagworte' => ['Thema1', 'Thema2'],
            ],
            [
                'nummer' => 2,
                'titel' => 'Roman2',
                'text' => ['Author1', 'Author2'],
                'bewertung' => 5.0,
                'stimmen' => 20,
                'personen' => ['Char2', 'Char3'],
                'schlagworte' => ['Thema2', 'Thema3'],
            ],
        ];
        $path = storage_path('app/private/maddrax.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, json_encode($data));
    }

    private function createHardcoversFile(): void
    {
        $data = [];
        for ($i = 1; $i <= 30; $i++) {
            // Start ratings at 3.1 and increment by 0.1 for each hardcover
            $rating = 3.1 + (($i - 1) * 0.1); // subtract 1 because loop starts at 1
            $data[] = [
                'nummer' => $i,
                'titel' => 'HC'.$i,
                'bewertung' => $rating,
                'text' => ['HC Author'.(($i % 2) + 1)],
            ];
        }
        $path = storage_path('app/private/hardcovers.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, json_encode($data));
    }

    private function createMissionMarsFile(): void
    {
        $data = [];
        for ($i = 1; $i <= 12; $i++) {
            $data[] = [
                'nummer' => $i,
                'titel' => 'Mission Mars '.$i,
                'bewertung' => 3.5 + ($i * 0.05),
            ];
        }

        $path = storage_path('app/private/missionmars.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, json_encode($data));
    }

    private function createTeamplayerDataFile(): void
    {
        $data = [];
        $coAuthor = 'Common';
        // Authors 1-10 appear twice
        for ($i = 1; $i <= 10; $i++) {
            for ($j = 1; $j <= 2; $j++) {
                $data[] = [
                    'nummer' => count($data) + 1,
                    'titel' => 'Roman'.$i.'-'.$j,
                    'text' => ['Author'.$i, $coAuthor],
                    'bewertung' => 4.0,
                    'stimmen' => 10,
                    'personen' => [],
                ];
            }
        }
        // Author11 appears once
        $data[] = [
            'nummer' => count($data) + 1,
            'titel' => 'Roman-extra',
            'text' => ['Author11', $coAuthor],
            'bewertung' => 3.0,
            'stimmen' => 5,
            'personen' => [],
        ];
        $path = storage_path('app/private/maddrax.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, json_encode($data));
    }

    public function test_statistics_page_shows_computed_values(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(11);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('4,50');
        $response->assertSee('30');
        $response->assertSee('15,00');
        $response->assertSee('Roman2');
    }

    public function test_top_author_table_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(10);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Top 10 Autor');
        $response->assertSee('Author2');
    }

    public function test_top_author_statistic_locked_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(7);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('wird ab');
        $response->assertSee('8');
    }

    public function test_statistics_page_returns_500_when_file_missing(): void
    {
        $user = $this->actingMemberWithPoints(11);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertStatus(500);
    }

    public function test_teamplayer_table_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(4);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Top Teamplayer');
        $response->assertSee('Author2');
    }

    public function test_teamplayer_table_locked_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(1);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Top Teamplayer');
        $response->assertSee('4 Baxx');
    }

    public function test_teamplayer_table_limits_to_top_10(): void
    {
        $this->createTeamplayerDataFile();
        $user = $this->actingMemberWithPoints(4);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertViewHas('teamplayerTable', function ($table) {
            $authors = $table->pluck('author');

            return $authors->count() === 10
                && ! $authors->contains('Author11');
        });
    }

    public function test_browser_usage_statistics_hidden_on_member_page(): void
    {
        $this->createDataFile();
        $viewer = $this->actingMemberWithPoints(3);
        $this->actingAs($viewer);

        $otherMember = $this->actingMemberWithPoints(3);

        DB::table('sessions')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $viewer->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        DB::table('sessions')->insert([
            'id' => Str::uuid()->toString(),
            'user_id' => $otherMember->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertDontSee('Browsernutzung unserer Mitglieder');
        $response->assertDontSee('Beliebteste Browser');
        $response->assertDontSee('Browser-Familien');
        $response->assertDontSee('window.browserUsageLabels', false);
        $response->assertDontSee('window.browserFamilyLabels', false);
    }

    public function test_character_table_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(16);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Top 10 Charaktere');
        $response->assertSee('Char2');
    }

    public function test_character_statistic_locked_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(9);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('wird ab');
        $response->assertSee('10');
    }

    public function test_review_statistics_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $team = Team::membersTeam();

        $viewer = $this->actingMemberWithPoints(12);
        $this->actingAs($viewer);

        $user2 = User::factory()->create(['current_team_id' => $team->id]);
        $user3 = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user2, ['role' => \App\Enums\Role::Mitglied->value]);
        $team->users()->attach($user3, ['role' => \App\Enums\Role::Mitglied->value]);

        $book1 = Book::create(['roman_number' => 1, 'title' => 'B1', 'author' => 'A1']);
        $book2 = Book::create(['roman_number' => 2, 'title' => 'B2', 'author' => 'A2']);

        $reviewA = Review::create(['team_id' => $team->id, 'user_id' => $viewer->id, 'book_id' => $book1->id, 'title' => 'RA', 'content' => str_repeat('A', 100)]);
        $reviewB = Review::create(['team_id' => $team->id, 'user_id' => $viewer->id, 'book_id' => $book1->id, 'title' => 'RB', 'content' => str_repeat('A', 120)]);
        Review::create(['team_id' => $team->id, 'user_id' => $viewer->id, 'book_id' => $book2->id, 'title' => 'RC', 'content' => str_repeat('A', 80)]);
        Review::create(['team_id' => $team->id, 'user_id' => $user2->id, 'book_id' => $book1->id, 'title' => 'RD', 'content' => 'X']);
        Review::create(['team_id' => $team->id, 'user_id' => $user2->id, 'book_id' => $book2->id, 'title' => 'RE', 'content' => 'Y']);
        Review::create(['team_id' => $team->id, 'user_id' => $user3->id, 'book_id' => $book1->id, 'title' => 'RF', 'content' => 'Z']);

        ReviewComment::create(['review_id' => $reviewA->id, 'user_id' => $user2->id, 'content' => 'C1']);
        ReviewComment::create(['review_id' => $reviewA->id, 'user_id' => $user3->id, 'content' => 'C2']);
        ReviewComment::create(['review_id' => $reviewB->id, 'user_id' => $user2->id, 'content' => 'C3']);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Rezensionen unserer Mitglieder');
        $response->assertSee('RA');
        $response->assertSee($viewer->name);
    }

    public function test_review_statistics_locked_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(11);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Rezensionen unserer Mitglieder');
        $response->assertSee('12 Baxx');
    }

    public function test_afra_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(21);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Afra-Zyklus');
    }

    public function test_afra_cycle_chart_locked_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(20);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Afra-Zyklus');
        $response->assertSee('21 Baxx');
    }

    public function test_antarktis_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(22);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Antarktis-Zyklus');
    }

    public function test_antarktis_cycle_chart_locked_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(21);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Antarktis-Zyklus');
        $response->assertSee('22 Baxx');
    }

    public function test_schatten_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(23);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Schatten-Zyklus');
    }

    public function test_schatten_cycle_chart_locked_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(22);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Schatten-Zyklus');
        $response->assertSee('23 Baxx');
    }

    public function test_ursprung_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(24);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Ursprung-Zyklus');
    }

    public function test_ursprung_cycle_chart_locked_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(23);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Ursprung-Zyklus');
        $response->assertSee('24 Baxx');
    }

    public function test_streiter_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(25);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Streiter-Zyklus');
    }

    public function test_streiter_cycle_chart_locked_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(24);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Streiter-Zyklus');
        $response->assertSee('25 Baxx');
    }

    public function test_archivar_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(26);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Archivar-Zyklus');
    }

    public function test_archivar_cycle_chart_locked_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(25);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Archivar-Zyklus');
        $response->assertSee('26 Baxx');
    }

    public function test_zeitsprung_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(27);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Zeitsprung-Zyklus');
    }

    public function test_zeitsprung_cycle_chart_locked_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(26);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Zeitsprung-Zyklus');
        $response->assertSee('27 Baxx');
    }

    public function test_fremdwelt_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(28);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Fremdwelt-Zyklus');
    }

    public function test_fremdwelt_cycle_chart_locked_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(27);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Fremdwelt-Zyklus');
        $response->assertSee('28 Baxx');
    }

    public function test_parallelwelt_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(29);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Parallelwelt-Zyklus');
    }

    public function test_parallelwelt_cycle_chart_locked_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(28);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Parallelwelt-Zyklus');
        $response->assertSee('29 Baxx');
    }

    public function test_weltenriss_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(30);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Weltenriss-Zyklus');
    }

    public function test_weltenriss_cycle_chart_locked_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(29);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Weltenriss-Zyklus');
        $response->assertSee('30 Baxx');
    }

    public function test_amraka_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(31);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Amraka-Zyklus');
    }

    public function test_amraka_cycle_chart_locked_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(30);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Amraka-Zyklus');
        $response->assertSee('31 Baxx');
    }

    public function test_weltrat_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(32);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Weltrat-Zyklus');
    }

    public function test_weltrat_cycle_chart_locked_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(31);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen des Weltrat-Zyklus');
        $response->assertSee('32 Baxx');
    }

    public function test_hardcover_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $this->createHardcoversFile();
        $user = $this->actingMemberWithPoints(40);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen der Hardcover');
    }

    public function test_hardcover_chart_locked_below_threshold(): void
    {
        $this->createDataFile();
        $this->createHardcoversFile();
        $user = $this->actingMemberWithPoints(39);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen der Hardcover');
        $response->assertSee('40 Baxx');
    }

    public function test_hardcover_author_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $this->createHardcoversFile();
        $user = $this->actingMemberWithPoints(41);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Maddrax-Hardcover je Autor:in');
    }

    public function test_hardcover_author_chart_locked_below_threshold(): void
    {
        $this->createDataFile();
        $this->createHardcoversFile();
        $user = $this->actingMemberWithPoints(40);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Maddrax-Hardcover je Autor:in');
        $response->assertSee('41 Baxx');
    }

    public function test_top_themes_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(42);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('TOP20 Maddrax-Themen');
    }

    public function test_top_themes_locked_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(41);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('TOP20 Maddrax-Themen');
        $response->assertSee('42 Baxx');
    }

    public function test_top_themes_ignore_books_with_few_votes(): void
    {
        $this->createDataFile();
        $path = storage_path('app/private/maddrax.json');
        $data = json_decode(file_get_contents($path), true);
        $data[] = [
            'nummer' => 3,
            'titel' => 'Roman3',
            'text' => ['Author3'],
            'bewertung' => 4.5,
            'stimmen' => 7,
            'personen' => [],
            'schlagworte' => ['LowVotesTheme'],
        ];
        file_put_contents($path, json_encode($data));

        $user = $this->actingMemberWithPoints(42);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('TOP20 Maddrax-Themen');
        $response->assertDontSee('LowVotesTheme');
    }

    public function test_mission_mars_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $this->createMissionMarsFile();
        $user = $this->actingMemberWithPoints(43);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen der Mission Mars-Heftromane');
    }

    public function test_mission_mars_chart_locked_below_threshold(): void
    {
        $this->createDataFile();
        $this->createMissionMarsFile();
        $user = $this->actingMemberWithPoints(42);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('Bewertungen der Mission Mars-Heftromane');
        $response->assertSee('43 Baxx');
    }

    public function test_top_themes_require_minimum_book_count(): void
    {
        $this->createDataFile();
        $path = storage_path('app/private/maddrax.json');
        $data = json_decode(file_get_contents($path), true);
        for ($i = 3; $i <= 7; $i++) {
            $data[] = [
                'nummer' => $i,
                'titel' => 'Roman'.$i,
                'text' => ['Author'.$i],
                'bewertung' => 4.5,
                'stimmen' => 10,
                'personen' => [],
                'schlagworte' => ['PopularTheme'],
            ];
        }
        file_put_contents($path, json_encode($data));

        $user = $this->actingMemberWithPoints(42);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('TOP20 Maddrax-Themen');
        $response->assertSee('PopularTheme');
        $response->assertDontSee('Thema1');
    }

    public function test_favorite_themes_visible_with_enough_points(): void
    {
        $this->createDataFile();
        User::factory()->create(['lieblingsthema' => 'Thema1']);
        User::factory()->create(['lieblingsthema' => 'Thema1']);
        User::factory()->create(['lieblingsthema' => 'Thema2']);
        User::factory()->create(['lieblingsthema' => 'Thema3']);
        $user = $this->actingMemberWithPoints(50);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('TOP10 Lieblingsthemen');
        $response->assertSee('Thema1');
        $response->assertSee('Thema2');
        $response->assertSee('Thema3');
    }

    public function test_favorite_themes_locked_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(49);
        $this->actingAs($user);

        $response = $this->get('/statistiken');

        $response->assertOk();
        $response->assertSee('TOP10 Lieblingsthemen');
        $response->assertSee('50 Baxx');
    }
}
