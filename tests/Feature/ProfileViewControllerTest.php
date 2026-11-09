<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\MaddraxikonRewardEventStatus;
use App\Enums\Role;
use App\Models\Book;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonRewardEvent;
use App\Models\Review;
use App\Models\Team;
use App\Models\TodoCategory;
use App\Models\User;
use App\Services\MaddraxDataService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Large;
use Tests\Support\PhpunitMetadata;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(ProfileViewControllerTestFixtures::class);

test('view own profile shows details', function (): void {
    $user = $this->createMember();
    $this->actingAs($user);
    $response = $this->get("/profil/{$user->id}");
    $response->assertOk();
    $response->assertViewHas('isOwnProfile', true);
    $response->assertViewHas('memberRole', Role::Mitglied);
    $response->assertViewHas('canViewDetails', true);
});

test('view other member without privilege hides contact', function (): void {
    $viewer = $this->createMember();
    $target = $this->createMember();
    $this->actingAs($viewer);
    $response = $this->get("/profil/{$target->id}");
    $response->assertOk();
    $response->assertViewHas('isOwnProfile', false);
    $response->assertViewHas('memberRole', Role::Mitglied);
    $response->assertViewHas('canViewDetails', false);
    $response->assertDontSee($target->email);
    $response->assertDontSee($target->telefon);
});

test('view other member shows released contact methods and aliases', function (): void {
    $viewer = $this->createMember();
    $target = $this->createMember('Ehrenmitglied', ['name' => 'Stefan Kontakt', 'vorname' => 'Stefan', 'nachname' => 'Kontakt', 'email' => 'stefan@example.com', 'telefon' => '0123 45 67', 'alias' => 'Stefan K', 'author_aliases' => ['Ian Rolf Hill', 'Jo Zybell'], 'contact_release_email' => true, 'contact_release_phone' => true, 'contact_release_maddraxikon' => true, 'contact_release_nextcloud' => true, 'nextcloud_username' => 'Holger']);
    MaddraxikonAccountLink::factory()->for($target)->create(['wiki_username' => 'Stefan K']);
    $this->actingAs($viewer);
    $response = $this->get("/profil/{$target->id}");
    $response->assertOk();
    $response->assertViewHas('canViewDetails', false);
    $response->assertSee('Alias');
    $response->assertSee('Stefan K');
    $response->assertSee('Ian Rolf Hill');
    $response->assertSee('Jo Zybell');
    $response->assertSee('Freigegebene Kontaktwege');
    $response->assertSee('mailto:stefan@example.com', false);
    $response->assertSee('tel:01234567', false);
    $response->assertSee('https://de.maddraxikon.com/index.php?title=Benutzer:Stefan_K', false);
    $response->assertSee('https://cloud.maddrax-fanclub.de/u/Holger', false);
});

test('view other member with admin role shows contact', function (): void {
    $admin = $this->createMember('Admin');
    $target = $this->createMember();
    $this->actingAs($admin);
    $response = $this->get("/profil/{$target->id}");
    $response->assertOk();
    $response->assertViewHas('canViewDetails', true);
});

test('redirect when user not in team', function (): void {
    $viewer = $this->createMember();
    $otherTeam = Team::factory()->create(['personal_team' => false, 'name' => 'Other']);
    $target = User::factory()->create(['current_team_id' => $otherTeam->id]);
    $otherTeam->users()->attach($target, ['role' => Role::Mitglied->value]);
    $this->actingAs($viewer);
    $response = $this->get("/profil/{$target->id}");
    $response->assertRedirect('/dashboard');
    $response->assertSessionHas('error');
});

test('redirect when current user membership missing', function (): void {
    $team = Team::membersTeam();
    $viewer = User::factory()->create(['current_team_id' => $team->id]);
    $target = $this->createMember();
    $this->actingAs($viewer);
    $response = $this->get("/profil/{$target->id}");
    $response->assertRedirect('/dashboard');
    $response->assertSessionHas('error');
});

test('points and badges are calculated', function (): void {
    $admin = $this->createMember('Admin');
    $member = $this->createMember();
    $general = TodoCategory::firstOrCreate(['slug' => Str::slug('General')], ['name' => 'General']);
    $maddraxikon = TodoCategory::firstOrCreate(['slug' => Str::slug('AG Maddraxikon')], ['name' => 'AG Maddraxikon']);
    $this->createTodoWithPoints($member, $general, 5);
    $this->createTodoWithPoints($member, $maddraxikon, 3);
    $book = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
    for ($i = 0; $i < 10; $i++) {
        Review::create(['team_id' => $member->currentTeam->id, 'user_id' => $member->id, 'book_id' => $book->id, 'title' => 'R'.$i, 'content' => str_repeat('A', 140)]);
    }
    $this->actingAs($admin);
    $response = $this->get("/profil/{$member->id}");
    $response->assertOk();
    $response->assertViewHas('userPoints', 8);
    $response->assertViewHas('completedTasks', 2);
    $response->assertViewHas('categoryPoints', ['General' => 5, 'AG Maddraxikon' => 3]);
    $badges = $response->viewData('badges');
    expect($badges)->toHaveCount(3)
        ->and($badges[0]['name'])->toEqual('Ersthelfer')
        ->and($badges[1]['name'])->toEqual('Retrologe (Stufe 1)')
        ->and($badges[2]['name'])->toEqual('Rezensator (Stufe 1)');
});

