<?php

namespace Tests\Feature;

use App\Enums\BookType;
use App\Enums\Role;
use App\Livewire\DashboardActivityFeed;
use App\Livewire\RomantauschBundleForm;
use App\Livewire\RomantauschIndex;
use App\Models\Activity;
use App\Models\Book;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\User;
use App\Services\Romantausch\BookPhotoService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class RomantauschReviewRegressionTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Mail::fake();
        foreach ([1, 2] as $number) {
            Book::create(['roman_number' => $number, 'title' => "Roman{$number}", 'author' => 'Autor', 'type' => BookType::MaddraxDieDunkleZukunftDerErde]);
        }
    }

    private function data(User $owner, array $extra = []): array
    {
        return $extra + ['user_id' => $owner->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'Z0'];
    }

    public function test_bundle_form_displays_and_can_remove_the_entire_photo_union(): void
    {
        $owner = $this->actingMember();
        $paths = ['book-offers/shared.jpg', 'book-offers/first.jpg', 'book-offers/second.jpg', 'book-offers/third.jpg'];
        foreach ($paths as $path) {
            Storage::disk('public')->put($path, 'photo');
        }
        $first = BookOffer::create($this->data($owner, ['bundle_id' => 'review-bundle', 'photos' => array_slice($paths, 0, 2)]));
        $second = BookOffer::create($this->data($owner, ['bundle_id' => 'review-bundle', 'book_number' => 2, 'photos' => [$paths[0], $paths[2], $paths[3]]]));
        BookOffer::create($this->data($this->createUserWithRole(Role::Mitglied), ['bundle_id' => 'review-bundle', 'photos' => ['book-offers/foreign.jpg']]));
        BookOffer::create($this->data($owner, ['bundle_id' => 'other-bundle', 'photos' => ['book-offers/other-bundle.jpg']]));

        $form = Livewire::test(RomantauschBundleForm::class, ['bundleId' => 'review-bundle'])
            ->assertSet('existingPhotos', $paths)
            ->assertSet('keptPhotosCount', 4)
            ->assertSet('maxNewPhotos', 0)
            ->assertDontSeeHtml('value="book-offers/foreign.jpg"')
            ->assertDontSeeHtml('value="book-offers/other-bundle.jpg"')
            ->assertSeeHtml('wire:model.live="remove_photos"');
        foreach ($paths as $path) {
            $form->assertSeeHtml('src="'.Storage::disk('public')->url($path).'"')
                ->assertSeeHtml('value="'.$path.'"');
        }
        $this->assertSame(1, substr_count($form->html(), 'value="'.$paths[0].'"'));
        $form->set('condition', 'Z1')->call('save')->assertHasErrors('photos');
        $form->set('remove_photos', [$paths[2], $paths[3]])
            ->assertSet('keptPhotosCount', 2)
            ->assertSet('maxNewPhotos', 1)
            ->assertSeeText('bis zu 1 neue Fotos')
            ->set('photos', [UploadedFile::fake()->image('replacement.jpg')])
            ->call('save')->assertHasNoErrors()->assertRedirect(route('romantausch.index'));

        $photos = $first->fresh()->photos;
        $this->assertCount(3, $photos);
        $this->assertSame($photos, $second->fresh()->photos);
        $this->assertSame(array_slice($paths, 0, 2), array_slice($photos, 0, 2));
        $this->assertSame('Z1', $second->fresh()->condition);
        Storage::disk('public')->assertExists($photos);
        Storage::disk('public')->assertMissing([$paths[2], $paths[3]]);
        $this->assertDatabaseCount('user_points', 0);
    }

    public function test_condition_only_bundle_edit_preserves_different_photos_within_the_limit(): void
    {
        $owner = $this->actingMember();
        $paths = ['book-offers/first.jpg', 'book-offers/second.jpg', 'book-offers/third.jpg'];
        $first = BookOffer::create($this->data($owner, ['bundle_id' => 'review-bundle', 'photos' => [$paths[0]]]));
        $second = BookOffer::create($this->data($owner, ['bundle_id' => 'review-bundle', 'book_number' => 2, 'photos' => array_slice($paths, 1)]));

        Livewire::test(RomantauschBundleForm::class, ['bundleId' => 'review-bundle'])
            ->assertSet('existingPhotos', $paths)
            ->assertSet('maxNewPhotos', 0)
            ->set('condition', 'Z1')->call('save')->assertHasNoErrors()->assertRedirect(route('romantausch.index'));

        $this->assertSame($paths, $first->fresh()->photos);
        $this->assertSame($paths, $second->fresh()->photos);
    }

    public static function missingSources(): array
    {
        return [['offer', false], ['request', false], ['offer', true], ['request', true]];
    }

    #[DataProvider('missingSources')]
    public function test_confirmation_returns_not_found_when_a_source_disappears_before_eager_loading(string $kind, bool $http): void
    {
        $owner = $this->actingMember();
        $offer = BookOffer::create($this->data($owner));
        $request = BookRequest::create($this->data($this->createUserWithRole(Role::Mitglied)));
        $swap = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
        $form = $http ? null : Livewire::test(RomantauschIndex::class);
        $source = $kind === 'offer' ? $offer : $request;
        $reads = 0;
        // HTTP route binding reads the swap once before the workflow does.
        $deleteAfterRead = $http ? 2 : 1;
        BookSwap::retrieved(function (BookSwap $loaded) use ($swap, $source, $deleteAfterRead, &$reads): void {
            if ($loaded->id === $swap->id && ++$reads === $deleteAfterRead) {
                DB::table($source->getTable())->where('id', $source->id)->delete();
            }
        });

        if ($http) {
            $this->post(route('romantausch.confirm-swap', $swap))->assertNotFound();
        } else {
            $form->call('confirmSwap', $swap->id)->assertNotFound();
        }

        $this->assertGreaterThanOrEqual($deleteAfterRead, $reads);
        $this->assertDatabaseCount('book_swaps', 0);
        $this->assertDatabaseCount('user_points', 0);
        $this->assertSame(0, Activity::where('action', 'swap_completed')->count());
    }

    public static function historySizes(): array
    {
        return [[0], [200]];
    }

    #[DataProvider('historySizes')]
    public function test_photo_cleanup_checks_exact_references_without_loading_the_offer_history(int $historySize): void
    {
        $owner = $this->actingMember();
        $removed = 'book-offers/remove "quoted".jpg';
        $shared = 'book-offers/shared "quoted".jpg';
        $unreferenced = 'book-offers/unreferenced.jpg';
        foreach ([$removed, $shared, $unreferenced] as $path) {
            Storage::disk('public')->put($path, 'photo');
        }
        BookOffer::create($this->data($this->createUserWithRole(Role::Mitglied), ['completed' => true, 'photos' => [$shared, $removed.'.backup']]));
        if ($historySize > 0) {
            DB::table('book_offers')->insert(array_fill(0, $historySize, $this->data($owner, ['completed' => true, 'photos' => json_encode(['book-offers/history.jpg'])])));
        }
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_starts_with(strtolower($query->sql), 'select') && str_contains($query->sql, 'book_offers')) {
                $queries[] = $query->sql;
            }
        });

        DB::transaction(fn () => app(BookPhotoService::class)->removeUnreferencedAfterCommit([$removed, $shared, $unreferenced, $removed]));

        Storage::disk('public')->assertMissing([$removed, $unreferenced]);
        Storage::disk('public')->assertExists($shared);
        $this->assertNotEmpty($queries);
        $this->assertLessThanOrEqual(3, count($queries));
        foreach ($queries as $sql) {
            $this->assertStringContainsString('select exists', strtolower($sql));
        }
    }

    public static function cancelledSources(): array
    {
        return [['offer', false], ['request', false], ['bundle', false], ['offer', true], ['request', true], ['bundle', true]];
    }

    #[DataProvider('cancelledSources')]
    public function test_deleting_a_matched_source_displays_a_cancellation_in_the_dashboard(string $kind, bool $http): void
    {
        $owner = $this->actingMember();
        $other = $this->createUserWithRole(Role::Mitglied);
        $offer = BookOffer::create($this->data($owner, ['bundle_id' => $kind === 'bundle' ? 'review-bundle' : null]));
        $request = BookRequest::create($this->data($other));
        BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
        $this->actingAs($kind === 'request' ? $other : $owner);
        $id = match ($kind) {
            'offer' => $offer->id,
            'request' => $request->id,
            'bundle' => $offer->bundle_id,
        };
        if ($http) {
            $this->delete(route('romantausch.delete-'.$kind, $id))->assertRedirect();
        } else {
            Livewire::test(RomantauschIndex::class)->call('delete'.ucfirst($kind), $id)->assertSuccessful();
        }
        $affected = $kind === 'request' ? $offer : $request;
        $this->assertDatabaseHas('activities', ['subject_type' => $affected::class, 'subject_id' => $affected->id,
            'action' => $kind === 'request' ? 'match_cancelled_by_request_owner' : 'match_cancelled_by_offer_owner']);
        $this->assertDatabaseCount('book_swaps', 0);
        $this->actingAs($affected->user);

        Livewire::test(DashboardActivityFeed::class)
            ->assertSeeText('Tauschzuordnung aufgehoben: Roman1')
            ->assertSeeText($kind === 'request' ? 'Das zugeordnete Gesuch wurde entfernt.' : 'Das zugeordnete Angebot wurde entfernt.')
            ->assertDontSeeText('Neues Angebot:')
            ->assertDontSeeText('Neues Gesuch:');
    }
}
