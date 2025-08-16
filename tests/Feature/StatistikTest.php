<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\File;
use App\Models\Book;
use App\Models\Review;
use App\Models\ReviewComment;

class StatistikTest extends TestCase
{
    use RefreshDatabase;

     private string $testStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStoragePath = base_path('storage/testing');
        $this->app->useStoragePath($this->testStoragePath);
        File::ensureDirectoryExists($this->testStoragePath . '/app/private');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testStoragePath);

        parent::tearDown();
    }

    private function actingMemberWithPoints(int $points): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Mitglied']);
        $user->incrementTeamPoints($points);
        return $user;
    }

    private function createDataFile(): void
    {
        $data = [
            [
                'nummer'    => 1,
                'titel'     => 'Roman1',
                'text'      => ['Author1'],
                'bewertung' => 4.0,
                'stimmen'   => 10,
                'personen'  => ['Char1', 'Char2'],
            ],
            [
                'nummer'    => 2,
                'titel'     => 'Roman2',
                'text'      => ['Author1', 'Author2'],
                'bewertung' => 5.0,
                'stimmen'   => 20,
                'personen'  => ['Char2', 'Char3'],
            ],
        ];
        $path = storage_path('app/private/maddrax.json');
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, json_encode($data));
    }

    private function createHardcoversFile(): void
    {
        $data = [];
        for ($i = 1; $i <= 30; $i++) {
            // Start ratings at 3.1 and increment by 0.1 for each hardcover
            $rating = 3.0 + ($i * 0.1);
            $data[] = [
                'nummer' => $i,
                'titel' => 'HC' . $i,
                'bewertung' => $rating,
            ];
        }
        $path = storage_path('app/private/hardcovers.json');
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, json_encode($data));
    }

    public function test_statistics_page_shows_computed_values(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(11);
        $this->actingAs($user);

        $response = $this->get('/statistik');

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

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('Top 10 Autor');
        $response->assertSee('Author2');
    }

    public function test_top_author_statistic_locked_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(7);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('wird ab');
        $response->assertSee('8');
    }

    public function test_statistics_page_returns_500_when_file_missing(): void
    {
        $user = $this->actingMemberWithPoints(11);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertStatus(500);
    }

    public function test_teamplayer_table_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(4);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('Top Teamplayer');
        $response->assertSee('Author2');
    }

    public function test_teamplayer_table_hidden_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(1);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertDontSee('Top Teamplayer');
    }

    public function test_character_table_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(16);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('Top 10 Charaktere');
        $response->assertSee('Char2');
    }

    public function test_character_statistic_locked_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(9);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('wird ab');
        $response->assertSee('10');
    }

    public function test_review_statistics_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $team = Team::where('name', 'Mitglieder')->first();

        $viewer = $this->actingMemberWithPoints(12);
        $this->actingAs($viewer);

        $user2 = User::factory()->create(['current_team_id' => $team->id]);
        $user3 = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user2, ['role' => 'Mitglied']);
        $team->users()->attach($user3, ['role' => 'Mitglied']);

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

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('Rezensionen unserer Mitglieder');
        $response->assertSee('RA');
        $response->assertSee($viewer->name);
    }

    public function test_review_statistics_hidden_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(11);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertDontSee('Rezensionen unserer Mitglieder');
    }

    public function test_afra_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(21);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('Bewertungen des Afra-Zyklus');
    }

    public function test_afra_cycle_chart_hidden_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(20);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertDontSee('Bewertungen des Afra-Zyklus');
    }

    public function test_antarktis_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(22);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('Bewertungen des Antarktis-Zyklus');
    }

    public function test_antarktis_cycle_chart_hidden_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(21);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertDontSee('Bewertungen des Antarktis-Zyklus');
    }

    public function test_schatten_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(23);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('Bewertungen des Schatten-Zyklus');
    }

    public function test_schatten_cycle_chart_hidden_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(22);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertDontSee('Bewertungen des Schatten-Zyklus');
    }

    public function test_ursprung_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(24);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('Bewertungen des Ursprung-Zyklus');
    }

    public function test_ursprung_cycle_chart_hidden_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(23);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertDontSee('Bewertungen des Ursprung-Zyklus');
    }

    public function test_streiter_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(25);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('Bewertungen des Streiter-Zyklus');
    }

    public function test_streiter_cycle_chart_hidden_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(24);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertDontSee('Bewertungen des Streiter-Zyklus');
    }

    public function test_archivar_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(26);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('Bewertungen des Archivar-Zyklus');
    }

    public function test_archivar_cycle_chart_hidden_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(25);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertDontSee('Bewertungen des Archivar-Zyklus');
    }

    public function test_zeitsprung_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(27);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('Bewertungen des Zeitsprung-Zyklus');
    }

    public function test_zeitsprung_cycle_chart_hidden_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(26);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertDontSee('Bewertungen des Zeitsprung-Zyklus');
    }

    public function test_fremdwelt_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(28);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('Bewertungen des Fremdwelt-Zyklus');
    }

    public function test_fremdwelt_cycle_chart_hidden_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(27);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertDontSee('Bewertungen des Fremdwelt-Zyklus');
    }

    public function test_parallelwelt_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(29);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('Bewertungen des Parallelwelt-Zyklus');
    }

    public function test_parallelwelt_cycle_chart_hidden_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(28);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertDontSee('Bewertungen des Parallelwelt-Zyklus');
    }

    public function test_weltenriss_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(30);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('Bewertungen des Weltenriss-Zyklus');
    }

    public function test_weltenriss_cycle_chart_hidden_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(29);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertDontSee('Bewertungen des Weltenriss-Zyklus');
    }

    public function test_amraka_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(31);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('Bewertungen des Amraka-Zyklus');
    }

    public function test_amraka_cycle_chart_hidden_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(30);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertDontSee('Bewertungen des Amraka-Zyklus');
    }

    public function test_weltrat_cycle_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(32);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('Bewertungen des Weltrat-Zyklus');
    }

    public function test_weltrat_cycle_chart_hidden_below_threshold(): void
    {
        $this->createDataFile();
        $user = $this->actingMemberWithPoints(31);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertDontSee('Bewertungen des Weltrat-Zyklus');
    }

    public function test_hardcover_chart_visible_with_enough_points(): void
    {
        $this->createDataFile();
        $this->createHardcoversFile();
        $user = $this->actingMemberWithPoints(40);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertSee('Bewertungen der Hardcover');
    }

    public function test_hardcover_chart_hidden_below_threshold(): void
    {
        $this->createDataFile();
        $this->createHardcoversFile();
        $user = $this->actingMemberWithPoints(39);
        $this->actingAs($user);

        $response = $this->get('/statistik');

        $response->assertOk();
        $response->assertDontSee('Bewertungen der Hardcover');
    }
}
