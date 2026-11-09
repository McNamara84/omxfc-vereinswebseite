<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Book;
use App\Models\Review;
use App\Models\ReviewComment;
use App\Models\Reward;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Large;
use Tests\Support\PhpunitMetadata;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(StatistikTestFixtures::class);

beforeEach(function (): void {
    $this->testStoragePath = base_path('storage/testing');
    $this->app->useStoragePath($this->testStoragePath);
    File::ensureDirectoryExists($this->testStoragePath.'/app/private');
});

afterEach(function (): void {
    File::deleteDirectory($this->testStoragePath);
});

test('statistics page shows computed values', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(11);
    $this->purchaseStatistikReward($user, 'maddraxikon-bewertungen', 'top-romane');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('4,50');
    $response->assertSee('30');
    $response->assertSee('15,00');
    $response->assertSee('Roman2');
});

test('top author table visible when purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(10);
    $this->purchaseStatistikReward($user, 'author-chart');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Top 10 Autor');
    $response->assertSee('Author2');
    $response->assertDontSee('statistik-purchase-author-chart');
});

test('top author statistic locked when not purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(7);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Diese Statistik kostet');
    $response->assertSee($this->expectedBaxxLabel('author-chart'));
});

test('statistics page works gracefully when file missing', function (): void {
    $user = $this->actingMemberWithPoints(11);
    $this->actingAs($user);
    // Keine Testdateien erstellt - MaddraxDataService gibt leere Collections zurück
    $response = $this->get('/statistiken');
    // Seite sollte trotzdem laden (graceful degradation statt 500-Fehler)
    $response->assertOk();
});

test('teamplayer table visible when purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(4);
    $this->purchaseStatistikReward($user, 'teamplayer');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Top Teamplayer');
    $response->assertSee('Author2');
    $response->assertDontSee('statistik-purchase-teamplayer');
});

test('teamplayer table locked when not purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(1);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Top Teamplayer');
    $response->assertSee($this->expectedBaxxLabel('teamplayer'));
});

test('teamplayer table limits to top 10', function (): void {
    $this->createTeamplayerDataFile();
    $user = $this->actingMemberWithPoints(4);
    $this->purchaseStatistikReward($user, 'teamplayer');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertViewHas('teamplayerTable', function ($table) {
        $authors = $table->pluck('author');

        return $authors->count() === 10 && ! $authors->contains('Author11');
    });
});

test('browser usage statistics hidden on member page', function (): void {
    $this->createDataFile();
    $viewer = $this->actingMemberWithPoints(3);
    $this->actingAs($viewer);
    $otherMember = $this->actingMemberWithPoints(3);
    $this->createSnapshot($viewer->id, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', now());
    $this->createSnapshot($otherMember->id, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15', now());
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertDontSee('Browsernutzung unserer Mitglieder');
    $response->assertDontSee('Beliebteste Browser');
    $response->assertDontSee('Browser-Familien');
    $response->assertDontSee('window.browserUsageLabels', false);
    $response->assertDontSee('window.browserFamilyLabels', false);
});

test('character table visible when purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(16);
    $this->purchaseStatistikReward($user, 'top-charaktere');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Top 10 Charaktere');
    $response->assertSee('Char2');
    $response->assertDontSee('statistik-purchase-top-charaktere');
});

test('character statistic locked when not purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(9);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Diese Statistik kostet');
    $response->assertSee($this->expectedBaxxLabel('top-charaktere'));
});

test('review statistics visible when purchased', function (): void {
    $this->createDataFile();
    $team = Team::membersTeam();
    $viewer = $this->actingMemberWithPoints(12);
    $this->purchaseStatistikReward($viewer, 'mitglieds-rezensionen');
    $this->actingAs($viewer);
    $user2 = User::factory()->create(['current_team_id' => $team->id]);
    $user3 = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($user2, ['role' => Role::Mitglied->value]);
    $team->users()->attach($user3, ['role' => Role::Mitglied->value]);
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
});

test('review statistics locked when not purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(11);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Rezensionen unserer Mitglieder');
    $response->assertSee($this->expectedBaxxLabel('mitglieds-rezensionen'));
});

test('afra cycle chart visible when purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(21);
    $this->purchaseStatistikReward($user, 'zyklus-afra');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Afra-Zyklus');
});

test('afra cycle chart locked when not purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(20);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Afra-Zyklus');
    $response->assertSee($this->expectedBaxxLabel('zyklus-afra'));
});

test('antarktis cycle chart visible when purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(22);
    $this->purchaseStatistikReward($user, 'zyklus-antarktis');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Antarktis-Zyklus');
});

