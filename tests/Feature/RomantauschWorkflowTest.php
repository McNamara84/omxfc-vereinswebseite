<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Romantausch\BundleWorkflow;
use App\Actions\Romantausch\OfferWorkflow;
use App\Actions\Romantausch\SwapWorkflow;
use App\Enums\BookType;
use App\Enums\Role;
use App\Http\Requests\StoreBookOfferRequest;
use App\Livewire\RomantauschBundleForm;
use App\Livewire\RomantauschIndex;
use App\Livewire\RomantauschOfferForm;
use App\Livewire\RomantauschRequestForm;
use App\Models\Activity;
use App\Models\BaxxEarningRule;
use App\Models\Book;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\Team;
use App\Services\Romantausch\SwapMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RomantauschWorkflowTestFixtures::class);
dataset('RomantauschWorkflowTest.staleForms', static fn (): array => [['offer', false], ['offer', true], ['request', false], ['request', true]]);
dataset('RomantauschWorkflowTest.lazyComponents', static fn (): array => [[RomantauschOfferForm::class], [RomantauschRequestForm::class], [RomantauschIndex::class]]);
dataset('RomantauschWorkflowTest.roles', static function (): array {
    $cases = [];
    foreach ([Role::Mitglied, Role::Anwaerter, Role::Kassenwart, Role::Vorstand, Role::Admin] as $role) {
        foreach ([false, true] as $http) {
            $cases[$role->name.($http ? '-http' : '-livewire')] = [$role, $http];
        }
    }

    return $cases;
});
beforeEach(function (): void {
    Storage::fake('public');
    Mail::fake();
    foreach (range(1, 4) as $number) {
        Book::create(['roman_number' => $number, 'title' => "Roman {$number}", 'author' => 'Autor', 'type' => BookType::MaddraxDieDunkleZukunftDerErde]);
    }
    BaxxEarningRule::whereIn('action_key', ['romantausch_offer', 'romantausch_request', 'romantausch_swap_complete'])->update(['points' => 5, 'every_count' => 1, 'is_active' => true]);
});
test('opening a blocked form redirects with an error', function (string $kind, bool $matched): void {
    $actor = $this->actingMember();
    [$offer, $request] = $this->pair($kind === 'offer' ? $actor : null);
    if ($kind === 'request') {
        $request->update(['user_id' => $actor->id]);
    }
    $source = $kind === 'offer' ? $offer : $request;
    if ($matched) {
        BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
    } else {
        $source->update(['completed' => true]);
    }
    Livewire::test($kind === 'offer' ? RomantauschOfferForm::class : RomantauschRequestForm::class, [$kind === 'offer' ? 'offer' : 'bookRequest' => $source])->assertRedirect(route('romantausch.index'));
    expect(session('error'))->toContain('nicht bearbeitet werden')
        ->and($source->fresh()->condition)->toBe('Z0');
    $this->assertDatabaseCount('user_points', 0);
})->with('RomantauschWorkflowTest.staleForms');
test('lazy components show a skeleton until loaded', function (string $component): void {
    $this->actingMember();
    Livewire::test($component, ['lazy' => true])->assertSeeHtml('aria-hidden="true"')->assertSeeHtml('skeleton')->assertDontSeeHtml('wire:submit="save"');
})->with('RomantauschWorkflowTest.lazyComponents');
test('guest has no personal offers or requests', function (): void {
    $this->pair();
    $this->get(route('romantausch.index'))->assertRedirect(route('login'));
    $component = app(RomantauschIndex::class);
    expect($component->ownOffers->isEmpty())->toBeTrue()
        ->and($component->ownRequests->isEmpty())->toBeTrue();
});
test('advertised series are selectable and accepted by the http adapter', function (): void {
    $actor = $this->actingMember();
    $types = StoreBookOfferRequest::getAllowedTypes();
    expect($types)->not->toBeEmpty();
    Livewire::test(RomantauschOfferForm::class)->assertSet('types', $types);
    Livewire::test(RomantauschRequestForm::class)->assertSet('types', $types);
    foreach ($types as $type) {
        $book = Book::firstOrCreate(['type' => $type, 'roman_number' => 1], ['title' => 'Roman 1', 'author' => 'Autor']);
        $this->post(route('romantausch.store-offer'), $this->data(['series' => $type->value]))->assertSessionHasNoErrors()->assertRedirect(route('romantausch.index'));
        $this->assertDatabaseHas('book_offers', ['user_id' => $actor->id, 'series' => $type->value, 'book_number' => $book->roman_number]);
    }
});
test('form rechecks state when saving', function (string $kind, bool $matched): void {
    $actor = $this->actingMember();
    [$offer, $request] = $this->pair($kind === 'offer' ? $actor : null);
    if ($kind === 'request') {
        $request->update(['user_id' => $actor->id]);
    }
    $form = Livewire::test($kind === 'offer' ? RomantauschOfferForm::class : RomantauschRequestForm::class, [$kind === 'offer' ? 'offer' : 'bookRequest' => $kind === 'offer' ? $offer : $request]);
    if ($matched) {
        BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
    } else {
        ($kind === 'offer' ? $offer : $request)->update(['completed' => true]);
    }
    $form->set('condition', 'changed')->call('save')->assertHasErrors($kind === 'offer' ? 'offer' : 'book_number');
    expect(($kind === 'offer' ? $offer : $request)->fresh()->condition)->toBe('Z0');
    $this->assertDatabaseCount('user_points', 0);
})->with('RomantauschWorkflowTest.staleForms');
test('manual completion requires club management', function (Role $role, bool $http): void {
    $actor = $this->actingMember($role);
    [$offer, $request] = $this->pair();
    $allowed = in_array($role, [Role::Vorstand, Role::Admin], true);
    if ($http) {
        $response = $this->post(route('romantausch.complete-swap', [$offer, $request]));
        // Applicants are redirected by the unchanged route middleware.
        $allowed || $role === Role::Anwaerter ? $response->assertRedirect() : $response->assertForbidden();
    } else {
        $component = Livewire::test(RomantauschIndex::class)->call('completeSwap', $offer->id, $request->id);
        $allowed ? $component->assertDispatched('toast') : $component->assertForbidden();
    }
    $this->assertDatabaseCount('book_swaps', $allowed ? 1 : 0);
    $this->assertDatabaseCount('user_points', $allowed ? 2 : 0);
})->with('RomantauschWorkflowTest.roles');
test('management role in other team does not grant manual completion', function (): void {
    $actor = $this->actingMember();
    $team = Team::factory()->create();
    $team->users()->attach($actor, ['role' => Role::Admin->value]);
    $actor->forceFill(['current_team_id' => $team->id])->save();
    [$offer, $request] = $this->pair();
    $this->post(route('romantausch.complete-swap', [$offer, $request]))->assertForbidden();
    Livewire::test(RomantauschIndex::class)->call('completeSwap', $offer->id, $request->id)->assertForbidden();
    $this->assertDatabaseCount('book_swaps', 0);
});
test('club manager can complete with another current team', function (): void {
    $actor = $this->actingVorstand();
    $actor->forceFill(['current_team_id' => Team::factory()->create()->id])->save();
    [$offer, $request] = $this->pair();
    $this->post(route('romantausch.complete-swap', [$offer, $request]))->assertRedirect(route('romantausch.index'));
    $this->assertDatabaseCount('user_points', 2);
});
test('outsider cannot confirm through either adapter', function (): void {
    $this->actingMember();
    [$offer, $request] = $this->pair();
    $swap = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
    $this->post(route('romantausch.confirm-swap', $swap))->assertForbidden();
    Livewire::test(RomantauschIndex::class)->call('confirmSwap', $swap->id)->assertForbidden();
    expect($swap->fresh()->offer_confirmed)->toBeFalse()
        ->and($swap->fresh()->request_confirmed)->toBeFalse();
    $this->assertDatabaseCount('user_points', 0);
});
test('completion is idempotent across both adapters and confirmation', function (): void {
    $manager = $this->actingVorstand();
    [$offer, $request] = $this->pair();
    $this->post(route('romantausch.complete-swap', [$offer, $request]))->assertSessionHas('success', 'Tausch abgeschlossen! Beide erhalten 5 Baxx.');
    Livewire::test(RomantauschIndex::class)->call('completeSwap', $offer->id, $request->id)->assertDispatched('toast', type: 'success', title: 'Dieser Tausch wurde bereits abgeschlossen.');
    $swap = BookSwap::firstOrFail();
    $result = app(SwapWorkflow::class)->confirm($offer->user, $swap->id);
    expect($result->alreadyCompleted)->toBeTrue()
        ->and($result->awards)->toBe([]);
    $this->assertDatabaseCount('book_swaps', 1);
    $this->assertDatabaseCount('user_points', 2);
    expect(Activity::where('action', 'swap_completed')->count())->toBe(1);
});
test('existing match is completed instead of duplicated', function (): void {
    $manager = $this->actingVorstand();
    [$offer, $request] = $this->pair();
    $swap = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
    $result = app(SwapWorkflow::class)->complete($manager, $offer->id, $request->id);
    expect($result->swap->id)->toBe($swap->id);
    $this->assertDatabaseCount('book_swaps', 1);
});
test('wrong pair and already assigned source are rejected', function (): void {
    $manager = $this->actingVorstand();
    [$offer, $request] = $this->pair();
    [, $otherRequest] = $this->pair();
    BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
    $this->postJson(route('romantausch.complete-swap', [$offer, $otherRequest]))->assertUnprocessable()->assertJsonValidationErrors('swap');
    $otherRequest->update(['book_number' => 2]);
    $this->postJson(route('romantausch.complete-swap', [$offer, $otherRequest]))->assertUnprocessable()->assertJsonValidationErrors('swap');
    $this->assertDatabaseCount('user_points', 0);
});
test('disabled rule never promises a fixed reward', function (): void {
    BaxxEarningRule::where('action_key', 'romantausch_swap_complete')->update(['is_active' => false]);
    [$offer, $request] = $this->pair();
    $swap = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
    app(SwapWorkflow::class)->confirm($offer->user, $swap->id);
    $result = app(SwapWorkflow::class)->confirm($request->user, $swap->id);
    expect($result->message())->toBe('Tausch abgeschlossen. Es wurden keine Baxx vergeben.');
    $this->assertDatabaseCount('user_points', 0);
});
test('update failure preserves old photos and removes new upload', function (): void {
    $actor = $this->actingMember();
    [$offer] = $this->pair($actor);
    Storage::disk('public')->put('book-offers/old.jpg', 'old');
    $offer->update(['photos' => ['book-offers/old.jpg']]);
    BookOffer::updating(fn () => throw new RuntimeException('DB update failed'));
    $this->put(route('romantausch.update-offer', $offer), $this->data(['remove_photos' => ['book-offers/old.jpg'], 'photos' => [UploadedFile::fake()->image('new.jpg')]]))->assertSessionHas('error');
    expect($offer->fresh()->photos)->toBe(['book-offers/old.jpg'])
        ->and(Storage::disk('public')->allFiles('book-offers'))->toBe(['book-offers/old.jpg']);
});
test('failed bundle activity rolls back offers points and uploads', function (): void {
    $actor = $this->actingMember();
    Activity::creating(function ($activity): void {
        if ($activity->action === 'bundle_created') {
            throw new RuntimeException('activity failed');
        }
    });
    Livewire::test(RomantauschBundleForm::class)->set('book_numbers', '1-2')->set('photos', [UploadedFile::fake()->image('new.jpg')])->call('save')->assertHasErrors('book_numbers');
    $this->assertDatabaseCount('book_offers', 0);
    $this->assertDatabaseCount('user_points', 0);
    $this->assertDatabaseCount('baxx_earning_progress', 0);
    expect(Storage::disk('public')->allFiles('book-offers'))->toBe([]);
    Mail::assertNothingQueued();
});
test('matching failure keeps committed source and photos', function (): void {
    $actor = $this->actingMember();
    $this->mock(SwapMatchingService::class, fn ($mock) => $mock->shouldReceive('matchSwap')->once()->andThrow(new RuntimeException('matching failed')));
    $this->post(route('romantausch.store-offer'), $this->data(['photos' => [UploadedFile::fake()->image('new.jpg')]]))->assertRedirect(route('romantausch.index'))->assertSessionHas('success')->assertSessionHas('warning');
    $offer = BookOffer::firstOrFail();
    Storage::disk('public')->assertExists($offer->photos[0]);
    $this->assertDatabaseCount('user_points', 1);
    expect(Activity::where('subject_type', BookOffer::class)->count())->toBe(1);
});
test('outer rollback removes uploaded files and never runs matching', function (): void {
    $actor = $this->actingMember();
    $this->mock(SwapMatchingService::class, fn ($mock) => $mock->shouldNotReceive('matchSwap'));
    DB::beginTransaction();
    $result = app(OfferWorkflow::class)->create($actor, $this->data(['photos' => [UploadedFile::fake()->image('new.jpg')]]));
    Storage::disk('public')->assertExists($result->model->photos[0]);
    DB::rollBack();
    $this->assertDatabaseCount('book_offers', 0);
    $this->assertDatabaseCount('user_points', 0);
    expect(Storage::disk('public')->allFiles('book-offers'))->toBe([]);
    Mail::assertNothingQueued();
});
test('shared photo survives until its last source is deleted', function (): void {
    // RefreshDatabase's test manager executes callbacks at the workflow's commit;
    // RomantauschPhotoCommitTest also checks the production manager at level 0.
    $actor = $this->actingMember();
    [$offer, $request] = $this->pair($actor);
    $sibling = BookOffer::create($offer->getAttributes());
    $path = 'book-offers/shared.jpg';
    Storage::disk('public')->put($path, 'shared');
    $offer->update(['photos' => [$path]]);
    $sibling->update(['photos' => [$path]]);
    BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
    $this->delete(route('romantausch.delete-offer', $offer))->assertRedirect();
    Storage::disk('public')->assertExists($path);
    $this->assertDatabaseCount('book_swaps', 0);
    $this->assertDatabaseHas('activities', ['user_id' => $request->user_id, 'action' => 'match_cancelled_by_offer_owner']);
    Livewire::test(RomantauschIndex::class)->call('deleteOffer', $sibling->id)->assertDispatched('toast', type: 'success', title: 'Angebot gelöscht.');
    Storage::disk('public')->assertMissing($path);
});
test('all legacy write routes remain functional', function (): void {
    $actor = $this->actingVorstand();
    $this->post(route('romantausch.store-offer'), $this->data())->assertRedirect();
    $offer = BookOffer::firstOrFail();
    $this->put(route('romantausch.update-offer', $offer), $this->data(['condition' => 'updated']))->assertRedirect();
    expect($offer->fresh()->condition)->toBe('updated');
    $this->post(route('romantausch.store-request'), $this->data())->assertRedirect();
    $request = BookRequest::firstOrFail();
    $this->put(route('romantausch.update-request', $request), $this->data(['condition' => 'updated']))->assertRedirect();
    expect($request->fresh()->condition)->toBe('updated');
    $this->delete(route('romantausch.delete-offer', $offer))->assertRedirect()->assertSessionHas('success', 'Angebot gelöscht.');
    $this->delete(route('romantausch.delete-request', $request))->assertRedirect()->assertSessionHas('success', 'Gesuch gelöscht.');
    $this->post(route('romantausch.store-bundle-offer'), $this->data(['book_numbers' => '1-2']))->assertRedirect();
    $bundleId = BookOffer::firstOrFail()->bundle_id;
    $this->put(route('romantausch.update-bundle', $bundleId), $this->data(['book_numbers' => '2-3']))->assertRedirect();
    expect(BookOffer::orderBy('book_number')->pluck('book_number')->all())->toBe([2, 3]);
    $this->delete(route('romantausch.delete-bundle', $bundleId))->assertRedirect()->assertSessionHas('success', 'Stapel-Angebot gelöscht.');
    [$offer, $request] = $this->pair($actor);
    $swap = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
    $this->post(route('romantausch.confirm-swap', $swap))->assertRedirect();
    $this->post(route('romantausch.complete-swap', [$offer, $request]))->assertRedirect();
    expect($swap->fresh()->completed_at)->not->toBeNull();
});
test('bundle rechecks state and retains its saved series', function (): void {
    $actor = $this->actingMember();
    $bundle = app(BundleWorkflow::class)->create($actor, $this->data(['book_numbers' => '1-2']));
    $form = Livewire::test(RomantauschBundleForm::class, ['bundleId' => $bundle->details['bundle_id']]);
    $bundle->model->update(['completed' => true]);
    $form->set('book_numbers', '2-3')->call('save')->assertHasErrors('book_numbers');
    expect(BookOffer::orderBy('book_number')->pluck('book_number')->all())->toBe([1, 2]);
    $bundle->model->update(['completed' => false]);
    $form->set('series', BookType::MissionMars->value)->set('book_numbers', '2-3')->call('save')->assertHasNoErrors();
    expect(BookOffer::pluck('series')->unique()->values()->all())->toBe([BookType::MaddraxDieDunkleZukunftDerErde->value]);
});
test('single deletion of completed source keeps historical points', function (): void {
    $manager = $this->actingVorstand();
    [$offer, $request] = $this->pair();
    app(SwapWorkflow::class)->complete($manager, $offer->id, $request->id);
    app(OfferWorkflow::class)->delete($offer->user, $offer->id);
    $this->assertDatabaseCount('user_points', 2);
    $this->assertDatabaseCount('book_swaps', 0);
});
test('bundle rejects reversed condition range without writing', function (): void {
    $this->actingMember();
    $this->postJson(route('romantausch.store-bundle-offer'), $this->data(['book_numbers' => '1-2', 'condition' => 'Z2', 'condition_max' => 'Z0']))->assertUnprocessable()->assertJsonValidationErrors('condition_max');
    $this->assertDatabaseCount('book_offers', 0);
});
test('completed unassigned source cannot be completed again', function (): void {
    $this->actingVorstand();
    [$offer, $request] = $this->pair();
    $offer->update(['completed' => true]);
    $this->postJson(route('romantausch.complete-swap', [$offer, $request]))->assertUnprocessable()->assertJsonValidationErrors('swap');
    $this->assertDatabaseCount('user_points', 0);
});
test('existing photos count towards the limit for http and livewire', function (): void {
    $actor = $this->actingMember();
    [$offer] = $this->pair($actor);
    $offer->update(['photos' => ['one.jpg', 'two.jpg', 'three.jpg']]);
    $file = UploadedFile::fake()->image('four.jpg');
    $this->putJson(route('romantausch.update-offer', $offer), $this->data(['photos' => [$file]]))->assertUnprocessable()->assertJsonValidationErrors('photos');
    Livewire::test(RomantauschOfferForm::class, ['offer' => $offer])->set('photos', [$file])->call('save')->assertHasErrors('photos');
    expect($offer->fresh()->photos)->toHaveCount(3)
        ->and(Storage::disk('public')->allFiles('book-offers'))->toBe([]);
});
test('matching failure is also displayed after livewire save', function (): void {
    $this->actingMember();
    $this->mock(SwapMatchingService::class, fn ($mock) => $mock->shouldReceive('matchSwap')->once()->andThrow(new RuntimeException('matching failed')));
    Livewire::test(RomantauschRequestForm::class)->set('book_number', 1)->call('save')->assertRedirect(route('romantausch.index'));
    expect(session('warning'))->not->toBeNull();
    $this->assertDatabaseCount('book_requests', 1);
});
test('bundle candidate failure preserves all committed sources', function (): void {
    $actor = $this->actingMember();
    BookRequest::retrieved(fn () => throw new RuntimeException('candidate query failed'));
    $other = $this->createUserWithRole(Role::Mitglied);
    BookRequest::create($this->data() + ['book_title' => 'Roman 1', 'user_id' => $other->id]);
    $result = app(BundleWorkflow::class)->create($actor, $this->data(['book_numbers' => '1-2']));
    expect($result->matchingFailed)->toBeTrue();
    $this->assertDatabaseCount('book_offers', 2);
    $this->assertDatabaseCount('user_points', 1);
});
test('duplicate match attempt and mail failure do not duplicate swaps', function (): void {
    [$offer, $request] = $this->pair();
    BookOffer::create($this->data(['book_number' => 2]) + ['book_title' => 'Roman 2', 'user_id' => $request->user_id]);
    BookRequest::create($this->data(['book_number' => 2]) + ['book_title' => 'Roman 2', 'user_id' => $offer->user_id]);
    Mail::shouldReceive('to')->twice()->andThrow(new RuntimeException('mail queue unavailable'));
    $service = app(SwapMatchingService::class);
    $service->matchSwap($offer, 'offer');
    $service->matchSwap($offer, 'offer');
    $this->assertDatabaseCount('book_swaps', 2);
});
test('deleted source is ignored by a later matching attempt', function (): void {
    [$offer] = $this->pair();
    $offer->delete();
    app(SwapMatchingService::class)->matchSwap($offer, 'offer');
    $this->assertDatabaseCount('book_swaps', 0);
});
test('reward panel uses current rules and disabled rule text', function (): void {
    $this->actingMember();
    BaxxEarningRule::where('action_key', 'romantausch_swap_complete')->update(['points' => 9, 'is_active' => true]);
    Livewire::test(RomantauschIndex::class)->assertSeeText('9 Baxx pro Auslöser')->assertDontSeeText('Für jeden abgeschlossenen Tausch gibt es 2 Baxx');
    BaxxEarningRule::where('action_key', 'romantausch_swap_complete')->update(['is_active' => false]);
    Livewire::test(RomantauschIndex::class)->assertSeeText('Derzeit keine Baxx-Vergabe.');
});
test('many requests from one owner do not repeat inventory queries', function (): void {
    [$offer, $request] = $this->pair();
    foreach (range(1, 19) as $index) {
        BookRequest::create($this->data() + ['book_title' => 'Roman 1', 'user_id' => $request->user_id]);
    }
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });
    app(SwapMatchingService::class)->matchSwap($offer, 'offer');
    expect($queries)->toBeLessThanOrEqual(8);
    $this->assertDatabaseCount('book_swaps', 0);
});
