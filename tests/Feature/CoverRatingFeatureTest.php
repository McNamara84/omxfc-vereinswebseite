<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Enums\Role;
use App\Livewire\CoverRatingIndex;
use App\Livewire\CoverRatingResults;
use App\Livewire\MyCoverRatings;
use App\Models\BookCover;
use App\Models\CoverRating;
use App\Models\UserPoint;
use App\Services\CoverRatings\CoverRatingBaxxService;
use App\Services\CoverRatings\CoverRatingResultService;
use App\Services\CoverRatings\CoverRatingService;
use App\Services\CoverRatings\CoverSelectionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CoverRatingFeatureTestFixtures::class);

beforeEach(function (): void {
    config(['cover-ratings.enabled' => true, 'cover-ratings.results_min_votes' => 3, 'cover-ratings.images.disk' => 'private']);
    RateLimiter::clear('cover-ratings:submit:1');
});

test('routes are limited to approved non applicant members and feature flag', function (): void {
    $this->get(route('cover-ratings.index'))->assertRedirect(route('login'));
    $member = $this->createUserWithRole(Role::Mitglied);
    $member->forceFill(['email_verified_at' => null])->save();
    $this->actingAs($member)->get(route('cover-ratings.index'))->assertOk()->assertSeeText('Cover-Bewertungen');
    $applicant = $this->createUserWithRole(Role::Anwaerter);
    $this->actingAs($applicant)->get(route('cover-ratings.index'))->assertRedirect();
    config(['cover-ratings.enabled' => false]);
    $this->actingAs($member)->get(route('cover-ratings.index'))->assertNotFound();
});

test('member can rate each cover once and immediately receives the next cover', function (): void {
    $member = $this->actingMember();
    $first = $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 1);
    $second = $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 2);
    $component = Livewire::test(CoverRatingIndex::class);
    $currentId = $component->get('currentCoverId');
    expect([$first->id, $second->id])->toContain($currentId);
    $component->call('rate', 5)->assertHasNoErrors()->assertDispatched('cover-rating-advanced', hasCover: true, awardedBaxx: 0)->assertSet('lastRatingId', fn (?int $id): bool => $id !== null)->assertSet('currentCoverId', fn (?int $id): bool => $id !== null && $id !== $currentId);
    $this->assertDatabaseHas('cover_ratings', ['user_id' => $member->id, 'book_cover_id' => $currentId, 'rating' => 5, 'deleted_at' => null]);
    $component->call('rate', 2)->assertDispatched('cover-rating-advanced', hasCover: false, awardedBaxx: 0)->assertSet('currentCoverId', null);
    expect(CoverRating::query()->where('user_id', $member->id)->count())->toBe(2);
    $component->assertSeeText('Alle Cover bewertet');
});

test('overview and fullscreen session render with stable accessible controls', function (): void {
    $this->actingMember();
    $cover = $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 3, 'Vollbild-Testcover');
    $component = Livewire::test(CoverRatingIndex::class)->assertSeeText('Wähle eine Serie und bewerte die Cover anschließend ungestört im Vollbildmodus.')->assertSeeText('Bewertung starten')->assertSeeText('Bewertungen beenden')->assertSeeText('Später bewerten')->assertSeeText('Bildquelle im Maddraxikon')->assertSeeText('Vollbild-Testcover');
    $html = $component->html();
    expect($html)->toContain('data-testid="cover-rating-overview"')
        ->toContain('data-testid="start-cover-rating"')
        ->toContain('data-testid="cover-rating-session"')
        ->toContain('x-bind:class="{ \'cover-rating-session--active\': active }"')
        ->toContain('role="dialog"')
        ->toContain('aria-modal="true"')
        ->toContain('x-trap.noscroll.inert="active"')
        ->toContain('data-testid="cover-rating-image-stage"')
        ->toContain('class="cover-rating-session__image"')
        ->toContain(route('cover-ratings.image', [$cover, 'large']))
        ->toContain('data-testid="cover-source-link"')
        ->toContain('href="'.e($cover->source_description_url).'"')
        ->toContain('wire:key="cover-rating-controls-'.$cover->id.'"')
        ->toContain('x-on:cover-rating-advanced.window="ratingPreview = 0"')
        ->toContain('x-on:mouseleave="ratingPreview = 0"')
        ->toContain('x-on:mousemove="ratingPreview = 5"')
        ->toContain('x-on:focus="ratingPreview = 5"')
        ->toContain('x-bind:class="ratingPreview >=')
        ->toContain('data-brina-rating-controls')->not->toContain('data-testid="cover-rating-card"');
});

