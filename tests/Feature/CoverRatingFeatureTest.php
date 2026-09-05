<?php

namespace Tests\Feature;

use App\Enums\BookType;
use App\Enums\Role;
use App\Livewire\CoverRatingIndex;
use App\Livewire\CoverRatingResults;
use App\Livewire\MyCoverRatings;
use App\Models\Book;
use App\Models\BookCover;
use App\Models\CoverRating;
use App\Models\UserPoint;
use App\Services\CoverRatings\CoverRatingResultService;
use App\Services\CoverRatings\CoverRatingService;
use App\Services\CoverRatings\CoverSelectionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class CoverRatingFeatureTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cover-ratings.enabled' => true,
            'cover-ratings.results_min_votes' => 3,
            'cover-ratings.images.disk' => 'private',
        ]);
        RateLimiter::clear('cover-ratings:submit:1');
    }

    public function test_routes_are_limited_to_approved_non_applicant_members_and_feature_flag(): void
    {
        $this->get(route('cover-ratings.index'))
            ->assertRedirect(route('login'));

        $member = $this->createUserWithRole(Role::Mitglied);
        $member->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($member)
            ->get(route('cover-ratings.index'))
            ->assertOk()
            ->assertSeeText('Cover-Bewertungen');

        $applicant = $this->createUserWithRole(Role::Anwaerter);
        $this->actingAs($applicant)
            ->get(route('cover-ratings.index'))
            ->assertRedirect();

        config(['cover-ratings.enabled' => false]);
        $this->actingAs($member)
            ->get(route('cover-ratings.index'))
            ->assertNotFound();
    }

    public function test_member_can_rate_each_cover_once_and_immediately_receives_the_next_cover(): void
    {
        $member = $this->actingMember();
        $first = $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 1);
        $second = $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 2);

        $component = Livewire::test(CoverRatingIndex::class);
        $currentId = $component->get('currentCoverId');

        $this->assertContains($currentId, [$first->id, $second->id]);

        $component
            ->call('rate', 5)
            ->assertHasNoErrors()
            ->assertDispatched('cover-rating-advanced')
            ->assertSet('lastRatingId', fn (?int $id): bool => $id !== null)
            ->assertSet('currentCoverId', fn (?int $id): bool => $id !== null && $id !== $currentId);

        $this->assertDatabaseHas('cover_ratings', [
            'user_id' => $member->id,
            'book_cover_id' => $currentId,
            'rating' => 5,
            'deleted_at' => null,
        ]);

        $component->call('rate', 2)->assertSet('currentCoverId', null);

        $this->assertSame(2, CoverRating::query()->where('user_id', $member->id)->count());
        $component->assertSeeText('Alle Cover bewertet');
    }

    public function test_client_cannot_replace_the_server_selected_cover_id(): void
    {
        $this->actingMember();
        $selected = $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 7);
        $other = $this->readyCover(BookType::MissionMars, 7);
        $component = Livewire::test(CoverRatingIndex::class);
        $currentId = $component->get('currentCoverId');
        $tamperedId = $currentId === $selected->id ? $other->id : $selected->id;

        $this->expectException(CannotUpdateLockedPropertyException::class);

        $component->set('currentCoverId', $tamperedId);
    }

    public function test_skip_only_excludes_a_cover_for_the_current_component_session(): void
    {
        $member = $this->actingMember();
        $cover = $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 10);

        Livewire::test(CoverRatingIndex::class)
            ->assertSet('currentCoverId', $cover->id)
            ->call('skip')
            ->assertSet('currentCoverId', null)
            ->assertSet('skippedCoverIds', [$cover->id])
            ->assertSeeText('zurückgestellt');

        $this->assertDatabaseCount('cover_ratings', 0);

        Livewire::test(CoverRatingIndex::class)
            ->assertSet('currentCoverId', $cover->id);

        $this->assertAuthenticatedAs($member);
    }

    public function test_series_filter_selection_progress_and_invalid_series_are_enforced(): void
    {
        $member = $this->actingMember();
        $maddrax = $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 20);
        $missionMars = $this->readyCover(BookType::MissionMars, 1);
        CoverRating::factory()->for($member)->for($maddrax, 'bookCover')->create();
        $selection = app(CoverSelectionService::class);

        $this->assertSame([
            'rated' => 1,
            'total' => 1,
            'remaining' => 0,
        ], $selection->progress($member, 'maddrax'));
        $this->assertSame($missionMars->id, $selection->next($member, 'missionmars')?->id);
        $this->assertSame($missionMars->id, $selection->next($member, 'all', [], 'maddrax')?->id);

        $this->expectException(ValidationException::class);
        $selection->next($member, 'unbekannt');
    }

    public function test_rating_validation_ownership_undo_and_soft_deleted_rerating_are_safe(): void
    {
        $member = $this->actingMember();
        $other = $this->createUserWithRole(Role::Mitglied);
        $cover = $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 30);
        $service = app(CoverRatingService::class);

        try {
            $service->rate($member, $cover->id, 0);
            $this->fail('An invalid Brina value was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rating', $exception->errors());
        }

        $created = $service->rate($member, $cover->id, 4);
        $this->assertTrue($created['first_rating']);
        $rating = $created['rating'];
        $service->update($member, $rating, 3);
        $this->assertSame(3, $rating->fresh()->rating);

        try {
            $service->update($other, $rating, 5);
            $this->fail('A foreign rating was updated.');
        } catch (AuthorizationException) {
            $this->assertSame(3, $rating->fresh()->rating);
        }

        $service->delete($member, $rating);
        $this->assertSoftDeleted($rating);

        $restored = $service->rate($member, $cover->id, 5);
        $this->assertFalse($restored['first_rating']);
        $this->assertSame($rating->id, $restored['rating']->id);
        $this->assertSame(1, CoverRating::query()->withTrashed()->count());
    }

    public function test_approved_members_can_rate_without_a_legacy_email_verification_timestamp(): void
    {
        $member = $this->createUserWithRole(Role::Mitglied);
        $member->forceFill(['email_verified_at' => null])->save();
        $cover = $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 31);

        $result = app(CoverRatingService::class)->rate($member, $cover->id, 5);

        $this->assertTrue($result['first_rating']);
        $this->assertSame(5, $result['rating']->rating);
        $this->assertDatabaseHas('cover_ratings', [
            'user_id' => $member->id,
            'book_cover_id' => $cover->id,
            'rating' => 5,
        ]);
    }

    public function test_every_hundred_first_time_cover_ratings_awards_exactly_one_baxx(): void
    {
        $member = $this->actingMember();
        $covers = collect(range(1, 200))->map(
            fn (int $number): BookCover => $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 1_000 + $number),
        );

        foreach ($covers->take(99) as $cover) {
            CoverRating::factory()->for($member)->for($cover, 'bookCover')->create();
        }

        $service = app(CoverRatingService::class);
        $hundredth = $service->rate($member, $covers[99]->id, 5);
        $this->assertSame(1, $hundredth['awarded_baxx']);

        foreach ($covers->slice(100, 99) as $cover) {
            CoverRating::factory()->for($member)->for($cover, 'bookCover')->create();
        }

        $twoHundredth = $service->rate($member, $covers[199]->id, 4);
        $this->assertSame(1, $twoHundredth['awarded_baxx']);
        $this->assertSame(2, UserPoint::query()->where('user_id', $member->id)->sum('points'));

        $service->update($member, $twoHundredth['rating'], 2);
        $service->delete($member, $twoHundredth['rating']);
        $rerated = $service->rate($member, $covers[199]->id, 3);

        $this->assertSame(0, $rerated['awarded_baxx']);
        $this->assertSame(2, UserPoint::query()->where('user_id', $member->id)->sum('points'));
        $this->assertSame(200, CoverRating::query()->withTrashed()->where('user_id', $member->id)->count());
    }

    public function test_results_are_anonymous_hidden_until_own_vote_and_hidden_below_vote_threshold(): void
    {
        $member = $this->actingMember();
        $voterTwo = $this->createUserWithRole(Role::Mitglied);
        $voterThree = $this->createUserWithRole(Role::Mitglied);
        $visible = $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 40, 'Sichtbares Ergebnis');
        $insufficient = $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 41, 'Noch verborgen');
        $foreignOnly = $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 42, 'Nicht bewertet');

        CoverRating::factory()->for($member)->for($visible, 'bookCover')->create(['rating' => 5]);
        CoverRating::factory()->for($voterTwo)->for($visible, 'bookCover')->create(['rating' => 4]);
        CoverRating::factory()->for($voterThree)->for($visible, 'bookCover')->create(['rating' => 3]);
        CoverRating::factory()->for($member)->for($insufficient, 'bookCover')->create(['rating' => 2]);
        CoverRating::factory()->for($voterTwo)->for($foreignOnly, 'bookCover')->create(['rating' => 1]);

        $results = app(CoverRatingResultService::class)
            ->resultsQuery($member)
            ->get();

        $this->assertEqualsCanonicalizing([$visible->id, $insufficient->id], $results->modelKeys());
        $this->assertSame(3, $results->firstWhere('id', $visible->id)->ratings_count);
        $this->assertSame(4.0, (float) $results->firstWhere('id', $visible->id)->ratings_avg_rating);

        Livewire::test(CoverRatingResults::class)
            ->assertSeeText('4,00 / 5')
            ->assertSeeText('Noch nicht genügend Bewertungen')
            ->assertDontSeeText('Nicht bewertet')
            ->assertDontSeeText($voterTwo->name)
            ->assertDontSeeText($voterThree->name);
    }

    public function test_member_can_manage_only_their_own_ratings(): void
    {
        $member = $this->actingMember();
        $other = $this->createUserWithRole(Role::Mitglied);
        $own = CoverRating::factory()
            ->for($member)
            ->for($this->readyCover(BookType::DieAbenteurer, 1), 'bookCover')
            ->create(['rating' => 2]);
        $foreign = CoverRating::factory()
            ->for($other)
            ->for($this->readyCover(BookType::DieAbenteurer, 2), 'bookCover')
            ->create(['rating' => 5]);

        Livewire::test(MyCoverRatings::class)
            ->assertSeeText($own->bookCover->book->title)
            ->assertDontSeeText($foreign->bookCover->book->title)
            ->call('updateRating', $own->id, 4)
            ->assertHasNoErrors()
            ->call('deleteRating', $own->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted($own);
        $this->assertSame(5, $foreign->fresh()->rating);
    }

    public function test_private_cover_image_supports_variants_cache_validation_and_access_checks(): void
    {
        Storage::fake('private');
        $member = $this->createUserWithRole(Role::Mitglied);
        $cover = $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 50);
        Storage::disk('private')->put($cover->small_path, 'small-webp');
        Storage::disk('private')->put($cover->large_path, 'large-webp');

        $this->get(route('cover-ratings.image', [$cover, 'small']))
            ->assertRedirect(route('login'));

        $response = $this->actingAs($member)
            ->get(route('cover-ratings.image', [$cover, 'small']))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/webp')
            ->assertHeader('Cache-Control', 'max-age=86400, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('small-webp', $response->streamedContent());
        $etag = $response->headers->get('ETag');
        $this->assertNotNull($etag);

        $this->withHeader('If-None-Match', $etag)
            ->get(route('cover-ratings.image', [$cover, 'small']))
            ->assertNotModified();

        Storage::disk('private')->delete($cover->large_path);
        $this->get(route('cover-ratings.image', [$cover, 'large']))
            ->assertNotFound();

        $cover->update(['small_path' => 'private-document.webp']);
        Storage::disk('private')->put('private-document.webp', 'secret');
        $this->get(route('cover-ratings.image', [$cover, 'small']))
            ->assertNotFound();
    }

    private function readyCover(
        BookType $type,
        int $number,
        ?string $title = null,
    ): BookCover {
        $book = Book::factory()->create([
            'type' => $type,
            'roman_number' => $number,
            'title' => $title ?? $type->label().' '.$number,
        ]);

        return BookCover::factory()->for($book)->create([
            'small_path' => "cover-ratings/{$book->id}/small.webp",
            'large_path' => "cover-ratings/{$book->id}/large.webp",
        ]);
    }
}