test('antarktis cycle chart locked when not purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(21);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Antarktis-Zyklus');
    $response->assertSee($this->expectedBaxxLabel('zyklus-antarktis'));
});

test('schatten cycle chart visible when purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(23);
    $this->purchaseStatistikReward($user, 'zyklus-schatten');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Schatten-Zyklus');
});

test('schatten cycle chart locked when not purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(22);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Schatten-Zyklus');
    $response->assertSee($this->expectedBaxxLabel('zyklus-schatten'));
});

test('ursprung cycle chart visible when purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(24);
    $this->purchaseStatistikReward($user, 'zyklus-ursprung');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Ursprung-Zyklus');
});

test('ursprung cycle chart locked when not purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(23);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Ursprung-Zyklus');
    $response->assertSee($this->expectedBaxxLabel('zyklus-ursprung'));
});

test('streiter cycle chart visible when purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(25);
    $this->purchaseStatistikReward($user, 'zyklus-streiter');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Streiter-Zyklus');
});

test('streiter cycle chart locked when not purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(24);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Streiter-Zyklus');
    $response->assertSee($this->expectedBaxxLabel('zyklus-streiter'));
});

test('archivar cycle chart visible when purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(26);
    $this->purchaseStatistikReward($user, 'zyklus-archivar');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Archivar-Zyklus');
});

test('archivar cycle chart locked when not purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(25);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Archivar-Zyklus');
    $response->assertSee($this->expectedBaxxLabel('zyklus-archivar'));
});

test('zeitsprung cycle chart visible when purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(27);
    $this->purchaseStatistikReward($user, 'zyklus-zeitsprung');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Zeitsprung-Zyklus');
});

test('zeitsprung cycle chart locked when not purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(26);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Zeitsprung-Zyklus');
    $response->assertSee($this->expectedBaxxLabel('zyklus-zeitsprung'));
});

test('fremdwelt cycle chart visible when purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(28);
    $this->purchaseStatistikReward($user, 'zyklus-fremdwelt');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Fremdwelt-Zyklus');
});

test('fremdwelt cycle chart locked when not purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(27);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Fremdwelt-Zyklus');
    $response->assertSee($this->expectedBaxxLabel('zyklus-fremdwelt'));
});

test('parallelwelt cycle chart visible when purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(29);
    $this->purchaseStatistikReward($user, 'zyklus-parallelwelt');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Parallelwelt-Zyklus');
});

test('parallelwelt cycle chart locked when not purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(28);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Parallelwelt-Zyklus');
    $response->assertSee($this->expectedBaxxLabel('zyklus-parallelwelt'));
});

test('weltenriss cycle chart visible when purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(30);
    $this->purchaseStatistikReward($user, 'zyklus-weltenriss');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Weltenriss-Zyklus');
});

test('weltenriss cycle chart locked when not purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(29);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Weltenriss-Zyklus');
    $response->assertSee($this->expectedBaxxLabel('zyklus-weltenriss'));
});

test('amraka cycle chart visible when purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(31);
    $this->purchaseStatistikReward($user, 'zyklus-amraka');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Amraka-Zyklus');
});

test('amraka cycle chart locked when not purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(30);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Amraka-Zyklus');
    $response->assertSee($this->expectedBaxxLabel('zyklus-amraka'));
});

test('weltrat cycle chart visible when purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(32);
    $this->purchaseStatistikReward($user, 'zyklus-weltrat');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Weltrat-Zyklus');
});

test('weltrat cycle chart locked when not purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(31);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen des Weltrat-Zyklus');
    $response->assertSee($this->expectedBaxxLabel('zyklus-weltrat'));
});

test('hardcover chart visible when purchased', function (): void {
    $this->createDataFile();
    $this->createHardcoversFile();
    $user = $this->actingMemberWithPoints(40);
    $this->purchaseStatistikReward($user, 'hardcover-bewertungen');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen der Hardcover');
});

test('hardcover chart locked when not purchased', function (): void {
    $this->createDataFile();
    $this->createHardcoversFile();
    $user = $this->actingMemberWithPoints(39);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen der Hardcover');
    $response->assertSee($this->expectedBaxxLabel('hardcover-bewertungen'));
});

test('hardcover author chart visible when purchased', function (): void {
    $this->createDataFile();
    $this->createHardcoversFile();
    $user = $this->actingMemberWithPoints(41);
    $this->purchaseStatistikReward($user, 'hardcover-autoren');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Maddrax-Hardcover je Autor:in');
});

test('hardcover author chart locked when not purchased', function (): void {
    $this->createDataFile();
    $this->createHardcoversFile();
    $user = $this->actingMemberWithPoints(40);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Maddrax-Hardcover je Autor:in');
    $response->assertSee($this->expectedBaxxLabel('hardcover-autoren'));
});

