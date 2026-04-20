<?php

namespace Tests\Feature;

use App\Enums\BookType;
use App\Livewire\RomantauschBundleForm;
use App\Livewire\RomantauschIndex;
use App\Models\Book;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\User;
use App\Services\Romantausch\BundleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class BundleOfferTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    /**
     * Erstellt Test-Buchdaten für Maddrax-Serie (1-100) und Mission Mars (1).
     *
     * HINWEIS: Diese Methode verwendet Book::create() ohne exists()-Check.
     * Das ist akzeptabel weil:
     * 1. RefreshDatabase-Trait migriert die DB vor jedem Test neu
     * 2. Jeder Test startet mit leerer Datenbank
     * 3. putBookData() wird nur einmal pro Test aufgerufen
     *
     * Falls Tests ohne RefreshDatabase laufen, würden Duplicate-Key-Errors auftreten.
     */
    private function putBookData(): void
    {
        for ($i = 1; $i <= 100; $i++) {
            Book::create([
                'roman_number' => $i,
                'title' => "Maddrax {$i}",
                'author' => 'Author',
                'type' => BookType::MaddraxDieDunkleZukunftDerErde,
            ]);
        }

        Book::create([
            'roman_number' => 1,
            'title' => 'Mission Mars 1',
            'author' => 'Author',
            'type' => BookType::MissionMars,
        ]);
    }

    // ====== parseBookNumbers Tests ======

    public function test_parse_book_numbers_single_number(): void
    {
        $service = app(BundleService::class);
        $result = $service->parseBookNumbers('5');
        $this->assertArraysAreEqual([5], $result);
    }

    public function test_parse_book_numbers_multiple_single_numbers(): void
    {
        $service = app(BundleService::class);
        $result = $service->parseBookNumbers('1, 3, 5, 7');
        $this->assertArraysAreEqual([1, 3, 5, 7], $result);
    }

    public function test_parse_book_numbers_range(): void
    {
        $service = app(BundleService::class);
        $result = $service->parseBookNumbers('1-5');
        $this->assertArraysAreEqual([1, 2, 3, 4, 5], $result);
    }

    public function test_parse_book_numbers_mixed_ranges_and_singles(): void
    {
        $service = app(BundleService::class);
        $result = $service->parseBookNumbers('1-5, 10, 15-17');
        $this->assertArraysAreEqual([1, 2, 3, 4, 5, 10, 15, 16, 17], $result);
    }

    public function test_parse_book_numbers_removes_duplicates(): void
    {
        $service = app(BundleService::class);
        $result = $service->parseBookNumbers('1-5, 3, 4, 5');
        $this->assertArraysAreEqual([1, 2, 3, 4, 5], $result);
    }

    public function test_parse_book_numbers_ignores_invalid_input(): void
    {
        $service = app(BundleService::class);
        $result = $service->parseBookNumbers('1, abc, 5, xyz');
        $this->assertArraysAreEqual([1, 5], $result);
    }

    public function test_parse_book_numbers_handles_whitespace(): void
    {
        $service = app(BundleService::class);
        $result = $service->parseBookNumbers('  1  ,  3  -  5  ,  10  ');
        $this->assertArraysAreEqual([1, 3, 4, 5, 10], $result);
    }

    public function test_parse_book_numbers_removes_duplicates_and_handles_unsorted(): void
    {
        $service = app(BundleService::class);

        // Input mit echten Duplikaten: 5 erscheint explizit zweimal,
        // Bereich 1-3 überlappt mit einzelnen 2 und 3
        $result = $service->parseBookNumbers('5, 1-3, 2, 3, 5, 10');

        // PHP-Version entfernt Duplikate via array_unique, sortiert aber NICHT.
        // JavaScript-Version sortiert zusätzlich. Dieser Unterschied ist akzeptabel
        // da die Sortierung nur für die UI-Darstellung relevant ist.
        //
        // Kernverhalten das hier getestet wird:
        // 1. Deduplizierung: 7 Input-Werte (5,1,2,3,2,3,5,10) → 5 unique Werte
        // 2. Alle erwarteten Werte sind enthalten
        $this->assertCount(5, $result, 'Erwarte genau 5 eindeutige Werte');

        // Prüfe dass array_unique tatsächlich Duplikate entfernt hat
        $this->assertSame(
            count($result),
            count(array_unique($result)),
            'Ergebnis sollte keine Duplikate enthalten'
        );

        // Prüfe alle erwarteten Werte (ohne Reihenfolge-Annahme)
        $expectedValues = [1, 2, 3, 5, 10];
        foreach ($expectedValues as $expected) {
            $this->assertContains($expected, $result, "Wert {$expected} sollte im Ergebnis sein");
        }
    }

    // ====== Bundle Creation Tests (Livewire) ======

    public function test_store_bundle_creates_entries_with_shared_bundle_id(): void
    {
        $this->putBookData();
        $user = $this->actingMember();

        Storage::fake('public');

        Livewire::test(RomantauschBundleForm::class)
            ->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)
            ->set('book_numbers', '1-5')
            ->set('condition', 'Z1')
            ->set('condition_max', 'Z2')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $offers = BookOffer::where('user_id', $user->id)->get();
        $this->assertCount(5, $offers);

        $bundleId = $offers->first()->bundle_id;
        $this->assertNotNull($bundleId);

        foreach ($offers as $offer) {
            $this->assertEquals($bundleId, $offer->bundle_id);
            $this->assertEquals('Z1', $offer->condition);
            $this->assertEquals('Z2', $offer->condition_max);
        }
    }

    public function test_store_bundle_with_photos(): void
    {
        $this->putBookData();
        $user = $this->actingMember();

        Storage::fake('public');

        Livewire::test(RomantauschBundleForm::class)
            ->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)
            ->set('book_numbers', '1-3')
            ->set('condition', 'Z2')
            ->set('photos', [UploadedFile::fake()->image('test.jpg', 800, 600)])
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $offers = BookOffer::where('user_id', $user->id)->get();
        $this->assertCount(3, $offers);

        // Alle sollten dieselben Fotos haben
        $firstPhotos = $offers->first()->photos;
        $this->assertNotEmpty($firstPhotos);

        foreach ($offers as $offer) {
            $this->assertEquals($firstPhotos, $offer->photos);
        }
    }

    // ====== Bundle Editing Tests (Livewire) ======

    public function test_edit_bundle_page_loads_for_owner(): void
    {
        $this->putBookData();
        $user = $this->actingMember();

        $bundleId = (string) Str::uuid();

        BookOffer::create([
            'user_id' => $user->id,
            'bundle_id' => $bundleId,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Maddrax 1',
            'condition' => 'Z2',
        ]);

        BookOffer::create([
            'user_id' => $user->id,
            'bundle_id' => $bundleId,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 2,
            'book_title' => 'Maddrax 2',
            'condition' => 'Z2',
        ]);

        Livewire::test(RomantauschBundleForm::class, ['bundleId' => $bundleId])
            ->assertOk()
            ->assertSet('bundleId', $bundleId)
            ->assertSet('series', BookType::MaddraxDieDunkleZukunftDerErde->value)
            ->assertSet('condition', 'Z2')
            ->assertSee('Stapel-Angebot bearbeiten');
    }

    public function test_edit_bundle_forbidden_for_non_owner(): void
    {
        $this->putBookData();
        $owner = $this->actingMember();

        $bundleId = (string) Str::uuid();

        BookOffer::create([
            'user_id' => $owner->id,
            'bundle_id' => $bundleId,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Maddrax 1',
            'condition' => 'Z2',
        ]);

        // Wechsle zu anderem User
        $otherUser = $this->actingMember();

        Livewire::test(RomantauschBundleForm::class, ['bundleId' => $bundleId])
            ->assertStatus(404);
    }

    public function test_update_bundle_removes_specified_offers(): void
    {
        $this->putBookData();
        $user = $this->actingMember();

        $bundleId = (string) Str::uuid();

        $offer1 = BookOffer::create([
            'user_id' => $user->id,
            'bundle_id' => $bundleId,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Maddrax 1',
            'condition' => 'Z2',
        ]);

        $offer2 = BookOffer::create([
            'user_id' => $user->id,
            'bundle_id' => $bundleId,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 2,
            'book_title' => 'Maddrax 2',
            'condition' => 'Z2',
        ]);

        $offer3 = BookOffer::create([
            'user_id' => $user->id,
            'bundle_id' => $bundleId,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 3,
            'book_title' => 'Maddrax 3',
            'condition' => 'Z2',
        ]);

        // Aktualisiere auf 2-3 (ohne 1)
        Livewire::test(RomantauschBundleForm::class, ['bundleId' => $bundleId])
            ->set('book_numbers', '2-3')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        // Offer 1 sollte jetzt entfernt sein
        $this->assertDatabaseMissing('book_offers', ['id' => $offer1->id]);
        $this->assertDatabaseHas('book_offers', ['id' => $offer2->id]);
        $this->assertDatabaseHas('book_offers', ['id' => $offer3->id]);
    }

    public function test_update_bundle_blocked_when_active_swaps_exist(): void
    {
        $this->putBookData();
        $user = $this->actingMember();
        $otherUser = User::factory()->create();

        $bundleId = (string) Str::uuid();

        $offer = BookOffer::create([
            'user_id' => $user->id,
            'bundle_id' => $bundleId,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Maddrax 1',
            'condition' => 'Z2',
        ]);

        BookOffer::create([
            'user_id' => $user->id,
            'bundle_id' => $bundleId,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 2,
            'book_title' => 'Maddrax 2',
            'condition' => 'Z2',
        ]);

        $request = BookRequest::create([
            'user_id' => $otherUser->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Maddrax 1',
            'condition' => 'Z2',
        ]);

        BookSwap::create([
            'offer_id' => $offer->id,
            'request_id' => $request->id,
        ]);

        // Livewire-Komponente blockiert Bearbeitung bei aktiven Swaps
        Livewire::test(RomantauschBundleForm::class, ['bundleId' => $bundleId])
            ->assertRedirect(route('romantausch.index'));
    }

    // ====== Bundle Deletion Tests (Livewire) ======

    public function test_delete_bundle_forbidden_for_non_owner(): void
    {
        $this->putBookData();
        $owner = $this->actingMember();

        $bundleId = (string) Str::uuid();

        BookOffer::create([
            'user_id' => $owner->id,
            'bundle_id' => $bundleId,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Maddrax 1',
            'condition' => 'Z2',
        ]);

        // Wechsle zu anderem User
        $otherUser = $this->actingMember();

        Livewire::test(RomantauschIndex::class)
            ->call('deleteBundle', $bundleId)
            ->assertStatus(404);

        // Bundle sollte weiterhin existieren
        $this->assertDatabaseHas('book_offers', ['bundle_id' => $bundleId]);
    }

    public function test_delete_bundle_removes_associated_swaps(): void
    {
        $this->putBookData();
        $user = $this->actingMember();
        $otherUser = User::factory()->create();

        $bundleId = (string) Str::uuid();

        $offer = BookOffer::create([
            'user_id' => $user->id,
            'bundle_id' => $bundleId,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Maddrax 1',
            'condition' => 'Z2',
        ]);

        $request = BookRequest::create([
            'user_id' => $otherUser->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Maddrax 1',
            'condition' => 'Z2',
        ]);

        $swap = BookSwap::create([
            'offer_id' => $offer->id,
            'request_id' => $request->id,
        ]);

        Livewire::test(RomantauschIndex::class)
            ->call('deleteBundle', $bundleId)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('book_swaps', ['id' => $swap->id]);
        $this->assertDatabaseMissing('book_offers', ['bundle_id' => $bundleId]);
    }

    public function test_delete_bundle_deletes_photos_from_storage(): void
    {
        $this->putBookData();
        $user = $this->actingMember();

        Storage::fake('public');

        $bundleId = (string) Str::uuid();
        $photoPath = 'book-offers/test-photo.jpg';

        Storage::disk('public')->put($photoPath, 'fake image content');

        BookOffer::create([
            'user_id' => $user->id,
            'bundle_id' => $bundleId,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Maddrax 1',
            'condition' => 'Z2',
            'photos' => [$photoPath],
        ]);

        BookOffer::create([
            'user_id' => $user->id,
            'bundle_id' => $bundleId,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 2,
            'book_title' => 'Maddrax 2',
            'condition' => 'Z2',
            'photos' => [$photoPath],
        ]);

        Livewire::test(RomantauschIndex::class)
            ->call('deleteBundle', $bundleId)
            ->assertDispatched('toast');

        Storage::disk('public')->assertMissing($photoPath);
    }

    // ====== Match Counting Tests ======

    public function test_bundle_shows_match_count_for_user_requests(): void
    {
        $this->putBookData();
        $owner = $this->actingMember();

        $bundleId = (string) Str::uuid();

        // Owner erstellt Stapel mit 5 Büchern
        for ($i = 1; $i <= 5; $i++) {
            BookOffer::create([
                'user_id' => $owner->id,
                'bundle_id' => $bundleId,
                'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
                'book_number' => $i,
                'book_title' => "Maddrax {$i}",
                'condition' => 'Z2',
            ]);
        }

        // Viewer sucht 2 davon
        $viewer = $this->actingMember();

        BookRequest::create([
            'user_id' => $viewer->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Maddrax 1',
            'condition' => 'Z2',
        ]);

        BookRequest::create([
            'user_id' => $viewer->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 3,
            'book_title' => 'Maddrax 3',
            'condition' => 'Z2',
        ]);

        Livewire::test(RomantauschIndex::class)
            ->assertOk()
            ->assertSet('bundles', function ($bundles) {
                $bundle = $bundles->first();

                return $bundle->matching_count === 2 && $bundle->total_count === 5;
            });
    }

    public function test_bundle_not_highlighted_for_owner(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

        $bundleId = (string) Str::uuid();

        // User erstellt Stapel
        BookOffer::create([
            'user_id' => $user->id,
            'bundle_id' => $bundleId,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Maddrax 1',
            'condition' => 'Z2',
        ]);

        BookOffer::create([
            'user_id' => $user->id,
            'bundle_id' => $bundleId,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 2,
            'book_title' => 'Maddrax 2',
            'condition' => 'Z2',
        ]);

        // User sucht dasselbe Buch (unwahrscheinlich, aber möglich)
        BookRequest::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Maddrax 1',
            'condition' => 'Z2',
        ]);

        Livewire::test(RomantauschIndex::class)
            ->assertOk()
            ->assertSet('bundles', function ($bundles) {
                $bundle = $bundles->first();

                return $bundle->matching_count === 0;
            });
    }

    // ====== Model Tests ======

    public function test_is_part_of_bundle_returns_true_for_bundle_offer(): void
    {
        $user = $this->actingMember();

        $offer = BookOffer::create([
            'user_id' => $user->id,
            'bundle_id' => (string) Str::uuid(),
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Test',
            'condition' => 'Z2',
        ]);

        $this->assertTrue($offer->isPartOfBundle());
    }

    public function test_is_part_of_bundle_returns_false_for_single_offer(): void
    {
        $user = $this->actingMember();

        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Test',
            'condition' => 'Z2',
        ]);

        $this->assertFalse($offer->isPartOfBundle());
    }

    public function test_bundle_siblings_returns_other_bundle_members(): void
    {
        $user = $this->actingMember();
        $bundleId = (string) Str::uuid();

        $offer1 = BookOffer::create([
            'user_id' => $user->id,
            'bundle_id' => $bundleId,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Test 1',
            'condition' => 'Z2',
        ]);

        $offer2 = BookOffer::create([
            'user_id' => $user->id,
            'bundle_id' => $bundleId,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 2,
            'book_title' => 'Test 2',
            'condition' => 'Z2',
        ]);

        $siblings = $offer1->bundleSiblings();

        $this->assertCount(1, $siblings);
        $this->assertEquals($offer2->id, $siblings->first()->id);
    }

    public function test_bundle_offers_returns_all_bundle_members_including_self(): void
    {
        $user = $this->actingMember();
        $bundleId = (string) Str::uuid();

        $offer1 = BookOffer::create([
            'user_id' => $user->id,
            'bundle_id' => $bundleId,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Test 1',
            'condition' => 'Z2',
        ]);

        $offer2 = BookOffer::create([
            'user_id' => $user->id,
            'bundle_id' => $bundleId,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 2,
            'book_title' => 'Test 2',
            'condition' => 'Z2',
        ]);

        $allOffers = $offer1->bundleOffers();

        $this->assertCount(2, $allOffers);
    }

    public function test_condition_range_attribute_returns_range_string(): void
    {
        $user = $this->actingMember();

        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Test',
            'condition' => 'Z1',
            'condition_max' => 'Z2',
        ]);

        $this->assertEquals('Z1 bis Z2', $offer->condition_range);
    }

    public function test_condition_range_attribute_returns_single_condition_when_no_max(): void
    {
        $user = $this->actingMember();

        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Test',
            'condition' => 'Z2',
        ]);

        $this->assertEquals('Z2', $offer->condition_range);
    }

    // ====== Index Display Tests ======

    public function test_index_displays_bundles_grouped(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

        $bundleId = (string) Str::uuid();

        for ($i = 1; $i <= 3; $i++) {
            BookOffer::create([
                'user_id' => $user->id,
                'bundle_id' => $bundleId,
                'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
                'book_number' => $i,
                'book_title' => "Maddrax {$i}",
                'condition' => 'Z2',
            ]);
        }

        Livewire::test(RomantauschIndex::class)
            ->assertOk()
            ->assertSet('bundles', function ($bundles) use ($bundleId) {
                return $bundles->count() === 1 && $bundles->first()->bundle_id === $bundleId;
            });
    }

    public function test_index_separates_single_offers_from_bundles(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

        // Ein Stapel
        $bundleId = (string) Str::uuid();
        BookOffer::create([
            'user_id' => $user->id,
            'bundle_id' => $bundleId,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Maddrax 1',
            'condition' => 'Z2',
        ]);

        BookOffer::create([
            'user_id' => $user->id,
            'bundle_id' => $bundleId,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 2,
            'book_title' => 'Maddrax 2',
            'condition' => 'Z2',
        ]);

        // Ein Einzelangebot
        BookOffer::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 10,
            'book_title' => 'Maddrax 10',
            'condition' => 'Z2',
        ]);

        Livewire::test(RomantauschIndex::class)
            ->assertOk()
            ->assertSet('bundles', fn ($b) => $b->count() === 1)
            ->assertSet('offers', fn ($o) => $o->count() === 1);
    }

    // ====== Photo Update Tests (Livewire) ======

    public function test_update_bundle_removes_photo_references_from_database(): void
    {
        $this->putBookData();
        $user = $this->actingMember();

        Storage::fake('public');

        $bundleId = (string) Str::uuid();
        $photoToRemove = 'book-offers/to-remove.jpg';
        $photoToKeep = 'book-offers/to-keep.jpg';

        Storage::disk('public')->put($photoToRemove, 'remove me');
        Storage::disk('public')->put($photoToKeep, 'keep me');

        for ($i = 1; $i <= 2; $i++) {
            BookOffer::create([
                'user_id' => $user->id,
                'bundle_id' => $bundleId,
                'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
                'book_number' => $i,
                'book_title' => "Maddrax {$i}",
                'condition' => 'Z1',
                'photos' => [$photoToRemove, $photoToKeep],
            ]);
        }

        // Bundle aktualisieren mit remove_photos
        Livewire::test(RomantauschBundleForm::class, ['bundleId' => $bundleId])
            ->set('remove_photos', [$photoToRemove])
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        // DB sollte nur noch das behaltene Foto enthalten
        $offer = BookOffer::where('bundle_id', $bundleId)->first();
        $this->assertCount(1, $offer->photos);
        $this->assertContains($photoToKeep, $offer->photos);
        $this->assertNotContains($photoToRemove, $offer->photos);
    }
}