test('member team missing results in zero points', function (): void {
    $admin = $this->createMember('Admin');
    $member = $this->createMember();
    $admin->currentTeam->update(['name' => 'Something']);
    $this->actingAs($admin);
    $response = $this->get("/profil/{$member->id}");
    $response->assertOk();
    $response->assertViewHas('userPoints', 0);
    $response->assertViewHas('categoryPoints', []);
    $response->assertViewHas('badges', []);
});

test('retrologe badge is awarded for a maddraxikon reward without legacy todo', function (): void {
    $admin = $this->createMember('Admin');
    $member = $this->createMember();
    MaddraxikonRewardEvent::factory()->create(['user_id' => $member->id, 'awarded_points' => 1, 'status' => MaddraxikonRewardEventStatus::Awarded, 'awarded_at' => now()]);
    $this->actingAs($admin);
    $response = $this->get("/profil/{$member->id}");
    $response->assertOk();
    expect(collect($response->viewData('badges'))->pluck('name')->all())->toBe(['Retrologe (Stufe 1)']);
});

test('retrologe badge remains as historical proof after reward reversal', function (): void {
    $admin = $this->createMember('Admin');
    $member = $this->createMember();
    MaddraxikonRewardEvent::factory()->create(['user_id' => $member->id, 'awarded_points' => 1, 'status' => MaddraxikonRewardEventStatus::Reversed, 'awarded_at' => now()->subDay(), 'reversed_at' => now()]);
    $this->actingAs($admin);
    $response = $this->get("/profil/{$member->id}");
    $response->assertOk();
    expect(collect($response->viewData('badges'))->pluck('name')->all())->toBe(['Retrologe (Stufe 1)']);
});

test('retrologe badge is not awarded for an event without credited points', function (): void {
    $admin = $this->createMember('Admin');
    $member = $this->createMember();
    MaddraxikonRewardEvent::factory()->create(['user_id' => $member->id, 'awarded_points' => 0, 'status' => MaddraxikonRewardEventStatus::EvaluatedNoAward]);
    $this->actingAs($admin);
    $response = $this->get("/profil/{$member->id}");
    $response->assertOk();
    $response->assertViewHas('badges', []);
});

test('haendler badge for one swap', function (): void {
    $admin = $this->createMember('Admin');
    $member = $this->createMember();
    $partner = $this->createMember();
    $this->createCompletedSwaps($member, $partner, 1);
    $this->actingAs($admin);
    $response = $this->get("/profil/{$member->id}");
    $response->assertOk();
    $badges = $response->viewData('badges');
    expect($badges)->toHaveCount(1)
        ->and($badges[0]['name'])->toEqual('Händler (Stufe 1)');
});

test('haendler badge for ten swaps', function (): void {
    $admin = $this->createMember('Admin');
    $member = $this->createMember();
    $partner = $this->createMember();
    $this->createCompletedSwaps($member, $partner, 10);
    $this->actingAs($admin);
    $response = $this->get("/profil/{$member->id}");
    $response->assertOk();
    $badges = $response->viewData('badges');
    expect($badges)->toHaveCount(1)
        ->and($badges[0]['name'])->toEqual('Händler (Stufe 2)');
});

test('haendler badge for hundred swaps', function (): void {
    $admin = $this->createMember('Admin');
    $member = $this->createMember();
    $partner = $this->createMember();
    $this->createCompletedSwaps($member, $partner, 100);
    $this->actingAs($admin);
    $response = $this->get("/profil/{$member->id}");
    $response->assertOk();
    $badges = $response->viewData('badges');
    expect($badges)->toHaveCount(1)
        ->and($badges[0]['name'])->toEqual('Händler (Stufe 3)');
});

test('haendler badge for thousand swaps', function (): void {
    $admin = $this->createMember('Admin');
    $member = $this->createMember();
    $partner = $this->createMember();
    $this->createCompletedSwaps($member, $partner, 1000);
    $this->actingAs($admin);
    $response = $this->get("/profil/{$member->id}");
    $response->assertOk();
    $badges = $response->viewData('badges');
    expect($badges)->toHaveCount(1)
        ->and($badges[0]['name'])->toEqual('Händler (Stufe 4)');
});