test('top themes visible when purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(42);
    $this->purchaseStatistikReward($user, 'top-themen');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('TOP20 Maddrax-Themen');
});

test('top themes locked when not purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(41);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('TOP20 Maddrax-Themen');
    $response->assertSee($this->expectedBaxxLabel('top-themen'));
});

test('top themes ignore books with few votes', function (): void {
    $this->createDataFile();
    $path = storage_path('app/private/maddrax.json');
    $data = json_decode(file_get_contents($path), true);
    $data[] = ['nummer' => 3, 'titel' => 'Roman3', 'text' => ['Author3'], 'bewertung' => 4.5, 'stimmen' => 7, 'personen' => [], 'schlagworte' => ['LowVotesTheme']];
    file_put_contents($path, json_encode($data));
    $user = $this->actingMemberWithPoints(42);
    $this->purchaseStatistikReward($user, 'top-themen');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('TOP20 Maddrax-Themen');
    $response->assertDontSee('LowVotesTheme');
});

test('mission mars chart visible when purchased', function (): void {
    $this->createDataFile();
    $this->createMissionMarsFile();
    $user = $this->actingMemberWithPoints(44);
    $this->purchaseStatistikReward($user, 'mission-mars-bewertungen', 'mission-mars-autoren');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen der Mission Mars-Heftromane');
    $response->assertSee('Mission Mars-Heftromane je Autor:in');
    $response->assertSee('Mission Mars Autor 1');
    $response->assertSee('Mission Mars Co-Autor');
});

test('mission mars chart locked when not purchased', function (): void {
    $this->createDataFile();
    $this->createMissionMarsFile();
    $user = $this->actingMemberWithPoints(43);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen der Mission Mars-Heftromane');
    $response->assertSee('Mission Mars-Heftromane je Autor:in');
    $response->assertSee($this->expectedBaxxLabel('mission-mars-bewertungen'));
});

test('volk der tiefe chart visible when purchased', function (): void {
    $this->createDataFile();
    $this->createVolkDerTiefeFile();
    $user = $this->actingMemberWithPoints(46);
    $this->purchaseStatistikReward($user, 'volk-der-tiefe-bewertungen', 'volk-der-tiefe-autoren');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen der Das Volk der Tiefe-Heftromane');
    $response->assertSee('Das Volk der Tiefe-Heftromane je Autor:in');
    $response->assertSee('Volk der Tiefe Autor 1');
    $response->assertSee('Volk der Tiefe Co-Autor');
});

test('volk der tiefe ratings chart locked when not purchased', function (): void {
    $this->createDataFile();
    $this->createVolkDerTiefeFile();
    $user = $this->actingMemberWithPoints(44);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen der Das Volk der Tiefe-Heftromane');
    $response->assertSee('Das Volk der Tiefe-Heftromane je Autor:in');
    $response->assertSee($this->expectedBaxxLabel('volk-der-tiefe-bewertungen'));
});

test('volk der tiefe author chart locked when not purchased', function (): void {
    $this->createDataFile();
    $this->createVolkDerTiefeFile();
    $user = $this->actingMemberWithPoints(45);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen der Das Volk der Tiefe-Heftromane');
    $response->assertSee('Das Volk der Tiefe-Heftromane je Autor:in');
    $response->assertSee($this->expectedBaxxLabel('volk-der-tiefe-autoren'));
});

test('zweitausendzwoelf chart visible when purchased', function (): void {
    $this->createDataFile();
    $this->createZweitausendzwoelfFile();
    $user = $this->actingMemberWithPoints(48);
    $this->purchaseStatistikReward($user, 'zweitausendzwoelf-bewertungen', 'zweitausendzwoelf-autoren');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen der 2012-Heftromane');
    $response->assertSee('2012-Heftromane je Autor:in');
    $response->assertSee('2012 Autor 1');
    $response->assertSee('2012 Co-Autor');
});

test('zweitausendzwoelf ratings chart locked when not purchased', function (): void {
    $this->createDataFile();
    $this->createZweitausendzwoelfFile();
    $user = $this->actingMemberWithPoints(46);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen der 2012-Heftromane');
    $response->assertSee('2012-Heftromane je Autor:in');
    $response->assertSee($this->expectedBaxxLabel('zweitausendzwoelf-bewertungen'));
});

test('zweitausendzwoelf author chart locked when not purchased', function (): void {
    $this->createDataFile();
    $this->createZweitausendzwoelfFile();
    $user = $this->actingMemberWithPoints(47);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen der 2012-Heftromane');
    $response->assertSee('2012-Heftromane je Autor:in');
    $response->assertSee($this->expectedBaxxLabel('zweitausendzwoelf-autoren'));
});