test('start button is disabled and empty state is compact without an available cover', function (): void {
    $this->actingMember();
    $html = Livewire::test(CoverRatingIndex::class)->assertSeeText('Noch sind keine Cover verfügbar.')->html();
    expect($html)->toMatch('/<button[^>]*data-testid="start-cover-rating"[^>]*disabled[^>]*>/')
        ->toContain('data-testid="cover-rating-overview-empty-state"')
        ->toContain('data-cover-return-focus')
        ->toContain('tabindex="-1"');
});

test('undo announces feedback and restores the only cover', function (): void {
    $this->actingMember();
    $cover = $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 4);
    Livewire::test(CoverRatingIndex::class)->assertSet('currentCoverId', $cover->id)->call('rate', 4)->assertSet('currentCoverId', null)->call('undoLast')->assertDispatched('cover-rating-feedback', awardedBaxx: 0)->assertSet('currentCoverId', $cover->id)->assertSeeText('rückgängig gemacht');
    $this->assertSoftDeleted('cover_ratings', ['book_cover_id' => $cover->id]);
});

test('livewire rating dispatches a positive baxx award with the next cover event', function (): void {
    $member = $this->actingMember();
    app(CoverRatingBaxxService::class)->getRule()->update(['every_count' => 1, 'points' => 1, 'is_active' => true]);
    $cover = $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 5);
    Livewire::test(CoverRatingIndex::class)->assertSet('currentCoverId', $cover->id)->call('rate', 5)->assertDispatched('cover-rating-advanced', hasCover: false, awardedBaxx: 1)->assertSet('awardedBaxx', 1);
    expect(UserPoint::query()->where('user_id', $member->id)->sum('points'))->toBe(1);
});

test('client cannot replace the server selected cover id', function (): void {
    $this->actingMember();
    $selected = $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 7);
    $other = $this->readyCover(BookType::MissionMars, 7);
    $component = Livewire::test(CoverRatingIndex::class);
    $currentId = $component->get('currentCoverId');
    $tamperedId = $currentId === $selected->id ? $other->id : $selected->id;
    expect(fn () => $component->set('currentCoverId', $tamperedId))->toThrow(CannotUpdateLockedPropertyException::class);
});

test('skip only excludes a cover for the current component session', function (): void {
    $member = $this->actingMember();
    $cover = $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 10);
    Livewire::test(CoverRatingIndex::class)->assertSet('currentCoverId', $cover->id)->call('skip')->assertSet('currentCoverId', null)->assertSet('skippedCoverIds', [$cover->id])->assertSeeText('zurückgestellt');
    $this->assertDatabaseCount('cover_ratings', 0);
    Livewire::test(CoverRatingIndex::class)->assertSet('currentCoverId', $cover->id);
    $this->assertAuthenticatedAs($member);
});

test('series filter selection progress and invalid series are enforced', function (): void {
    $member = $this->actingMember();
    $maddrax = $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 20);
    $missionMars = $this->readyCover(BookType::MissionMars, 1);
    CoverRating::factory()->for($member)->for($maddrax, 'bookCover')->create();
    $selection = app(CoverSelectionService::class);
    expect($selection->progress($member, 'maddrax'))->toBe(['rated' => 1, 'total' => 1, 'remaining' => 0])
        ->and($selection->next($member, 'missionmars')?->id)->toBe($missionMars->id)
        ->and($selection->next($member, 'all', [], 'maddrax')?->id)->toBe($missionMars->id)
        ->and(fn () => $selection->next($member, 'unbekannt'))->toThrow(ValidationException::class);
});

test('rating validation ownership undo and soft deleted rerating are safe', function (): void {
    $member = $this->actingMember();
    $other = $this->createUserWithRole(Role::Mitglied);
    $cover = $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 30);
    $service = app(CoverRatingService::class);
    try {
        $service->rate($member, $cover->id, 0);
        $this->fail('An invalid Brina value was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('rating');
    }
    $created = $service->rate($member, $cover->id, 4);
    expect($created['first_rating'])->toBeTrue();
    $rating = $created['rating'];
    $service->update($member, $rating, 3);
    expect($rating->fresh()->rating)->toBe(3);
    try {
        $service->update($other, $rating, 5);
        $this->fail('A foreign rating was updated.');
    } catch (AuthorizationException) {
        expect($rating->fresh()->rating)->toBe(3);
    }
    $service->delete($member, $rating);
    $this->assertSoftDeleted($rating);
    $restored = $service->rate($member, $cover->id, 5);
    expect($restored['first_rating'])->toBeFalse()
        ->and($restored['rating']->id)->toBe($rating->id)
        ->and(CoverRating::query()->withTrashed()->count())->toBe(1);
});

