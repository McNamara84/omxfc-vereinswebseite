<?php

namespace Tests\Feature;

use App\Enums\FanfictionStatus;
use App\Enums\Role;
use App\Enums\TodoStatus;
use App\Mail\MitgliedGenehmigtMail;
use App\Models\Fanfiction;
use App\Models\Team;
use App\Models\User;
use App\Services\MembersTeamProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Admin->value]);

        return $user;
    }

    private function createApplicant(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Anwaerter->value]);

        return $user;
    }

    public function test_admin_can_approve_applicant(): void
    {
        Mail::fake();
        $admin = $this->actingAdmin();
        $applicant = $this->createApplicant();

        $this->actingAs($admin)
            ->from('/dashboard')
            ->post(route('anwaerter.approve', $applicant))
            ->assertRedirect('/dashboard');

        $this->assertDatabaseHas('team_user', [
            'user_id' => $applicant->id,
            'role' => Role::Mitglied->value,
        ]);
        $this->assertNotNull($applicant->fresh()->mitglied_seit);
        Mail::assertQueued(MitgliedGenehmigtMail::class);
    }

    public function test_admin_can_reject_applicant(): void
    {
        $admin = $this->actingAdmin();
        $applicant = $this->createApplicant();

        $this->actingAs($admin)
            ->from('/dashboard')
            ->post(route('anwaerter.reject', $applicant))
            ->assertRedirect('/dashboard');

        $this->assertDatabaseMissing('users', ['id' => $applicant->id]);
        $this->assertDatabaseMissing('team_user', ['user_id' => $applicant->id]);
    }

    public function test_approving_applicant_clears_dashboard_cache(): void
    {
        Mail::fake();
        $admin = $this->actingAdmin();
        $applicant = $this->createApplicant();

        $this->actingAs($admin)->get('/dashboard');

        $this->actingAs($admin)
            ->from('/dashboard')
            ->post(route('anwaerter.approve', $applicant))
            ->assertRedirect('/dashboard');

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertViewHas('openTodos');
        $this->assertFalse($response->viewData('anwaerter')->contains($applicant));
    }

    public function test_index_displays_dashboard_statistics(): void
    {
        $team = Team::membersTeam();
        $admin = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($admin, ['role' => Role::Admin->value]);

        $applicant = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($applicant, ['role' => Role::Anwaerter->value]);

        $member1 = User::factory()->create(['current_team_id' => $team->id]);
        $member2 = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member1, ['role' => Role::Mitglied->value]);
        $team->users()->attach($member2, ['role' => Role::Mitglied->value]);

        $category = \App\Models\TodoCategory::first() ?? \App\Models\TodoCategory::create(['name' => 'Test', 'slug' => 'test']);
        $assignedTodo = \App\Models\Todo::create([
            'team_id' => $team->id,
            'created_by' => $admin->id,
            'assigned_to' => $admin->id,
            'title' => 'Akzeptiert',
            'points' => 5,
            'status' => TodoStatus::Assigned->value,
            'category_id' => $category->id,
        ]);
        \App\Models\Todo::create([
            'team_id' => $team->id,
            'created_by' => $admin->id,
            'assigned_to' => $member1->id,
            'title' => 'Für anderes Mitglied',
            'points' => 8,
            'status' => TodoStatus::Assigned->value,
            'category_id' => $category->id,
        ]);
        \App\Models\Todo::create([
            'team_id' => $team->id,
            'created_by' => $admin->id,
            'assigned_to' => $admin->id,
            'title' => 'Abgeschlossen',
            'points' => 3,
            'status' => TodoStatus::Completed->value,
            'category_id' => $category->id,
        ]);

        \App\Models\UserPoint::create(['user_id' => $admin->id, 'team_id' => $team->id, 'todo_id' => $assignedTodo->id, 'points' => 5]);
        \App\Models\UserPoint::create(['user_id' => $member1->id, 'team_id' => $team->id, 'todo_id' => $assignedTodo->id, 'points' => 10]);
        \App\Models\UserPoint::create(['user_id' => $member2->id, 'team_id' => $team->id, 'todo_id' => $assignedTodo->id, 'points' => 7]);

        $book = \App\Models\Book::create(['roman_number' => 1, 'title' => 'B1', 'author' => 'A']);
        \App\Models\Review::create(['team_id' => $team->id, 'user_id' => $admin->id, 'book_id' => $book->id, 'title' => 'T', 'content' => 'X']);
        $review2 = \App\Models\Review::create(['team_id' => $team->id, 'user_id' => $member1->id, 'book_id' => $book->id, 'title' => 'T2', 'content' => 'Y']);
        \App\Models\ReviewComment::create(['review_id' => $review2->id, 'user_id' => $admin->id, 'content' => 'C']);

        $offer = \App\Models\BookOffer::create([
            'user_id' => $admin->id,
            'series' => \App\Enums\BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'B1',
            'condition' => 'gut',
        ]);

        $request = \App\Models\BookRequest::create([
            'user_id' => $member1->id,
            'series' => \App\Enums\BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'B1',
            'condition' => 'gut',
        ]);

        \App\Models\BookSwap::create([
            'offer_id' => $offer->id,
            'request_id' => $request->id,
        ]);

        $this->actingAs($admin);
        Cache::flush();
        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('openTodos', 1);
        $response->assertViewHas('userPoints', 5);
        $response->assertViewHas('pendingVerification', 1);
        $response->assertViewHas('myReviews', 1);
        $response->assertViewHas('myReviewComments', 1);
        $response->assertViewHas('romantauschMatches', 1);
        $response->assertViewHas('romantauschOffers', 1);
        $topUsers = $response->viewData('topUsers');
        $this->assertEquals($member1->id, $topUsers[0]['id']);
        $anwaerter = $response->viewData('anwaerter');
        $this->assertTrue($anwaerter->contains($applicant));
    }

    public function test_dashboard_counts_book_swap_matches_for_request_owner(): void
    {
        $team = Team::membersTeam();
        $requestingUser = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($requestingUser, ['role' => Role::Mitglied->value]);

        $offeringUser = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($offeringUser, ['role' => Role::Mitglied->value]);

        $offer = \App\Models\BookOffer::create([
            'user_id' => $offeringUser->id,
            'series' => \App\Enums\BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 5,
            'book_title' => 'Testroman',
            'condition' => 'gut',
        ]);

        $request = \App\Models\BookRequest::create([
            'user_id' => $requestingUser->id,
            'series' => \App\Enums\BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 5,
            'book_title' => 'Testroman',
            'condition' => 'gut',
        ]);

        \App\Models\BookSwap::create([
            'offer_id' => $offer->id,
            'request_id' => $request->id,
        ]);

        $this->actingAs($requestingUser);
        Cache::flush();

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('romantauschMatches', 1);
    }

    public function test_dashboard_uses_cached_romantausch_offer_count_without_team_context(): void
    {
        Cache::flush();

        $team = Team::membersTeam();
        $admin = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($admin, ['role' => Role::Admin->value]);

        Cache::put("romantausch_offers_{$admin->id}", 7, now()->addMinutes(10));

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertViewHas('romantauschOffers', 7);
    }

    public function test_dashboard_counts_only_open_offers_of_current_user(): void
    {
        $team = Team::membersTeam();
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => Role::Mitglied->value]);

        $otherMember = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($otherMember, ['role' => Role::Mitglied->value]);

        \App\Models\BookOffer::create([
            'user_id' => $member->id,
            'series' => \App\Enums\BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 10,
            'book_title' => 'Offenes Angebot',
            'condition' => 'gut',
            'completed' => false,
        ]);

        \App\Models\BookOffer::create([
            'user_id' => $member->id,
            'series' => \App\Enums\BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 11,
            'book_title' => 'Abgeschlossen',
            'condition' => 'gut',
            'completed' => true,
        ]);

        \App\Models\BookOffer::create([
            'user_id' => $otherMember->id,
            'series' => \App\Enums\BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 12,
            'book_title' => 'Fremdes Angebot',
            'condition' => 'gut',
            'completed' => false,
        ]);

        $this->actingAs($member);
        Cache::flush();

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('romantauschOffers', 1);
    }

    public function test_dashboard_counts_only_challenges_assigned_to_current_user(): void
    {
        $team = Team::membersTeam();
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => Role::Mitglied->value]);

        $otherMember = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($otherMember, ['role' => Role::Mitglied->value]);

        $category = \App\Models\TodoCategory::first() ?? \App\Models\TodoCategory::create(['name' => 'Test', 'slug' => 'test']);

        \App\Models\Todo::create([
            'team_id' => $team->id,
            'created_by' => $member->id,
            'assigned_to' => $member->id,
            'title' => 'Meine aktive Challenge',
            'points' => 4,
            'status' => TodoStatus::Assigned->value,
            'category_id' => $category->id,
        ]);

        \App\Models\Todo::create([
            'team_id' => $team->id,
            'created_by' => $member->id,
            'assigned_to' => $member->id,
            'title' => 'Bereits abgeschlossen',
            'points' => 6,
            'status' => TodoStatus::Completed->value,
            'category_id' => $category->id,
        ]);

        \App\Models\Todo::create([
            'team_id' => $team->id,
            'created_by' => $otherMember->id,
            'assigned_to' => $otherMember->id,
            'title' => 'Challenge anderer',
            'points' => 3,
            'status' => TodoStatus::Assigned->value,
            'category_id' => $category->id,
        ]);

        \App\Models\Todo::create([
            'team_id' => $team->id,
            'created_by' => $member->id,
            'title' => 'Noch offen',
            'points' => 2,
            'status' => TodoStatus::Open->value,
            'category_id' => $category->id,
        ]);

        $this->actingAs($member);
        Cache::flush();

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('openTodos', 1);
    }

    public function test_dashboard_caches_open_todos_grouped_by_team(): void
    {
        Cache::flush();

        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        $category = \App\Models\TodoCategory::first()
            ?? \App\Models\TodoCategory::create(['name' => 'Cache Test', 'slug' => 'cache-test']);

        \App\Models\Todo::create([
            'team_id' => $team->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'title' => 'Cache coverage',
            'points' => 1,
            'status' => TodoStatus::Assigned->value,
            'category_id' => $category->id,
        ]);

        $otherTeam = Team::factory()->create();
        $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);

        \App\Models\Todo::create([
            'team_id' => $otherTeam->id,
            'created_by' => $user->id,
            'assigned_to' => $user->id,
            'title' => 'Other team cache coverage',
            'points' => 1,
            'status' => TodoStatus::Assigned->value,
            'category_id' => $category->id,
        ]);

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $this->assertTrue(Cache::has("open_todos_{$user->id}"));
        $cachedCounts = Cache::get("open_todos_{$user->id}");
        $this->assertIsArray($cachedCounts);
        $this->assertArrayHasKey($team->id, $cachedCounts);
        $this->assertArrayHasKey($otherTeam->id, $cachedCounts);
        $this->assertSame(1, $cachedCounts[$team->id]);
        $this->assertSame(1, $cachedCounts[$otherTeam->id]);
        $this->assertFalse(Cache::has("open_todos_{$team->id}_{$user->id}"));
    }

    public function test_redirect_when_membership_missing(): void
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertRedirect('/');
        $response->assertSessionHas('error');
    }

    public function test_index_uses_members_team_provider(): void
    {
        $team = Team::membersTeam();
        $user = $this->actingAdmin();
        $this->actingAs($user);

        $this->mock(MembersTeamProvider::class, function ($mock) use ($team) {
            $mock->shouldReceive('getMembersTeamOrAbort')->once()->andReturn($team);
        });

        $this->get('/dashboard')->assertOk();
    }

    public function test_approve_uses_members_team_provider(): void
    {
        Mail::fake();
        $admin = $this->actingAdmin();
        $applicant = $this->createApplicant();
        $team = Team::membersTeam();
        $this->actingAs($admin);

        $this->mock(MembersTeamProvider::class, function ($mock) use ($team) {
            $mock->shouldReceive('getMembersTeamOrAbort')->once()->andReturn($team);
        });

        $this->from('/dashboard')
            ->post(route('anwaerter.approve', $applicant))
            ->assertRedirect('/dashboard');
    }

    public function test_reject_uses_members_team_provider(): void
    {
        $admin = $this->actingAdmin();
        $applicant = $this->createApplicant();
        $team = Team::membersTeam();
        $this->actingAs($admin);

        $this->mock(MembersTeamProvider::class, function ($mock) use ($team) {
            $mock->shouldReceive('getMembersTeamOrAbort')->once()->andReturn($team);
        });

        $this->from('/dashboard')
            ->post(route('anwaerter.reject', $applicant))
            ->assertRedirect('/dashboard');
    }

    public function test_dashboard_shows_published_fanfiction_count(): void
    {
        $team = Team::membersTeam();
        $member = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($member, ['role' => Role::Mitglied->value]);

        // Erstelle 2 veröffentlichte Fanfictions
        Fanfiction::factory()->count(2)->create([
            'team_id' => $team->id,
            'user_id' => $member->id,
            'created_by' => $member->id,
            'status' => FanfictionStatus::Published,
            'published_at' => now(),
        ]);

        // Erstelle 1 Entwurf (sollte nicht gezählt werden)
        Fanfiction::factory()->create([
            'team_id' => $team->id,
            'user_id' => $member->id,
            'created_by' => $member->id,
            'status' => FanfictionStatus::Draft,
        ]);

        $this->actingAs($member);
        Cache::flush();
        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('fanfictionCount', 2);
    }
}