test('abenteurer charts visible when purchased', function (): void {
    $this->createDataFile();
    $this->createAbenteurerFile();
    $user = $this->actingMemberWithPoints(34);
    $this->purchaseStatistikReward($user, 'abenteurer-bewertungen', 'abenteurer-autoren');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen der Die Abenteurer-Heftromane');
    $response->assertSee('Die Abenteurer-Heftromane je Autor:in');
    $response->assertSee('Abenteurer Autor 1');
    $response->assertSee('Abenteurer Co-Autor');
});

test('abenteurer ratings chart locked when not purchased', function (): void {
    $this->createDataFile();
    $this->createAbenteurerFile();
    $user = $this->actingMemberWithPoints(32);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen der Die Abenteurer-Heftromane');
    $response->assertSee('Die Abenteurer-Heftromane je Autor:in');
    $response->assertSee($this->expectedBaxxLabel('abenteurer-bewertungen'));
});

test('abenteurer author chart locked when not purchased', function (): void {
    $this->createDataFile();
    $this->createAbenteurerFile();
    $user = $this->actingMemberWithPoints(33);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('Bewertungen der Die Abenteurer-Heftromane');
    $response->assertSee('Die Abenteurer-Heftromane je Autor:in');
    $response->assertSee($this->expectedBaxxLabel('abenteurer-autoren'));
});

test('top themes require minimum book count', function (): void {
    $this->createDataFile();
    $path = storage_path('app/private/maddrax.json');
    $data = json_decode(file_get_contents($path), true);
    for ($i = 3; $i <= 7; $i++) {
        $data[] = ['nummer' => $i, 'titel' => 'Roman'.$i, 'text' => ['Author'.$i], 'bewertung' => 4.5, 'stimmen' => 10, 'personen' => [], 'schlagworte' => ['PopularTheme']];
    }
    file_put_contents($path, json_encode($data));
    $user = $this->actingMemberWithPoints(42);
    $this->purchaseStatistikReward($user, 'top-themen');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('TOP20 Maddrax-Themen');
    $response->assertSee('PopularTheme');
    $response->assertDontSee('Thema1');
});

test('favorite themes visible when purchased', function (): void {
    $this->createDataFile();
    User::factory()->create(['lieblingsthema' => 'Thema1']);
    User::factory()->create(['lieblingsthema' => 'Thema1']);
    User::factory()->create(['lieblingsthema' => 'Thema2']);
    User::factory()->create(['lieblingsthema' => 'Thema3']);
    $user = $this->actingMemberWithPoints(50);
    $this->purchaseStatistikReward($user, 'lieblingsthemen');
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('TOP10 Lieblingsthemen');
    $response->assertSee('Thema1');
    $response->assertSee('Thema2');
    $response->assertSee('Thema3');
});

test('favorite themes locked when not purchased', function (): void {
    $this->createDataFile();
    $user = $this->actingMemberWithPoints(49);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    $response->assertSee('TOP10 Lieblingsthemen');
    $response->assertSee($this->expectedBaxxLabel('lieblingsthemen'));
});

test('statistic quick navigation lists all sections', function (): void {
    $this->createDataFile();
    $this->createHardcoversFile();
    $this->createMissionMarsFile();
    $user = $this->actingMemberWithPoints(50);
    $this->actingAs($user);
    $response = $this->get('/statistiken');
    $response->assertOk();
    /** @var array<int, array{id: string, label: string}> $sections */
    $sections = $response->viewData('statisticSections');
    expect($sections)->not->toBeEmpty();
    $dom = new \DOMDocument;
    \libxml_use_internal_errors(true);
    $dom->loadHTML($response->getContent());
    \libxml_clear_errors();
    \libxml_use_internal_errors(false);
    $xpath = new \DOMXPath($dom);
    $navNodes = $xpath->query('//nav[@aria-label="Statistikabschnitte"]');
    expect($navNodes->length)->toBe(1);
    $nav = $navNodes->item(0);
    $links = $xpath->query('.//a[@data-statistik-nav-link]', $nav);
    expect($links->length)->toBe(count($sections));
    foreach ($sections as $index => $section) {
        $link = $links->item($index);
        expect($link)->not->toBeNull()
            ->and($link->getAttribute('href'))->toBe('#'.$section['id']);
        $linkText = trim(preg_replace('/\s+/', ' ', $link->textContent));
        expect($linkText)->toContain($section['label']);
        // Rewards cost is shown in the quicknav (cost_baxx from reward)
        // No minPoints-based assertion needed anymore
    }
});

PhpunitMetadata::onFile(__FILE__, Large::class);
