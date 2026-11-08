<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Enums\FanfictionStatus;
use App\Enums\Role;
use App\Enums\TodoStatus;
use App\Enums\TourAssignmentSource;
use App\Enums\TourAssignmentStatus;
use App\Mail\MitgliedGenehmigtMail;
use App\Models\Book;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\Fanfiction;
use App\Models\Review;
use App\Models\ReviewBaxxSpecialOffer;
use App\Models\ReviewComment;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\Team;
use App\Models\Todo;
use App\Models\TodoCategory;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\MembersTeamProvider;
use App\Services\RewardService;
use App\Services\TourAssignmentService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(DashboardControllerTestFixtures::class);

test('admin can approve applicant', function (): void {
    $hauptmenueVersion = (int) config('tours.hauptmenue.version');
    $profilpflegeVersion = (int) config('tours.profilpflege.version');
    Mail::fake();
    $admin = $this->actingAdmin();
    $applicant = $this->createApplicant();
    $this->actingAs($admin)->from('/dashboard')->post(route('anwaerter.approve', $applicant))->assertRedirect('/dashboard');
    $this->assertDatabaseHas('team_user', ['user_id' => $applicant->id, 'role' => Role::Mitglied->value]);
    $this->assertDatabaseHas('tour_assignments', ['user_id' => $applicant->id, 'tour_key' => 'hauptmenue', 'tour_version' => $hauptmenueVersion, 'status' => TourAssignmentStatus::Pending->value, 'assigned_via' => TourAssignmentSource::System->value]);
    $this->assertDatabaseHas('tour_assignments', ['user_id' => $applicant->id, 'tour_key' => 'profilpflege', 'tour_version' => $profilpflegeVersion, 'status' => TourAssignmentStatus::Pending->value, 'assigned_via' => TourAssignmentSource::System->value]);
    expect($applicant->fresh()->mitglied_seit)->not->toBeNull();
    Mail::assertQueued(MitgliedGenehmigtMail::class);
});

test('approved member auto tours are only assigned once', function (): void {
    $hauptmenueVersion = (int) config('tours.hauptmenue.version');
    $profilpflegeVersion = (int) config('tours.profilpflege.version');
    Mail::fake();
    $admin = $this->actingAdmin();
    $applicant = $this->createApplicant();
    $this->actingAs($admin)->post(route('anwaerter.approve', $applicant))->assertRedirect();
    app(TourAssignmentService::class)->assignAutoToursForApprovedMember($applicant->fresh(), $admin);
    $this->assertDatabaseCount('tour_assignments', 2);
    $this->assertDatabaseHas('tour_assignments', ['user_id' => $applicant->id, 'tour_key' => 'hauptmenue', 'tour_version' => $hauptmenueVersion]);
    $this->assertDatabaseHas('tour_assignments', ['user_id' => $applicant->id, 'tour_key' => 'profilpflege', 'tour_version' => $profilpflegeVersion]);
});

test('admin can reject applicant', function (): void {
    $admin = $this->actingAdmin();
    $applicant = $this->createApplicant();
    $this->actingAs($admin)->from('/dashboard')->post(route('anwaerter.reject', $applicant))->assertRedirect('/dashboard');
    $this->assertDatabaseMissing('users', ['id' => $applicant->id]);
    $this->assertDatabaseMissing('team_user', ['user_id' => $applicant->id]);
});

test('approving applicant clears dashboard cache', function (): void {
    Mail::fake();
    $admin = $this->actingAdmin();
    $applicant = $this->createApplicant();
    $this->actingAs($admin)->get('/dashboard');
    $this->actingAs($admin)->from('/dashboard')->post(route('anwaerter.approve', $applicant))->assertRedirect('/dashboard');
    $response = $this->actingAs($admin)->get('/dashboard');
    $response->assertViewHas('openTodos');
    expect($response->viewData('anwaerter')->contains($applicant))->toBeFalse();
});

test('dashboard ignores legacy cached applicant collections', function (): void {
    $admin = $this->actingAdmin();
    $applicant = $this->createApplicant();
    $team = Team::membersTeam();
    Cache::put("anwaerter_{$team->id}", collect([$applicant]), now()->addMinutes(10));
    $response = $this->actingAs($admin)->get('/dashboard');
    $response->assertOk();
    $anwaerter = $response->viewData('anwaerter');
    expect($anwaerter)->toHaveCount(1)
        ->and($anwaerter->contains(fn (User $user): bool => $user->is($applicant)))->toBeTrue();
});

