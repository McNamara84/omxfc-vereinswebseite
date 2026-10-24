<?php

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
use App\Models\User;
use App\Services\Romantausch\SwapMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class RomantauschWorkflowTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Mail::fake();
        foreach (range(1, 4) as $number) {
            Book::create(['roman_number' => $number, 'title' => "Roman {$number}", 'author' => 'Autor', 'type' => BookType::MaddraxDieDunkleZukunftDerErde]);
        }
        BaxxEarningRule::whereIn('action_key', ['romantausch_offer', 'romantausch_request', 'romantausch_swap_complete'])
            ->update(['points' => 5, 'every_count' => 1, 'is_active' => true]);
    }

    private function data(array $extra = []): array
    {
        return $extra + ['series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'condition' => 'Z0'];
    }

    private function pair(?User $owner = null): array
    {
        $owner ??= $this->createUserWithRole(Role::Mitglied);
        $other = $this->createUserWithRole(Role::Mitglied);
        $data = $this->data() + ['book_title' => 'Roman 1'];

        return [BookOffer::create($data + ['user_id' => $owner->id]), BookRequest::create($data + ['user_id' => $other->id])];
    }

    public static function staleForms(): array
    {
        return [['offer', false], ['offer', true], ['request', false], ['request', true]];
    }

    #[DataProvider('staleForms')]
    public function test_opening_a_blocked_form_redirects_with_an_error(string $kind, bool $matched): void
    {
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

        Livewire::test($kind === 'offer' ? RomantauschOfferForm::class : RomantauschRequestForm::class, [
            $kind === 'offer' ? 'offer' : 'bookRequest' => $source,
        ])->assertRedirect(route('romantausch.index'));

        $this->assertStringContainsString('nicht bearbeitet werden', session('error'));
        $this->assertSame('Z0', $source->fresh()->condition);
        $this->assertDatabaseCount('user_points', 0);
    }

    public static function lazyComponents(): array
    {
        return [[RomantauschOfferForm::class], [RomantauschRequestForm::class], [RomantauschIndex::class]];
    }

    #[DataProvider('lazyComponents')]
    public function test_lazy_components_show_a_skeleton_until_loaded(string $component): void
    {
        $this->actingMember();

        Livewire::test($component, ['lazy' => true])
            ->assertSeeHtml('aria-hidden="true"')
            ->assertSeeHtml('skeleton')
            ->assertDontSeeHtml('wire:submit="save"');
    }

    public function test_guest_has_no_personal_offers_or_requests(): void
    {
        $this->pair();
        $this->get(route('romantausch.index'))->assertRedirect(route('login'));

        $component = app(RomantauschIndex::class);

        $this->assertTrue($component->ownOffers->isEmpty());
        $this->assertTrue($component->ownRequests->isEmpty());
    }

    public function test_advertised_series_are_selectable_and_accepted_by_the_http_adapter(): void
    {
        $actor = $this->actingMember();
        $types = StoreBookOfferRequest::getAllowedTypes();
        $this->assertNotEmpty($types);
        Livewire::test(RomantauschOfferForm::class)->assertSet('types', $types);
        Livewire::test(RomantauschRequestForm::class)->assertSet('types', $types);

        foreach ($types as $type) {
            $book = Book::firstOrCreate(['type' => $type, 'roman_number' => 1], ['title' => 'Roman 1', 'author' => 'Autor']);
            $this->post(route('romantausch.store-offer'), $this->data(['series' => $type->value]))
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('romantausch.index'));
            $this->assertDatabaseHas('book_offers', ['user_id' => $actor->id, 'series' => $type->value, 'book_number' => $book->roman_number]);
        }
    }

    #[DataProvider('staleForms')]
    public function test_form_rechecks_state_when_saving(string $kind, bool $matched): void
    {
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
        $this->assertSame('Z0', ($kind === 'offer' ? $offer : $request)->fresh()->condition);
        $this->assertDatabaseCount('user_points', 0);
    }

    public static function roles(): array
    {
        $cases = [];
        foreach ([Role::Mitglied, Role::Anwaerter, Role::Kassenwart, Role::Vorstand, Role::Admin] as $role) {
            foreach ([false, true] as $http) {
                $cases[$role->name.($http ? '-http' : '-livewire')] = [$role, $http];
            }
        }

        return $cases;
    }

    #[DataProvider('roles')]
    public function test_manual_completion_requires_club_management(Role $role, bool $http): void
    {
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
    }

    public function test_management_role_in_other_team_does_not_grant_manual_completion(): void
    {
        $actor = $this->actingMember();
        $team = Team::factory()->create();
        $team->users()->attach($actor, ['role' => Role::Admin->value]);
        $actor->forceFill(['current_team_id' => $team->id])->save();
        [$offer, $request] = $this->pair();
        $this->post(route('romantausch.complete-swap', [$offer, $request]))->assertForbidden();
        Livewire::test(RomantauschIndex::class)->call('completeSwap', $offer->id, $request->id)->assertForbidden();
        $this->assertDatabaseCount('book_swaps', 0);
    }

    public function test_club_manager_can_complete_with_another_current_team(): void
    {
        $actor = $this->actingVorstand();
        $actor->forceFill(['current_team_id' => Team::factory()->create()->id])->save();
        [$offer, $request] = $this->pair();
        $this->post(route('romantausch.complete-swap', [$offer, $request]))->assertRedirect(route('romantausch.index'));
        $this->assertDatabaseCount('user_points', 2);
    }

    public function test_outsider_cannot_confirm_through_either_adapter(): void
    {
        $this->actingMember();
        [$offer, $request] = $this->pair();
        $swap = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
        $this->post(route('romantausch.confirm-swap', $swap))->assertForbidden();
        Livewire::test(RomantauschIndex::class)->call('confirmSwap', $swap->id)->assertForbidden();
        $this->assertFalse($swap->fresh()->offer_confirmed);
        $this->assertFalse($swap->fresh()->request_confirmed);
        $this->assertDatabaseCount('user_points', 0);
    }

    public function test_completion_is_idempotent_across_both_adapters_and_confirmation(): void
    {
        $manager = $this->actingVorstand();
        [$offer, $request] = $this->pair();
        $this->post(route('romantausch.complete-swap', [$offer, $request]))->assertSessionHas('success', 'Tausch abgeschlossen! Beide erhalten 5 Baxx.');
        Livewire::test(RomantauschIndex::class)->call('completeSwap', $offer->id, $request->id)->assertDispatched('toast', type: 'success', title: 'Dieser Tausch wurde bereits abgeschlossen.');
        $swap = BookSwap::firstOrFail();
        $result = app(SwapWorkflow::class)->confirm($offer->user, $swap->id);
        $this->assertTrue($result->alreadyCompleted);
        $this->assertSame([], $result->awards);
        $this->assertDatabaseCount('book_swaps', 1);
        $this->assertDatabaseCount('user_points', 2);
        $this->assertSame(1, Activity::where('action', 'swap_completed')->count());
    }

    public function test_existing_match_is_completed_instead_of_duplicated(): void
    {
        $manager = $this->actingVorstand();
        [$offer, $request] = $this->pair();
        $swap = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
        $result = app(SwapWorkflow::class)->complete($manager, $offer->id, $request->id);
        $this->assertSame($swap->id, $result->swap->id);
        $this->assertDatabaseCount('book_swaps', 1);
    }

    public function test_wrong_pair_and_already_assigned_source_are_rejected(): void
    {
        $manager = $this->actingVorstand();
        [$offer, $request] = $this->pair();
        [, $otherRequest] = $this->pair();
        BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
        $this->postJson(route('romantausch.complete-swap', [$offer, $otherRequest]))->assertUnprocessable()->assertJsonValidationErrors('swap');
        $otherRequest->update(['book_number' => 2]);
        $this->postJson(route('romantausch.complete-swap', [$offer, $otherRequest]))->assertUnprocessable()->assertJsonValidationErrors('swap');
        $this->assertDatabaseCount('user_points', 0);
    }

    public function test_disabled_rule_never_promises_a_fixed_reward(): void
    {
        BaxxEarningRule::where('action_key', 'romantausch_swap_complete')->update(['is_active' => false]);
        [$offer, $request] = $this->pair();
        $swap = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
        app(SwapWorkflow::class)->confirm($offer->user, $swap->id);
        $result = app(SwapWorkflow::class)->confirm($request->user, $swap->id);
        $this->assertSame('Tausch abgeschlossen. Es wurden keine Baxx vergeben.', $result->message());
        $this->assertDatabaseCount('user_points', 0);
    }

    public function test_update_failure_preserves_old_photos_and_removes_new_upload(): void
    {
        $actor = $this->actingMember();
        [$offer] = $this->pair($actor);
        Storage::disk('public')->put('book-offers/old.jpg', 'old');
        $offer->update(['photos' => ['book-offers/old.jpg']]);
        BookOffer::updating(fn () => throw new RuntimeException('DB update failed'));
        $this->put(route('romantausch.update-offer', $offer), $this->data(['remove_photos' => ['book-offers/old.jpg'], 'photos' => [UploadedFile::fake()->image('new.jpg')]]))->assertSessionHas('error');
        $this->assertSame(['book-offers/old.jpg'], $offer->fresh()->photos);
        $this->assertSame(['book-offers/old.jpg'], Storage::disk('public')->allFiles('book-offers'));
    }

    public function test_failed_bundle_activity_rolls_back_offers_points_and_uploads(): void
    {
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
        $this->assertSame([], Storage::disk('public')->allFiles('book-offers'));
        Mail::assertNothingQueued();
    }

    public function test_matching_failure_keeps_committed_source_and_photos(): void
    {
        $actor = $this->actingMember();
        $this->mock(SwapMatchingService::class, fn ($mock) => $mock->shouldReceive('matchSwap')->once()->andThrow(new RuntimeException('matching failed')));
        $this->post(route('romantausch.store-offer'), $this->data(['photos' => [UploadedFile::fake()->image('new.jpg')]]))
            ->assertRedirect(route('romantausch.index'))->assertSessionHas('success')->assertSessionHas('warning');
        $offer = BookOffer::firstOrFail();
        Storage::disk('public')->assertExists($offer->photos[0]);
        $this->assertDatabaseCount('user_points', 1);
        $this->assertSame(1, Activity::where('subject_type', BookOffer::class)->count());
    }

    public function test_outer_rollback_removes_uploaded_files_and_never_runs_matching(): void
    {
        $actor = $this->actingMember();
        $this->mock(SwapMatchingService::class, fn ($mock) => $mock->shouldNotReceive('matchSwap'));
        DB::beginTransaction();
        $result = app(OfferWorkflow::class)->create($actor, $this->data(['photos' => [UploadedFile::fake()->image('new.jpg')]]));
        Storage::disk('public')->assertExists($result->model->photos[0]);
        DB::rollBack();
        $this->assertDatabaseCount('book_offers', 0);
        $this->assertDatabaseCount('user_points', 0);
        $this->assertSame([], Storage::disk('public')->allFiles('book-offers'));
        Mail::assertNothingQueued();
    }

    public function test_shared_photo_survives_until_its_last_source_is_deleted(): void
    {
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
    }

    public function test_all_legacy_write_routes_remain_functional(): void
    {
        $actor = $this->actingVorstand();
        $this->post(route('romantausch.store-offer'), $this->data())->assertRedirect();
        $offer = BookOffer::firstOrFail();
        $this->put(route('romantausch.update-offer', $offer), $this->data(['condition' => 'updated']))->assertRedirect();
        $this->assertSame('updated', $offer->fresh()->condition);
        $this->post(route('romantausch.store-request'), $this->data())->assertRedirect();
        $request = BookRequest::firstOrFail();
        $this->put(route('romantausch.update-request', $request), $this->data(['condition' => 'updated']))->assertRedirect();
        $this->assertSame('updated', $request->fresh()->condition);
        $this->delete(route('romantausch.delete-offer', $offer))->assertRedirect()->assertSessionHas('success', 'Angebot gelöscht.');
        $this->delete(route('romantausch.delete-request', $request))->assertRedirect()->assertSessionHas('success', 'Gesuch gelöscht.');
        $this->post(route('romantausch.store-bundle-offer'), $this->data(['book_numbers' => '1-2']))->assertRedirect();
        $bundleId = BookOffer::firstOrFail()->bundle_id;
        $this->put(route('romantausch.update-bundle', $bundleId), $this->data(['book_numbers' => '2-3']))->assertRedirect();
        $this->assertSame([2, 3], BookOffer::orderBy('book_number')->pluck('book_number')->all());
        $this->delete(route('romantausch.delete-bundle', $bundleId))->assertRedirect()->assertSessionHas('success', 'Stapel-Angebot gelöscht.');
        [$offer, $request] = $this->pair($actor);
        $swap = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
        $this->post(route('romantausch.confirm-swap', $swap))->assertRedirect();
        $this->post(route('romantausch.complete-swap', [$offer, $request]))->assertRedirect();
        $this->assertNotNull($swap->fresh()->completed_at);
    }

    public function test_bundle_rechecks_state_and_retains_its_saved_series(): void
    {
        $actor = $this->actingMember();
        $bundle = app(BundleWorkflow::class)->create($actor, $this->data(['book_numbers' => '1-2']));
        $form = Livewire::test(RomantauschBundleForm::class, ['bundleId' => $bundle->details['bundle_id']]);
        $bundle->model->update(['completed' => true]);
        $form->set('book_numbers', '2-3')->call('save')->assertHasErrors('book_numbers');
        $this->assertSame([1, 2], BookOffer::orderBy('book_number')->pluck('book_number')->all());
        $bundle->model->update(['completed' => false]);
        $form->set('series', BookType::MissionMars->value)->set('book_numbers', '2-3')->call('save')->assertHasNoErrors();
        $this->assertSame([BookType::MaddraxDieDunkleZukunftDerErde->value], BookOffer::pluck('series')->unique()->values()->all());
    }

    public function test_single_deletion_of_completed_source_keeps_historical_points(): void
    {
        $manager = $this->actingVorstand();
        [$offer, $request] = $this->pair();
        app(SwapWorkflow::class)->complete($manager, $offer->id, $request->id);
        app(OfferWorkflow::class)->delete($offer->user, $offer->id);
        $this->assertDatabaseCount('user_points', 2);
        $this->assertDatabaseCount('book_swaps', 0);
    }

    public function test_bundle_rejects_reversed_condition_range_without_writing(): void
    {
        $this->actingMember();
        $this->postJson(route('romantausch.store-bundle-offer'), $this->data(['book_numbers' => '1-2', 'condition' => 'Z2', 'condition_max' => 'Z0']))
            ->assertUnprocessable()->assertJsonValidationErrors('condition_max');
        $this->assertDatabaseCount('book_offers', 0);
    }

    public function test_completed_unassigned_source_cannot_be_completed_again(): void
    {
        $this->actingVorstand();
        [$offer, $request] = $this->pair();
        $offer->update(['completed' => true]);
        $this->postJson(route('romantausch.complete-swap', [$offer, $request]))->assertUnprocessable()->assertJsonValidationErrors('swap');
        $this->assertDatabaseCount('user_points', 0);
    }

    public function test_existing_photos_count_towards_the_limit_for_http_and_livewire(): void
    {
        $actor = $this->actingMember();
        [$offer] = $this->pair($actor);
        $offer->update(['photos' => ['one.jpg', 'two.jpg', 'three.jpg']]);
        $file = UploadedFile::fake()->image('four.jpg');
        $this->putJson(route('romantausch.update-offer', $offer), $this->data(['photos' => [$file]]))->assertUnprocessable()->assertJsonValidationErrors('photos');
        Livewire::test(RomantauschOfferForm::class, ['offer' => $offer])->set('photos', [$file])->call('save')->assertHasErrors('photos');
        $this->assertCount(3, $offer->fresh()->photos);
        $this->assertSame([], Storage::disk('public')->allFiles('book-offers'));
    }

    public function test_matching_failure_is_also_displayed_after_livewire_save(): void
    {
        $this->actingMember();
        $this->mock(SwapMatchingService::class, fn ($mock) => $mock->shouldReceive('matchSwap')->once()->andThrow(new RuntimeException('matching failed')));
        Livewire::test(RomantauschRequestForm::class)->set('book_number', 1)->call('save')->assertRedirect(route('romantausch.index'));
        $this->assertNotNull(session('warning'));
        $this->assertDatabaseCount('book_requests', 1);
    }

    public function test_bundle_candidate_failure_preserves_all_committed_sources(): void
    {
        $actor = $this->actingMember();
        BookRequest::retrieved(fn () => throw new RuntimeException('candidate query failed'));
        $other = $this->createUserWithRole(Role::Mitglied);
        BookRequest::create($this->data() + ['book_title' => 'Roman 1', 'user_id' => $other->id]);
        $result = app(BundleWorkflow::class)->create($actor, $this->data(['book_numbers' => '1-2']));
        $this->assertTrue($result->matchingFailed);
        $this->assertDatabaseCount('book_offers', 2);
        $this->assertDatabaseCount('user_points', 1);
    }

    public function test_duplicate_match_attempt_and_mail_failure_do_not_duplicate_swaps(): void
    {
        [$offer, $request] = $this->pair();
        BookOffer::create($this->data(['book_number' => 2]) + ['book_title' => 'Roman 2', 'user_id' => $request->user_id]);
        BookRequest::create($this->data(['book_number' => 2]) + ['book_title' => 'Roman 2', 'user_id' => $offer->user_id]);
        Mail::shouldReceive('to')->twice()->andThrow(new RuntimeException('mail queue unavailable'));
        $service = app(SwapMatchingService::class);
        $service->matchSwap($offer, 'offer');
        $service->matchSwap($offer, 'offer');
        $this->assertDatabaseCount('book_swaps', 2);
    }

    public function test_deleted_source_is_ignored_by_a_later_matching_attempt(): void
    {
        [$offer] = $this->pair();
        $offer->delete();
        app(SwapMatchingService::class)->matchSwap($offer, 'offer');
        $this->assertDatabaseCount('book_swaps', 0);
    }

    public function test_reward_panel_uses_current_rules_and_disabled_rule_text(): void
    {
        $this->actingMember();
        BaxxEarningRule::where('action_key', 'romantausch_swap_complete')->update(['points' => 9, 'is_active' => true]);
        Livewire::test(RomantauschIndex::class)->assertSeeText('9 Baxx pro Auslöser')->assertDontSeeText('Für jeden abgeschlossenen Tausch gibt es 2 Baxx');
        BaxxEarningRule::where('action_key', 'romantausch_swap_complete')->update(['is_active' => false]);
        Livewire::test(RomantauschIndex::class)->assertSeeText('Derzeit keine Baxx-Vergabe.');
    }

    public function test_many_requests_from_one_owner_do_not_repeat_inventory_queries(): void
    {
        [$offer, $request] = $this->pair();
        foreach (range(1, 19) as $index) {
            BookRequest::create($this->data() + ['book_title' => 'Roman 1', 'user_id' => $request->user_id]);
        }
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        app(SwapMatchingService::class)->matchSwap($offer, 'offer');
        $this->assertLessThanOrEqual(8, $queries);
        $this->assertDatabaseCount('book_swaps', 0);
    }
}
