<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Models\Todo;
use App\Models\TodoCategory;
use App\Models\UserPoint;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\Book;
use App\Models\Review;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Services\MaddraxDataService;
use Illuminate\Support\Facades\File;

class ProfileViewControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(string $role = 'Mitglied'): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);
        return $user;
    }

    private function createTodoWithPoints(User $user, TodoCategory $category, int $points): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $todo = Todo::create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'title' => 'Task',
            'points' => $points,
            'category_id' => $category->id,
        ]);

        UserPoint::create([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'todo_id' => $todo->id,
            'points' => $points,
        ]);
    }

    private function createCompletedSwaps(User $member, User $partner, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $offer = BookOffer::create([
                'user_id' => $member->id,
                'series' => 'Series',
                'book_number' => $i + 1,
                'book_title' => 'Title',
                'condition' => 'neu',
            ]);

            $request = BookRequest::create([
                'user_id' => $partner->id,
                'series' => 'Series',
                'book_number' => $i + 1,
                'book_title' => 'Title',
                'condition' => 'neu',
            ]);

            BookSwap::create([
                'offer_id' => $offer->id,
                'request_id' => $request->id,
                'completed_at' => now(),
            ]);
        }
    }

    public function test_view_own_profile_shows_details(): void
    {
        $user = $this->createMember();
        $this->actingAs($user);

        $response = $this->get("/profil/{$user->id}");

        $response->assertOk();
        $response->assertViewHas('isOwnProfile', true);
        $response->assertViewHas('memberRole', 'Mitglied');
        $response->assertViewHas('canViewDetails', true);
    }

    public function test_view_other_member_without_privilege_hides_contact(): void
    {
        $viewer = $this->createMember();
        $target = $this->createMember();
        $this->actingAs($viewer);

        $response = $this->get("/profil/{$target->id}");

        $response->assertOk();
        $response->assertViewHas('isOwnProfile', false);
        $response->assertViewHas('memberRole', 'Mitglied');
        $response->assertViewHas('canViewDetails', false);
    }

    public function test_view_other_member_with_admin_role_shows_contact(): void
    {
        $admin = $this->createMember('Admin');
        $target = $this->createMember();
        $this->actingAs($admin);

        $response = $this->get("/profil/{$target->id}");

        $response->assertOk();
        $response->assertViewHas('canViewDetails', true);
    }

    public function test_redirect_when_user_not_in_team(): void
    {
        $viewer = $this->createMember();
        $otherTeam = Team::factory()->create(['personal_team' => false, 'name' => 'Other']);
        $target = User::factory()->create(['current_team_id' => $otherTeam->id]);
        $otherTeam->users()->attach($target, ['role' => 'Mitglied']);
        $this->actingAs($viewer);

        $response = $this->get("/profil/{$target->id}");

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('error');
    }

    public function test_redirect_when_current_user_membership_missing(): void
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $viewer = User::factory()->create(['current_team_id' => $team->id]);
        $target = $this->createMember();
        $this->actingAs($viewer);

        $response = $this->get("/profil/{$target->id}");

        $response->assertRedirect('/dashboard');
        $response->assertSessionHas('error');
    }

    public function test_points_and_badges_are_calculated(): void
    {
        $admin = $this->createMember('Admin');
        $member = $this->createMember();

        $general = TodoCategory::firstOrCreate(
            ['slug' => Str::slug('General')],
            ['name' => 'General']
        );
        $maddraxikon = TodoCategory::firstOrCreate(
            ['slug' => Str::slug('AG Maddraxikon')],
            ['name' => 'AG Maddraxikon']
        );

        $this->createTodoWithPoints($member, $general, 5);
        $this->createTodoWithPoints($member, $maddraxikon, 3);

        $book = Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
        ]);
        for ($i = 0; $i < 10; $i++) {
            Review::create([
                'team_id' => $member->currentTeam->id,
                'user_id' => $member->id,
                'book_id' => $book->id,
                'title' => 'R'.$i,
                'content' => str_repeat('A', 140),
            ]);
        }
        $this->actingAs($admin);

        $response = $this->get("/profil/{$member->id}");

        $response->assertOk();
        $response->assertViewHas('userPoints', 8);
        $response->assertViewHas('completedTasks', 2);
        $response->assertViewHas('categoryPoints', [
            'General' => 5,
            'AG Maddraxikon' => 3,
        ]);
        $badges = $response->viewData('badges');
        $this->assertCount(3, $badges);
        $this->assertEquals('Ersthelfer', $badges[0]['name']);
        $this->assertEquals('Retrologe (Stufe 1)', $badges[1]['name']);
        $this->assertEquals('Rezensator (Stufe 1)', $badges[2]['name']);
    }

    public function test_member_team_missing_results_in_zero_points(): void
    {
        $admin = $this->createMember('Admin');
        $member = $this->createMember();
        $admin->currentTeam->update(['name' => 'Something']);

        $this->actingAs($admin);

        $response = $this->get("/profil/{$member->id}");

        $response->assertOk();
        $response->assertViewHas('userPoints', 0);
        $response->assertViewHas('categoryPoints', []);
        $response->assertViewHas('badges', []);
    }

    public function test_haendler_badge_for_one_swap(): void
    {
        $admin = $this->createMember('Admin');
        $member = $this->createMember();
        $partner = $this->createMember();

        $this->createCompletedSwaps($member, $partner, 1);

        $this->actingAs($admin);
        $response = $this->get("/profil/{$member->id}");

        $response->assertOk();
        $badges = $response->viewData('badges');
        $this->assertCount(1, $badges);
        $this->assertEquals('Händler (Stufe 1)', $badges[0]['name']);
    }

    public function test_haendler_badge_for_ten_swaps(): void
    {
        $admin = $this->createMember('Admin');
        $member = $this->createMember();
        $partner = $this->createMember();

        $this->createCompletedSwaps($member, $partner, 10);

        $this->actingAs($admin);
        $response = $this->get("/profil/{$member->id}");

        $response->assertOk();
        $badges = $response->viewData('badges');
        $this->assertCount(1, $badges);
        $this->assertEquals('Händler (Stufe 2)', $badges[0]['name']);
    }

    public function test_haendler_badge_for_hundred_swaps(): void
    {
        $admin = $this->createMember('Admin');
        $member = $this->createMember();
        $partner = $this->createMember();

        $this->createCompletedSwaps($member, $partner, 100);

        $this->actingAs($admin);
        $response = $this->get("/profil/{$member->id}");

        $response->assertOk();
        $badges = $response->viewData('badges');
        $this->assertCount(1, $badges);
        $this->assertEquals('Händler (Stufe 3)', $badges[0]['name']);
    }

    public function test_haendler_badge_for_thousand_swaps(): void
    {
        $admin = $this->createMember('Admin');
        $member = $this->createMember();
        $partner = $this->createMember();

        $this->createCompletedSwaps($member, $partner, 1000);

        $this->actingAs($admin);
        $response = $this->get("/profil/{$member->id}");

        $response->assertOk();
        $badges = $response->viewData('badges');
        $this->assertCount(1, $badges);
        $this->assertEquals('Händler (Stufe 4)', $badges[0]['name']);
    }

    public function test_weltrat_kritiker_badge_for_full_cycle(): void
    {
        $admin = $this->createMember('Admin');
        $member = $this->createMember();

        $testStoragePath = base_path('storage/testing-weltrat');
        $originalStoragePath = $this->app->storagePath();
        $this->app->useStoragePath($testStoragePath);
        File::ensureDirectoryExists($testStoragePath . '/app/private');
        $originalLocalRoot = config('filesystems.disks.local.root');
        config(['filesystems.disks.local.root' => $testStoragePath . '/app/private']);

        $ref = new \ReflectionClass(MaddraxDataService::class);
        $property = $ref->getProperty('data');
        $property->setAccessible(true);
        $property->setValue(null, null);

        $data = [
            ['nummer' => 1, 'zyklus' => 'Weltrat-Zyklus', 'titel' => 'Roman1', 'text' => []],
            ['nummer' => 2, 'zyklus' => 'Weltrat-Zyklus', 'titel' => 'Roman2', 'text' => []],
        ];
        File::put($testStoragePath . '/app/private/maddrax.json', json_encode($data));

        $book1 = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
        $book2 = Book::create(['roman_number' => 2, 'title' => 'Roman2', 'author' => 'Author']);

        Review::create([
            'team_id' => $member->currentTeam->id,
            'user_id' => $member->id,
            'book_id' => $book1->id,
            'title' => 'R1',
            'content' => str_repeat('A', 140),
        ]);

        Review::create([
            'team_id' => $member->currentTeam->id,
            'user_id' => $member->id,
            'book_id' => $book2->id,
            'title' => 'R2',
            'content' => str_repeat('A', 140),
        ]);

        $this->actingAs($admin);
        $response = $this->get("/profil/{$member->id}");

        $response->assertOk();
        $badges = $response->viewData('badges');
        $this->assertCount(1, $badges);
        $this->assertEquals('Weltrat-Kritiker', $badges[0]['name']);

        File::deleteDirectory($testStoragePath);
        $this->app->useStoragePath($originalStoragePath);
        config(['filesystems.disks.local.root' => $originalLocalRoot]);
    }

    public function test_amraka_kritiker_badge_for_full_cycle(): void
    {
        $admin = $this->createMember('Admin');
        $member = $this->createMember();

        $testStoragePath = base_path('storage/testing-amraka');
        $originalStoragePath = $this->app->storagePath();
        $this->app->useStoragePath($testStoragePath);
        File::ensureDirectoryExists($testStoragePath . '/app/private');
        $originalLocalRoot = config('filesystems.disks.local.root');
        config(['filesystems.disks.local.root' => $testStoragePath . '/app/private']);

        $ref = new \ReflectionClass(MaddraxDataService::class);
        $property = $ref->getProperty('data');
        $property->setAccessible(true);
        $property->setValue(null, null);

        $data = [
            ['nummer' => 1, 'zyklus' => 'Amraka-Zyklus', 'titel' => 'Roman1', 'text' => []],
            ['nummer' => 2, 'zyklus' => 'Amraka-Zyklus', 'titel' => 'Roman2', 'text' => []],
        ];
        File::put($testStoragePath . '/app/private/maddrax.json', json_encode($data));

        $book1 = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
        $book2 = Book::create(['roman_number' => 2, 'title' => 'Roman2', 'author' => 'Author']);

        Review::create([
            'team_id' => $member->currentTeam->id,
            'user_id' => $member->id,
            'book_id' => $book1->id,
            'title' => 'R1',
            'content' => str_repeat('A', 140),
        ]);

        Review::create([
            'team_id' => $member->currentTeam->id,
            'user_id' => $member->id,
            'book_id' => $book2->id,
            'title' => 'R2',
            'content' => str_repeat('A', 140),
        ]);

        $this->actingAs($admin);
        $response = $this->get("/profil/{$member->id}");

        $response->assertOk();
        $badges = $response->viewData('badges');
        $this->assertCount(1, $badges);
        $this->assertEquals('Amraka-Kritiker', $badges[0]['name']);

        File::deleteDirectory($testStoragePath);
        $this->app->useStoragePath($originalStoragePath);
        config(['filesystems.disks.local.root' => $originalLocalRoot]);
    }

    public function test_online_status_is_true_for_recent_activity(): void
    {
        $viewer = $this->createMember();
        $target = $this->createMember();
        $this->actingAs($viewer);

        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $response = $this->get("/profil/{$target->id}");

        $response->assertOk();
        $response->assertViewHas('isOnline', true);
        $this->assertNotNull($response->viewData('lastSeen'));
    }

    public function test_online_status_is_false_for_old_activity(): void
    {
        $viewer = $this->createMember();
        $target = $this->createMember();
        $this->actingAs($viewer);

        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => now()->subMinutes(10)->timestamp,
        ]);

        $response = $this->get("/profil/{$target->id}");

        $response->assertOk();
        $response->assertViewHas('isOnline', false);
        $lastSeen = $response->viewData('lastSeen');
        $this->assertTrue($lastSeen->lt(now()->subMinutes(5)));
    }
}