test('approved members can rate without a legacy email verification timestamp', function (): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $member->forceFill(['email_verified_at' => null])->save();
    $cover = $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 31);
    $result = app(CoverRatingService::class)->rate($member, $cover->id, 5);
    expect($result['first_rating'])->toBeTrue()
        ->and($result['rating']->rating)->toBe(5);
    $this->assertDatabaseHas('cover_ratings', ['user_id' => $member->id, 'book_cover_id' => $cover->id, 'rating' => 5]);
});

test('every hundred first time cover ratings awards exactly one baxx', function (): void {
    $member = $this->actingMember();
    $covers = collect(range(1, 200))->map(fn (int $number): BookCover => $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 1000 + $number));
    foreach ($covers->take(99) as $cover) {
        CoverRating::factory()->for($member)->for($cover, 'bookCover')->create();
    }
    $service = app(CoverRatingService::class);
    $hundredth = $service->rate($member, $covers[99]->id, 5);
    expect($hundredth['awarded_baxx'])->toBe(1);
    foreach ($covers->slice(100, 99) as $cover) {
        CoverRating::factory()->for($member)->for($cover, 'bookCover')->create();
    }
    $twoHundredth = $service->rate($member, $covers[199]->id, 4);
    expect($twoHundredth['awarded_baxx'])->toBe(1)
        ->and(UserPoint::query()->where('user_id', $member->id)->sum('points'))->toBe(2);
    $service->update($member, $twoHundredth['rating'], 2);
    $service->delete($member, $twoHundredth['rating']);
    $rerated = $service->rate($member, $covers[199]->id, 3);
    expect($rerated['awarded_baxx'])->toBe(0)
        ->and(UserPoint::query()->where('user_id', $member->id)->sum('points'))->toBe(2)
        ->and(CoverRating::query()->withTrashed()->where('user_id', $member->id)->count())->toBe(200);
});

test('results are anonymous hidden until own vote and hidden below vote threshold', function (): void {
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
    $results = app(CoverRatingResultService::class)->resultsQuery($member)->get();
    expect($results->modelKeys())->toEqualCanonicalizing([$visible->id, $insufficient->id])
        ->and($results->firstWhere('id', $visible->id)->ratings_count)->toBe(3)
        ->and((float) $results->firstWhere('id', $visible->id)->ratings_avg_rating)->toBe(4.0);
    Livewire::test(CoverRatingResults::class)->assertSeeText('4,00 / 5')->assertSeeText('Noch nicht genügend Bewertungen')->assertDontSeeText('Nicht bewertet')->assertDontSeeText($voterTwo->name)->assertDontSeeText($voterThree->name);
});

test('member can manage only their own ratings', function (): void {
    $member = $this->actingMember();
    $other = $this->createUserWithRole(Role::Mitglied);
    $own = CoverRating::factory()->for($member)->for($this->readyCover(BookType::DieAbenteurer, 1), 'bookCover')->create(['rating' => 2]);
    $foreign = CoverRating::factory()->for($other)->for($this->readyCover(BookType::DieAbenteurer, 2), 'bookCover')->create(['rating' => 5]);
    Livewire::test(MyCoverRatings::class)->assertSeeText($own->bookCover->book->title)->assertDontSeeText($foreign->bookCover->book->title)->call('updateRating', $own->id, 4)->assertHasNoErrors()->call('deleteRating', $own->id)->assertHasNoErrors();
    $this->assertSoftDeleted($own);
    expect($foreign->fresh()->rating)->toBe(5);
});

test('private cover image supports variants cache validation and access checks', function (): void {
    Storage::fake('private');
    $member = $this->createUserWithRole(Role::Mitglied);
    $cover = $this->readyCover(BookType::MaddraxDieDunkleZukunftDerErde, 50);
    Storage::disk('private')->put($cover->small_path, 'small-webp');
    Storage::disk('private')->put($cover->large_path, 'large-webp');
    $this->get(route('cover-ratings.image', [$cover, 'small']))->assertRedirect(route('login'));
    $response = $this->actingAs($member)->get(route('cover-ratings.image', [$cover, 'small']))->assertOk()->assertHeader('Content-Type', 'image/webp')->assertHeader('Cache-Control', 'max-age=86400, private')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->streamedContent())->toBe('small-webp');
    $etag = $response->headers->get('ETag');
    expect($etag)->not->toBeNull();
    $this->withHeader('If-None-Match', $etag)->get(route('cover-ratings.image', [$cover, 'small']))->assertNotModified();
    Storage::disk('private')->delete($cover->large_path);
    $this->get(route('cover-ratings.image', [$cover, 'large']))->assertNotFound();
    $cover->update(['small_path' => 'private-document.webp']);
    Storage::disk('private')->put('private-document.webp', 'secret');
    $this->get(route('cover-ratings.image', [$cover, 'small']))->assertNotFound();
});