test('index displays dashboard statistics', function (): void {
    $team = Team::membersTeam();
    $admin = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($admin, ['role' => Role::Admin->value]);
    $applicant = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($applicant, ['role' => Role::Anwaerter->value]);
    $member1 = User::factory()->create(['current_team_id' => $team->id]);
    $member2 = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member1, ['role' => Role::Mitglied->value]);
    $team->users()->attach($member2, ['role' => Role::Mitglied->value]);
    $category = TodoCategory::first() ?? TodoCategory::create(['name' => 'Test', 'slug' => 'test']);
    $assignedTodo = Todo::create(['team_id' => $team->id, 'created_by' => $admin->id, 'assigned_to' => $admin->id, 'title' => 'Akzeptiert', 'points' => 5, 'status' => TodoStatus::Assigned->value, 'category_id' => $category->id]);
    Todo::create(['team_id' => $team->id, 'created_by' => $admin->id, 'assigned_to' => $member1->id, 'title' => 'Für anderes Mitglied', 'points' => 8, 'status' => TodoStatus::Assigned->value, 'category_id' => $category->id]);
    Todo::create(['team_id' => $team->id, 'created_by' => $admin->id, 'assigned_to' => $admin->id, 'title' => 'Abgeschlossen', 'points' => 3, 'status' => TodoStatus::Completed->value, 'category_id' => $category->id]);
    // todo_id ist seit der Unique-Constraint auf user_points.todo_id
    // pro Todo nur einmal vergebbar; mehrere unabhängige Gutschriften
    // werden im Code via incrementTeamPoints() ohne Todo-Bezug erstellt.
    UserPoint::create(['user_id' => $admin->id, 'team_id' => $team->id, 'todo_id' => null, 'points' => 5]);
    UserPoint::create(['user_id' => $member1->id, 'team_id' => $team->id, 'todo_id' => null, 'points' => 10]);
    UserPoint::create(['user_id' => $member2->id, 'team_id' => $team->id, 'todo_id' => null, 'points' => 7]);
    $book = Book::create(['roman_number' => 1, 'title' => 'B1', 'author' => 'A']);
    Review::create(['team_id' => $team->id, 'user_id' => $admin->id, 'book_id' => $book->id, 'title' => 'T', 'content' => 'X']);
    $review2 = Review::create(['team_id' => $team->id, 'user_id' => $member1->id, 'book_id' => $book->id, 'title' => 'T2', 'content' => 'Y']);
    ReviewComment::create(['review_id' => $review2->id, 'user_id' => $admin->id, 'content' => 'C']);
    $offer = BookOffer::create(['user_id' => $admin->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'B1', 'condition' => 'gut']);
    $request = BookRequest::create(['user_id' => $member1->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'B1', 'condition' => 'gut']);
    BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
    $this->actingAs($admin);
    Cache::flush();
    $response = $this->get('/dashboard');
    $response->assertOk();
    $response->assertViewHas('openTodos', 1);
    $response->assertViewHas('availableBaxx', 5);
    $response->assertViewHas('pendingVerification', 1);
    $response->assertViewHas('myReviews', 1);
    $response->assertViewHas('myReviewComments', 1);
    $response->assertViewHas('romantauschMatches', 1);
    $response->assertViewHas('romantauschOffers', 1);
    $topUsers = $response->viewData('topUsers');
    expect($topUsers[0]['id'])->toEqual($member1->id);
    $anwaerter = $response->viewData('anwaerter');
    expect($anwaerter->contains($applicant))->toBeTrue();
});

test('dashboard shows available baxx after reward purchase even after previous page load', function (): void {
    $team = Team::membersTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    UserPoint::create(['user_id' => $member->id, 'team_id' => $team->id, 'todo_id' => null, 'points' => 12]);
    $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'dashboard-baxx-test']);
    $this->actingAs($member);
    $this->get('/dashboard')->assertOk();
    app(RewardService::class)->purchaseReward($member, $reward);
    $this->get('/dashboard')->assertOk()->assertViewHas('availableBaxx', 7)->assertSeeText('7 Baxx verfügbar')->assertSeeText('Verfügbare Baxx');
});

test('dashboard shows restored baxx after reward refund even after previous page load', function (): void {
    $team = Team::membersTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    UserPoint::create(['user_id' => $member->id, 'team_id' => $team->id, 'todo_id' => null, 'points' => 12]);
    $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'dashboard-baxx-refund-test']);
    $purchase = app(RewardService::class)->purchaseReward($member, $reward);
    $admin = $this->actingAdmin();
    $this->actingAs($member)->get('/dashboard')->assertOk()->assertViewHas('availableBaxx', 7);
    app(RewardService::class)->refundPurchase($purchase, $admin);
    $this->actingAs($member)->get('/dashboard')->assertOk()->assertViewHas('availableBaxx', 12)->assertSeeText('12 Baxx verfügbar')->assertSeeText('Verfügbare Baxx');
});

