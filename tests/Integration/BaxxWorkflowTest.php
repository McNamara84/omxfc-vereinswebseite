<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Actions\Fanfiction\PublishFanfiction;
use App\Actions\Reviews\RecordReview;
use App\Actions\Todos\TodoWorkflow;
use App\Enums\FanfictionStatus;
use App\Enums\Role;
use App\Enums\TodoStatus;
use App\Livewire\FanfictionCreate;
use App\Models\Activity;
use App\Models\BaxxEarningRule;
use App\Models\Book;
use App\Models\Fanfiction;
use App\Models\Review;
use App\Models\ReviewBaxxSpecialOffer;
use App\Models\Team;
use App\Models\Todo;
use App\Models\UserPoint;
use App\Services\Baxx\BaxxBookingWriter;
use App\Services\Baxx\BaxxProgressStore;
use App\Services\Baxx\BaxxRuleResolver;
use App\Services\Baxx\BaxxSpecialOfferManager;
use App\Services\MissionCompletionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(BaxxWorkflowTestFixtures::class);
dataset('BaxxWorkflowTest.publicationRules', static fn (): array => ['configured' => [13, true, 13], 'disabled' => [13, false, 0]]);
test('rule resolution sees edits and expiry in the same service instance', function (): void {
    $resolver = app(BaxxRuleResolver::class);
    expect($resolver->resolve('rezension')->points)->toBe(1);
    $resolver->base('rezension')->update(['points' => 3]);
    expect($resolver->resolve('rezension')->points)->toBe(3);
    ReviewBaxxSpecialOffer::create(['points' => 9, 'every_count' => 1, 'is_active' => true, 'ends_at' => now()->addHour()]);
    expect($resolver->resolve('rezension')->points)->toBe(9);
    $this->travel(1)->hours();
    expect($resolver->resolve('rezension')->points)->toBe(3)
        ->and($resolver->resolve('rezension')->isSpecialOffer)->toBeFalse();
});
test('progress never moves backwards or resets on repeated lock', function (): void {
    $user = $this->actingMember();
    DB::transaction(function () use ($user): void {
        $store = app(BaxxProgressStore::class);
        $progress = $store->lock($user->id, 'test', 10);
        $store->advance($progress, 12);
        $store->advance($progress, 4);
        expect($store->lock($user->id, 'test', 0)->processed_count)->toBe(12);
    });
});
test('booking preserves signed amount team and historical time', function (): void {
    $user = $this->actingMember();
    $team = Team::factory()->create();
    $at = now()->subYears(3)->startOfSecond();
    $point = app(BaxxBookingWriter::class)->record($user->id, $team->id, -4, [], $at);
    expect($point->points)->toBe(-4)
        ->and($point->team_id)->toBe($team->id)
        ->and($point->created_at->equalTo($at))->toBeTrue();
});
test('verification rolls back status and activity when booking fails', function (): void {
    $admin = $this->actingAdmin();
    $member = $this->createUserWithRole(Role::Mitglied);
    $todo = $this->todo($admin, ['assigned_to' => $member->id, 'status' => TodoStatus::Completed]);
    $this->failNextBooking();
    try {
        app(TodoWorkflow::class)->verify($admin, $todo->id);
        $this->fail('Expected rollback');
    } catch (RuntimeException $error) {
        expect($error->getMessage())->toBe('Buchung fehlgeschlagen');
    }
    expect($todo->fresh()->status)->toBe(TodoStatus::Completed)
        ->and($todo->fresh()->verified_at)->toBeNull();
    $this->assertDatabaseCount('user_points', 0);
    expect(Activity::where('subject_type', Todo::class)->where('action', 'completed')->count())->toBe(0);
});
test('verification is single use and deletion removes its booking', function (): void {
    $admin = $this->actingAdmin();
    $todo = $this->todo($admin, ['assigned_to' => $admin->id, 'status' => TodoStatus::Completed]);
    $workflow = app(TodoWorkflow::class);
    $workflow->verify($admin, $todo->id);
    try {
        $workflow->verify($admin, $todo->id);
        $this->fail('Repeated verification must be rejected');
    } catch (ValidationException) {
        expect((int) UserPoint::sum('points'))->toBe(7);
    }
    expect(Activity::where('subject_type', Todo::class)->where('action', 'completed')->count())->toBe(1);
    $workflow->delete($admin, $todo->id);
    $this->assertDatabaseCount('user_points', 0);
    $this->assertModelMissing($todo);
});
test('other team admin cannot verify members team challenges', function (): void {
    $member = $this->actingMember();
    $other = Team::factory()->create();
    $other->users()->attach($member, ['role' => Role::Admin->value]);
    $member->switchTeam($other);
    $todo = $this->todo($member, ['assigned_to' => $member->id, 'status' => TodoStatus::Completed]);
    expect(fn () => app(TodoWorkflow::class)->verify($member->fresh(), $todo->id))->toThrow(AuthorizationException::class);
});
test('review and activity roll back with failed award', function (): void {
    $user = $this->actingMember();
    $book = Book::factory()->create();
    app(BaxxRuleResolver::class)->base('rezension')->update(['every_count' => 1]);
    $this->failNextBooking();
    try {
        app(RecordReview::class)->execute($user, Team::membersTeam()->id, $book->id, ['title' => 'Titel', 'content' => 'Inhalt']);
        $this->fail('Expected rollback');
    } catch (RuntimeException) {
        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseCount('user_points', 0);
        expect(Activity::where('subject_type', Review::class)->count())->toBe(0);
    }
});
test('live review repetition is rejected but historical counts keep their time', function (): void {
    $user = $this->actingMember();
    $book = Book::factory()->create();
    app(BaxxRuleResolver::class)->base('rezension')->update(['every_count' => 1]);
    $record = app(RecordReview::class);
    $at = now()->subYear()->startOfSecond();
    $record->execute($user, Team::membersTeam()->id, $book->id, ['title' => 'Alt', 'content' => 'Archiv'], $at);
    expect(UserPoint::first()->created_at->equalTo($at))->toBeTrue()
        ->and(fn () => $record->execute($user, Team::membersTeam()->id, $book->id, ['title' => 'Doppelt', 'content' => 'Inhalt']))->toThrow(HttpException::class);
});
test('mission completion books once even when retried', function (): void {
    $user = $this->actingMember();
    $mission = $this->mission($user);
    $service = app(MissionCompletionService::class);
    expect($service->status($user))->toBe('completed')
        ->and($service->status($user))->toBe('none')
        ->and($mission->fresh()->completed)->toBeTrue()
        ->and((int) UserPoint::sum('points'))->toBe(5);
});
test('mission completion rolls back when booking fails', function (): void {
    $user = $this->actingMember();
    $mission = $this->mission($user);
    $this->failNextBooking();
    try {
        app(MissionCompletionService::class)->status($user);
        $this->fail('Expected rollback');
    } catch (RuntimeException) {
        expect($mission->fresh()->completed)->toBeFalse();
        $this->assertDatabaseCount('user_points', 0);
    }
});
test('livewire publication uses the configured rule', function (int $points, bool $active, int $expected): void {
    $this->actingVorstand();
    $author = $this->createUserWithRole(Role::Mitglied);
    BaxxEarningRule::where('action_key', 'fanfiction_publish')->update(['points' => $points, 'is_active' => $active]);
    Livewire::test(FanfictionCreate::class)->set('title', 'Neue Geschichte')->set('content', 'Eine längere neue Geschichte.')->set('authorType', 'member')->set('userId', $author->id)->set('authorName', $author->name)->set('status', 'published')->call('save')->assertHasNoErrors();
    $story = Fanfiction::firstOrFail();
    expect($story->status)->toBe(FanfictionStatus::Published)
        ->and($story->reward)->not->toBeNull()
        ->and((int) UserPoint::where('user_id', $author->id)->sum('points'))->toBe($expected)
        ->and(app(PublishFanfiction::class)->execute($story))->toBeFalse()
        ->and((int) UserPoint::where('user_id', $author->id)->sum('points'))->toBe($expected);
})->with('BaxxWorkflowTest.publicationRules');
test('special offer manager rejects overlap and expired activation', function (): void {
    $admin = $this->actingAdmin();
    $manager = app(BaxxSpecialOfferManager::class);
    $data = ['points' => 2, 'every_count' => 1, 'is_active' => true, 'ends_at' => now()->addHour()];
    $first = $manager->save($admin, 'rezension', $data, null, 'review');
    try {
        $manager->save($admin, 'rezension', $data, null, 'review');
        $this->fail('Overlap must fail');
    } catch (ValidationException $error) {
        expect($error->errors())->toHaveKey('reviewIsActive');
    }
    expect($manager->toggle($admin, 'rezension', $first->id, 'review'))->toBe('deaktiviert');
    $this->travel(1)->hours();
    expect(fn () => $manager->toggle($admin, 'rezension', $first->id, 'review'))->toThrow(ValidationException::class);
});
