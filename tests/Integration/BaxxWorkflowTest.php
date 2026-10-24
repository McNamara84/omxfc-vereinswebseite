<?php

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
use App\Models\Mission;
use App\Models\Review;
use App\Models\ReviewBaxxSpecialOffer;
use App\Models\Team;
use App\Models\Todo;
use App\Models\TodoCategory;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\Baxx\BaxxBookingWriter;
use App\Services\Baxx\BaxxProgressStore;
use App\Services\Baxx\BaxxRuleResolver;
use App\Services\Baxx\BaxxSpecialOfferManager;
use App\Services\MissionCompletionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class BaxxWorkflowTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function todo(User $creator, array $attributes = []): Todo
    {
        return Todo::create([...[
            'team_id' => Team::membersTeam()->id, 'created_by' => $creator->id,
            'title' => 'Gemeinsame Challenge', 'points' => 7, 'status' => TodoStatus::Open,
            'category_id' => TodoCategory::create(['name' => 'Tests', 'slug' => 'tests'])->id,
        ], ...$attributes]);
    }

    private function failNextBooking(): void
    {
        UserPoint::creating(fn () => throw new RuntimeException('Buchung fehlgeschlagen'));
    }

    public function test_rule_resolution_sees_edits_and_expiry_in_the_same_service_instance(): void
    {
        $resolver = app(BaxxRuleResolver::class);
        $this->assertSame(1, $resolver->resolve('rezension')->points);
        $resolver->base('rezension')->update(['points' => 3]);
        $this->assertSame(3, $resolver->resolve('rezension')->points);
        ReviewBaxxSpecialOffer::create(['points' => 9, 'every_count' => 1, 'is_active' => true, 'ends_at' => now()->addHour()]);
        $this->assertSame(9, $resolver->resolve('rezension')->points);
        $this->travel(1)->hours();
        $this->assertSame(3, $resolver->resolve('rezension')->points);
        $this->assertFalse($resolver->resolve('rezension')->isSpecialOffer);
    }

    public function test_progress_never_moves_backwards_or_resets_on_repeated_lock(): void
    {
        $user = $this->actingMember();
        DB::transaction(function () use ($user): void {
            $store = app(BaxxProgressStore::class);
            $progress = $store->lock($user->id, 'test', 10);
            $store->advance($progress, 12);
            $store->advance($progress, 4);
            $this->assertSame(12, $store->lock($user->id, 'test', 0)->processed_count);
        });
    }

    public function test_booking_preserves_signed_amount_team_and_historical_time(): void
    {
        $user = $this->actingMember();
        $team = Team::factory()->create();
        $at = now()->subYears(3)->startOfSecond();
        $point = app(BaxxBookingWriter::class)->record($user->id, $team->id, -4, [], $at);
        $this->assertSame(-4, $point->points);
        $this->assertSame($team->id, $point->team_id);
        $this->assertTrue($point->created_at->equalTo($at));
    }

    public function test_verification_rolls_back_status_and_activity_when_booking_fails(): void
    {
        $admin = $this->actingAdmin();
        $member = $this->createUserWithRole(Role::Mitglied);
        $todo = $this->todo($admin, ['assigned_to' => $member->id, 'status' => TodoStatus::Completed]);
        $this->failNextBooking();
        try {
            app(TodoWorkflow::class)->verify($admin, $todo->id);
            $this->fail('Expected rollback');
        } catch (RuntimeException $error) {
            $this->assertSame('Buchung fehlgeschlagen', $error->getMessage());
        }
        $this->assertSame(TodoStatus::Completed, $todo->fresh()->status);
        $this->assertNull($todo->fresh()->verified_at);
        $this->assertDatabaseCount('user_points', 0);
        $this->assertSame(0, Activity::where('subject_type', Todo::class)->where('action', 'completed')->count());
    }

    public function test_verification_is_single_use_and_deletion_removes_its_booking(): void
    {
        $admin = $this->actingAdmin();
        $todo = $this->todo($admin, ['assigned_to' => $admin->id, 'status' => TodoStatus::Completed]);
        $workflow = app(TodoWorkflow::class);
        $workflow->verify($admin, $todo->id);
        try {
            $workflow->verify($admin, $todo->id);
            $this->fail('Repeated verification must be rejected');
        } catch (ValidationException) {
            $this->assertSame(7, (int) UserPoint::sum('points'));
        }
        $this->assertSame(1, Activity::where('subject_type', Todo::class)->where('action', 'completed')->count());
        $workflow->delete($admin, $todo->id);
        $this->assertDatabaseCount('user_points', 0);
        $this->assertModelMissing($todo);
    }

    public function test_other_team_admin_cannot_verify_members_team_challenges(): void
    {
        $member = $this->actingMember();
        $other = Team::factory()->create();
        $other->users()->attach($member, ['role' => Role::Admin->value]);
        $member->switchTeam($other);
        $todo = $this->todo($member, ['assigned_to' => $member->id, 'status' => TodoStatus::Completed]);
        $this->expectException(AuthorizationException::class);
        app(TodoWorkflow::class)->verify($member->fresh(), $todo->id);
    }

    public function test_review_and_activity_roll_back_with_failed_award(): void
    {
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
            $this->assertSame(0, Activity::where('subject_type', Review::class)->count());
        }
    }

    public function test_live_review_repetition_is_rejected_but_historical_counts_keep_their_time(): void
    {
        $user = $this->actingMember();
        $book = Book::factory()->create();
        app(BaxxRuleResolver::class)->base('rezension')->update(['every_count' => 1]);
        $record = app(RecordReview::class);
        $at = now()->subYear()->startOfSecond();
        $record->execute($user, Team::membersTeam()->id, $book->id, ['title' => 'Alt', 'content' => 'Archiv'], $at);
        $this->assertTrue(UserPoint::first()->created_at->equalTo($at));
        $this->expectException(HttpException::class);
        $record->execute($user, Team::membersTeam()->id, $book->id, ['title' => 'Doppelt', 'content' => 'Inhalt']);
    }

    private function mission(User $user): Mission
    {
        return Mission::create(['user_id' => $user->id, 'name' => 'Testmission', 'origin' => 'Waashton', 'destination' => 'Salem',
            'travel_duration' => 10, 'mission_duration' => 10, 'started_at' => now()->subMinute(),
            'arrival_at' => now()->subSeconds(50), 'mission_ends_at' => now()->subSeconds(40), 'completed' => false]);
    }

    public function test_mission_completion_books_once_even_when_retried(): void
    {
        $user = $this->actingMember();
        $mission = $this->mission($user);
        $service = app(MissionCompletionService::class);
        $this->assertSame('completed', $service->status($user));
        $this->assertSame('none', $service->status($user));
        $this->assertTrue($mission->fresh()->completed);
        $this->assertSame(5, (int) UserPoint::sum('points'));
    }

    public function test_mission_completion_rolls_back_when_booking_fails(): void
    {
        $user = $this->actingMember();
        $mission = $this->mission($user);
        $this->failNextBooking();
        try {
            app(MissionCompletionService::class)->status($user);
            $this->fail('Expected rollback');
        } catch (RuntimeException) {
            $this->assertFalse($mission->fresh()->completed);
            $this->assertDatabaseCount('user_points', 0);
        }
    }

    public static function publicationRules(): array
    {
        return ['configured' => [13, true, 13], 'disabled' => [13, false, 0]];
    }

    #[DataProvider('publicationRules')]
    public function test_livewire_publication_uses_the_configured_rule(int $points, bool $active, int $expected): void
    {
        $this->actingVorstand();
        $author = $this->createUserWithRole(Role::Mitglied);
        BaxxEarningRule::where('action_key', 'fanfiction_publish')->update(['points' => $points, 'is_active' => $active]);
        Livewire::test(FanfictionCreate::class)->set('title', 'Neue Geschichte')->set('content', 'Eine längere neue Geschichte.')
            ->set('authorType', 'member')->set('userId', $author->id)->set('authorName', $author->name)
            ->set('status', 'published')->call('save')->assertHasNoErrors();
        $story = Fanfiction::firstOrFail();
        $this->assertSame(FanfictionStatus::Published, $story->status);
        $this->assertNotNull($story->reward);
        $this->assertSame($expected, (int) UserPoint::where('user_id', $author->id)->sum('points'));
        $this->assertFalse(app(PublishFanfiction::class)->execute($story));
        $this->assertSame($expected, (int) UserPoint::where('user_id', $author->id)->sum('points'));
    }

    public function test_special_offer_manager_rejects_overlap_and_expired_activation(): void
    {
        $admin = $this->actingAdmin();
        $manager = app(BaxxSpecialOfferManager::class);
        $data = ['points' => 2, 'every_count' => 1, 'is_active' => true, 'ends_at' => now()->addHour()];
        $first = $manager->save($admin, 'rezension', $data, null, 'review');
        try {
            $manager->save($admin, 'rezension', $data, null, 'review');
            $this->fail('Overlap must fail');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('reviewIsActive', $error->errors());
        }
        $this->assertSame('deaktiviert', $manager->toggle($admin, 'rezension', $first->id, 'review'));
        $this->travel(1)->hours();
        $this->expectException(ValidationException::class);
        $manager->toggle($admin, 'rezension', $first->id, 'review');
    }
}