test('dashboard shows wallet warning for ambiguous legacy purchase without 500', function (): void {
    $team = Team::membersTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    UserPoint::create(['user_id' => $member->id, 'team_id' => $team->id, 'todo_id' => null, 'points' => 12]);
    $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'dashboard-legacy-warning-test']);
    RewardPurchase::factory()->create(['user_id' => $member->id, 'reward_id' => $reward->id, 'wallet_team_id' => null, 'cost_baxx' => 5]);
    $this->actingAs($member)->get('/dashboard')->assertOk()->assertViewHas('walletWarning')->assertSeeText('Baxx-Guthaben wird geprüft')->assertSeeText('Prüfung nötig');
});

test('dashboard uses members team baxx when current team differs', function (): void {
    $team = Team::membersTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    UserPoint::create(['user_id' => $member->id, 'team_id' => $team->id, 'todo_id' => null, 'points' => 12]);
    $reward = Reward::factory()->create(['cost_baxx' => 5, 'slug' => 'dashboard-members-team-balance']);
    app(RewardService::class)->purchaseReward($member, $reward);
    $otherTeam = Team::factory()->create(['personal_team' => false, 'name' => 'Nebenverein']);
    $otherTeam->users()->attach($member, ['role' => Role::Mitglied->value]);
    $member->forceFill(['current_team_id' => $otherTeam->id])->save();
    $this->actingAs($member->fresh())->get('/dashboard')->assertOk()->assertViewHas('availableBaxx', 7)->assertSeeText('7 Baxx verfügbar');
});

test('dashboard counts book swap matches for request owner', function (): void {
    $team = Team::membersTeam();
    $requestingUser = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($requestingUser, ['role' => Role::Mitglied->value]);
    $offeringUser = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($offeringUser, ['role' => Role::Mitglied->value]);
    $offer = BookOffer::create(['user_id' => $offeringUser->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 5, 'book_title' => 'Testroman', 'condition' => 'gut']);
    $request = BookRequest::create(['user_id' => $requestingUser->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 5, 'book_title' => 'Testroman', 'condition' => 'gut']);
    BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
    $this->actingAs($requestingUser);
    Cache::flush();
    $response = $this->get('/dashboard');
    $response->assertOk();
    $response->assertViewHas('romantauschMatches', 1);
});

test('dashboard uses cached romantausch offer count without team context', function (): void {
    Cache::flush();
    $team = Team::membersTeam();
    $admin = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($admin, ['role' => Role::Admin->value]);
    Cache::put("romantausch_offers_{$admin->id}", 7, now()->addMinutes(10));
    $this->actingAs($admin)->get('/dashboard')->assertViewHas('romantauschOffers', 7);
});

test('dashboard shows prominent review special offer', function (): void {
    $team = Team::membersTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    ReviewBaxxSpecialOffer::create(['points' => 2, 'every_count' => 1, 'ends_at' => now()->addDay(), 'is_active' => true]);
    $this->actingAs($member)->get('/dashboard')->assertOk()->assertSee('Sonderaktion')->assertSee('2 Baxx pro Rezension');
});

test('dashboard counts only open offers of current user', function (): void {
    $team = Team::membersTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    $otherMember = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($otherMember, ['role' => Role::Mitglied->value]);
    BookOffer::create(['user_id' => $member->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 10, 'book_title' => 'Offenes Angebot', 'condition' => 'gut', 'completed' => false]);
    BookOffer::create(['user_id' => $member->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 11, 'book_title' => 'Abgeschlossen', 'condition' => 'gut', 'completed' => true]);
    BookOffer::create(['user_id' => $otherMember->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 12, 'book_title' => 'Fremdes Angebot', 'condition' => 'gut', 'completed' => false]);
    $this->actingAs($member);
    Cache::flush();
    $response = $this->get('/dashboard');
    $response->assertOk();
    $response->assertViewHas('romantauschOffers', 1);
});