test('weltrat kritiker badge for full cycle', function (): void {
    $admin = $this->createMember('Admin');
    $member = $this->createMember();
    $testStoragePath = base_path('storage/testing-weltrat');
    $originalStoragePath = $this->app->storagePath();
    $this->app->useStoragePath($testStoragePath);
    File::ensureDirectoryExists($testStoragePath.'/app/private');
    $originalLocalRoot = config('filesystems.disks.local.root');
    config(['filesystems.disks.local.root' => $testStoragePath.'/app/private']);
    $ref = new \ReflectionClass(MaddraxDataService::class);
    $property = $ref->getProperty('data');
    $property->setValue(null, null);
    $data = [['nummer' => 1, 'zyklus' => 'Weltrat-Zyklus', 'titel' => 'Roman1', 'text' => []], ['nummer' => 2, 'zyklus' => 'Weltrat-Zyklus', 'titel' => 'Roman2', 'text' => []]];
    File::put($testStoragePath.'/app/private/maddrax.json', json_encode($data));
    $book1 = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
    $book2 = Book::create(['roman_number' => 2, 'title' => 'Roman2', 'author' => 'Author']);
    Review::create(['team_id' => $member->currentTeam->id, 'user_id' => $member->id, 'book_id' => $book1->id, 'title' => 'R1', 'content' => str_repeat('A', 140)]);
    Review::create(['team_id' => $member->currentTeam->id, 'user_id' => $member->id, 'book_id' => $book2->id, 'title' => 'R2', 'content' => str_repeat('A', 140)]);
    $this->actingAs($admin);
    $response = $this->get("/profil/{$member->id}");
    $response->assertOk();
    $badges = $response->viewData('badges');
    expect($badges)->toHaveCount(1)
        ->and($badges[0]['name'])->toEqual('Weltrat-Kritiker');
    File::deleteDirectory($testStoragePath);
    $this->app->useStoragePath($originalStoragePath);
    config(['filesystems.disks.local.root' => $originalLocalRoot]);
});

test('amraka kritiker badge for full cycle', function (): void {
    $admin = $this->createMember('Admin');
    $member = $this->createMember();
    $testStoragePath = base_path('storage/testing-amraka');
    $originalStoragePath = $this->app->storagePath();
    $this->app->useStoragePath($testStoragePath);
    File::ensureDirectoryExists($testStoragePath.'/app/private');
    $originalLocalRoot = config('filesystems.disks.local.root');
    config(['filesystems.disks.local.root' => $testStoragePath.'/app/private']);
    $ref = new \ReflectionClass(MaddraxDataService::class);
    $property = $ref->getProperty('data');
    $property->setValue(null, null);
    $data = [['nummer' => 1, 'zyklus' => 'Amraka-Zyklus', 'titel' => 'Roman1', 'text' => []], ['nummer' => 2, 'zyklus' => 'Amraka-Zyklus', 'titel' => 'Roman2', 'text' => []]];
    File::put($testStoragePath.'/app/private/maddrax.json', json_encode($data));
    $book1 = Book::create(['roman_number' => 1, 'title' => 'Roman1', 'author' => 'Author']);
    $book2 = Book::create(['roman_number' => 2, 'title' => 'Roman2', 'author' => 'Author']);
    Review::create(['team_id' => $member->currentTeam->id, 'user_id' => $member->id, 'book_id' => $book1->id, 'title' => 'R1', 'content' => str_repeat('A', 140)]);
    Review::create(['team_id' => $member->currentTeam->id, 'user_id' => $member->id, 'book_id' => $book2->id, 'title' => 'R2', 'content' => str_repeat('A', 140)]);
    $this->actingAs($admin);
    $response = $this->get("/profil/{$member->id}");
    $response->assertOk();
    $badges = $response->viewData('badges');
    expect($badges)->toHaveCount(1)
        ->and($badges[0]['name'])->toEqual('Amraka-Kritiker');
    File::deleteDirectory($testStoragePath);
    $this->app->useStoragePath($originalStoragePath);
    config(['filesystems.disks.local.root' => $originalLocalRoot]);
});

test('online status is true for recent activity', function (): void {
    $viewer = $this->createMember();
    $target = $this->createMember();
    $this->actingAs($viewer);
    DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $target->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => '', 'last_activity' => now()->timestamp]);
    $response = $this->get("/profil/{$target->id}");
    $response->assertOk();
    $response->assertViewHas('isOnline', true);
    expect($response->viewData('lastSeen'))->not->toBeNull();
});

test('online status is false for old activity', function (): void {
    $viewer = $this->createMember();
    $target = $this->createMember();
    $this->actingAs($viewer);
    DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $target->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => '', 'last_activity' => now()->subMinutes(10)->timestamp]);
    $response = $this->get("/profil/{$target->id}");
    $response->assertOk();
    $response->assertViewHas('isOnline', false);
    $lastSeen = $response->viewData('lastSeen');
    expect($lastSeen->lt(now()->subMinutes(5)))->toBeTrue();
});

PhpunitMetadata::onFile(__FILE__, Large::class);