test('dashboard counts only challenges assigned to current user', function (): void {
    $team = Team::membersTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    $otherMember = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($otherMember, ['role' => Role::Mitglied->value]);
    $category = TodoCategory::first() ?? TodoCategory::create(['name' => 'Test', 'slug' => 'test']);
    Todo::create(['team_id' => $team->id, 'created_by' => $member->id, 'assigned_to' => $member->id, 'title' => 'Meine aktive Challenge', 'points' => 4, 'status' => TodoStatus::Assigned->value, 'category_id' => $category->id]);
    Todo::create(['team_id' => $team->id, 'created_by' => $member->id, 'assigned_to' => $member->id, 'title' => 'Bereits abgeschlossen', 'points' => 6, 'status' => TodoStatus::Completed->value, 'category_id' => $category->id]);
    Todo::create(['team_id' => $team->id, 'created_by' => $otherMember->id, 'assigned_to' => $otherMember->id, 'title' => 'Challenge anderer', 'points' => 3, 'status' => TodoStatus::Assigned->value, 'category_id' => $category->id]);
    Todo::create(['team_id' => $team->id, 'created_by' => $member->id, 'title' => 'Noch offen', 'points' => 2, 'status' => TodoStatus::Open->value, 'category_id' => $category->id]);
    $this->actingAs($member);
    Cache::flush();
    $response = $this->get('/dashboard');
    $response->assertOk();
    $response->assertViewHas('openTodos', 1);
});

test('dashboard caches open todos grouped by team', function (): void {
    Cache::flush();
    $team = Team::membersTeam();
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($user, ['role' => Role::Mitglied->value]);
    $category = TodoCategory::first() ?? TodoCategory::create(['name' => 'Cache Test', 'slug' => 'cache-test']);
    Todo::create(['team_id' => $team->id, 'created_by' => $user->id, 'assigned_to' => $user->id, 'title' => 'Cache coverage', 'points' => 1, 'status' => TodoStatus::Assigned->value, 'category_id' => $category->id]);
    $otherTeam = Team::factory()->create();
    $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
    Todo::create(['team_id' => $otherTeam->id, 'created_by' => $user->id, 'assigned_to' => $user->id, 'title' => 'Other team cache coverage', 'points' => 1, 'status' => TodoStatus::Assigned->value, 'category_id' => $category->id]);
    $this->actingAs($user)->get('/dashboard')->assertOk();
    expect(Cache::has("open_todos_{$user->id}"))->toBeTrue();
    $cachedCounts = Cache::get("open_todos_{$user->id}");
    expect($cachedCounts)->toBeArray()
        ->toHaveKey($team->id)
        ->toHaveKey($otherTeam->id)
        ->and($cachedCounts[$team->id])->toBe(1)
        ->and($cachedCounts[$otherTeam->id])->toBe(1)
        ->and(Cache::has("open_todos_{$team->id}_{$user->id}"))->toBeFalse();
});

test('dashboard is forbidden when membership is missing', function (): void {
    $team = Team::membersTeam();
    $user = User::factory()->create(['current_team_id' => $team->id]);
    $this->actingAs($user);
    $this->get('/dashboard')->assertForbidden();
});

test('index uses members team provider', function (): void {
    $team = Team::membersTeam();
    $user = $this->actingAdmin();
    $this->actingAs($user);
    $this->mock(MembersTeamProvider::class, function ($mock) use ($team) {
        $mock->shouldReceive('getMembersTeamOrAbort')->once()->andReturn($team);
    });
    $this->get('/dashboard')->assertOk();
});

test('approve uses members team provider', function (): void {
    Mail::fake();
    $admin = $this->actingAdmin();
    $applicant = $this->createApplicant();
    $team = Team::membersTeam();
    $this->actingAs($admin);
    $this->mock(MembersTeamProvider::class, function ($mock) use ($team) {
        $mock->shouldReceive('getMembersTeamOrAbort')->once()->andReturn($team);
    });
    $this->from('/dashboard')->post(route('anwaerter.approve', $applicant))->assertRedirect('/dashboard');
});

test('reject uses members team provider', function (): void {
    $admin = $this->actingAdmin();
    $applicant = $this->createApplicant();
    $team = Team::membersTeam();
    $this->actingAs($admin);
    $this->mock(MembersTeamProvider::class, function ($mock) use ($team) {
        $mock->shouldReceive('getMembersTeamOrAbort')->once()->andReturn($team);
    });
    $this->from('/dashboard')->post(route('anwaerter.reject', $applicant))->assertRedirect('/dashboard');
});

test('dashboard shows published fanfiction count', function (): void {
    $team = Team::membersTeam();
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    // Erstelle 2 veröffentlichte Fanfictions
    Fanfiction::factory()->count(2)->create(['team_id' => $team->id, 'user_id' => $member->id, 'created_by' => $member->id, 'status' => FanfictionStatus::Published, 'published_at' => now()]);
    // Erstelle 1 Entwurf (sollte nicht gezählt werden)
    Fanfiction::factory()->create(['team_id' => $team->id, 'user_id' => $member->id, 'created_by' => $member->id, 'status' => FanfictionStatus::Draft]);
    $this->actingAs($member);
    Cache::flush();
    $response = $this->get('/dashboard');
    $response->assertOk();
    $response->assertViewHas('fanfictionCount', 2);
});
