<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Livewire\RomantauschBundleForm;
use App\Livewire\RomantauschIndex;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\User;
use App\Services\Romantausch\BookPhotoService;
use App\Services\Romantausch\BundleService;
use App\Services\Romantausch\RomantauschBaxxService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use LogicException;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(BundleOfferTestFixtures::class);

test('parse book numbers single number', function (): void {
    $service = app(BundleService::class);
    $result = $service->parseBookNumbers('5');
    $this->assertArraysAreEqual([5], $result);
});

test('parse book numbers multiple single numbers', function (): void {
    $service = app(BundleService::class);
    $result = $service->parseBookNumbers('1, 3, 5, 7');
    $this->assertArraysAreEqual([1, 3, 5, 7], $result);
});

test('parse book numbers range', function (): void {
    $service = app(BundleService::class);
    $result = $service->parseBookNumbers('1-5');
    $this->assertArraysAreEqual([1, 2, 3, 4, 5], $result);
});

test('parse book numbers mixed ranges and singles', function (): void {
    $service = app(BundleService::class);
    $result = $service->parseBookNumbers('1-5, 10, 15-17');
    $this->assertArraysAreEqual([1, 2, 3, 4, 5, 10, 15, 16, 17], $result);
});

test('parse book numbers removes duplicates', function (): void {
    $service = app(BundleService::class);
    $result = $service->parseBookNumbers('1-5, 3, 4, 5');
    $this->assertArraysAreEqual([1, 2, 3, 4, 5], $result);
});

test('parse book numbers ignores invalid input', function (): void {
    $service = app(BundleService::class);
    $result = $service->parseBookNumbers('1, abc, 5, xyz');
    $this->assertArraysAreEqual([1, 5], $result);
});

test('parse book numbers handles whitespace', function (): void {
    $service = app(BundleService::class);
    $result = $service->parseBookNumbers('  1  ,  3  -  5  ,  10  ');
    $this->assertArraysAreEqual([1, 3, 4, 5, 10], $result);
});

test('parse book numbers removes duplicates and handles unsorted', function (): void {
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
    expect($result)->toHaveCount(5);
    // Prüfe dass array_unique tatsächlich Duplikate entfernt hat
    expect(array_unique($result))->toHaveSameSize($result);
    // Prüfe alle erwarteten Werte (ohne Reihenfolge-Annahme)
    $expectedValues = [1, 2, 3, 5, 10];
    foreach ($expectedValues as $expected) {
        expect($result)->toContain($expected);
    }
});

test('store bundle creates entries with shared bundle id', function (): void {
    $this->putBookData();
    $user = $this->actingMember();
    Storage::fake('public');
    Livewire::test(RomantauschBundleForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_numbers', '1-5')->set('condition', 'Z1')->set('condition_max', 'Z2')->call('save')->assertRedirect(route('romantausch.index'));
    $offers = BookOffer::where('user_id', $user->id)->get();
    expect($offers)->toHaveCount(5);
    $bundleId = $offers->first()->bundle_id;
    expect($bundleId)->not->toBeNull();
    foreach ($offers as $offer) {
        expect($offer->bundle_id)->toEqual($bundleId)
            ->and($offer->condition)->toEqual('Z1')
            ->and($offer->condition_max)->toEqual('Z2');
    }
});

test('store bundle with photos', function (): void {
    $this->putBookData();
    $user = $this->actingMember();
    Storage::fake('public');
    Livewire::test(RomantauschBundleForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_numbers', '1-3')->set('condition', 'Z2')->set('photos', [UploadedFile::fake()->image('test.jpg', 800, 600)])->call('save')->assertRedirect(route('romantausch.index'));
    $offers = BookOffer::where('user_id', $user->id)->get();
    expect($offers)->toHaveCount(3);
    // Alle sollten dieselben Fotos haben
    $firstPhotos = $offers->first()->photos;
    expect($firstPhotos)->not->toBeEmpty();
    foreach ($offers as $offer) {
        expect($offer->photos)->toEqual($firstPhotos);
    }
});

test('store bundle rolls back and cleans up uploaded photos when baxx award fails', function (): void {
    $this->putBookData();
    $this->actingMember();
    Storage::fake('public');
    $this->mock(RomantauschBaxxService::class, function ($mock) {
        $mock->shouldReceive('awardForNewOffers')->once()->andThrow(new LogicException('Boom'));
    });
    Livewire::test(RomantauschBundleForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_numbers', '1-3')->set('condition', 'Z2')->set('photos', [UploadedFile::fake()->image('bundle.jpg')])->call('save')->assertHasErrors('book_numbers')->assertNoRedirect();
    $this->assertDatabaseCount('book_offers', 0);
    expect(Storage::disk('public')->allFiles(BookPhotoService::STORAGE_PATH))->toBe([]);
});

test('edit bundle page loads for owner', function (): void {
    $this->putBookData();
    $user = $this->actingMember();
    $bundleId = (string) Str::uuid();
    BookOffer::create(['user_id' => $user->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Maddrax 1', 'condition' => 'Z2']);
    BookOffer::create(['user_id' => $user->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 2, 'book_title' => 'Maddrax 2', 'condition' => 'Z2']);
    Livewire::test(RomantauschBundleForm::class, ['bundleId' => $bundleId])->assertOk()->assertSet('bundleId', $bundleId)->assertSet('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->assertSet('condition', 'Z2')->assertSee('Stapel-Angebot bearbeiten');
});

test('edit bundle forbidden for non owner', function (): void {
    $this->putBookData();
    $owner = $this->actingMember();
    $bundleId = (string) Str::uuid();
    BookOffer::create(['user_id' => $owner->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Maddrax 1', 'condition' => 'Z2']);
    // Wechsle zu anderem User
    $otherUser = $this->actingMember();
    Livewire::test(RomantauschBundleForm::class, ['bundleId' => $bundleId])->assertStatus(404);
});

test('update bundle removes specified offers', function (): void {
    $this->putBookData();
    $user = $this->actingMember();
    $bundleId = (string) Str::uuid();
    $offer1 = BookOffer::create(['user_id' => $user->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Maddrax 1', 'condition' => 'Z2']);
    $offer2 = BookOffer::create(['user_id' => $user->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 2, 'book_title' => 'Maddrax 2', 'condition' => 'Z2']);
    $offer3 = BookOffer::create(['user_id' => $user->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 3, 'book_title' => 'Maddrax 3', 'condition' => 'Z2']);
    // Aktualisiere auf 2-3 (ohne 1)
    Livewire::test(RomantauschBundleForm::class, ['bundleId' => $bundleId])->set('book_numbers', '2-3')->call('save')->assertRedirect(route('romantausch.index'));
    // Offer 1 sollte jetzt entfernt sein
    $this->assertDatabaseMissing('book_offers', ['id' => $offer1->id]);
    $this->assertDatabaseHas('book_offers', ['id' => $offer2->id]);
    $this->assertDatabaseHas('book_offers', ['id' => $offer3->id]);
});

test('update bundle rolls back and cleans up new photos when baxx award fails', function (): void {
    $this->putBookData();
    $user = $this->actingMember();
    Storage::fake('public');
    $bundleId = (string) Str::uuid();
    $offer1 = BookOffer::create(['user_id' => $user->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Maddrax 1', 'condition' => 'Z2']);
    $offer2 = BookOffer::create(['user_id' => $user->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 2, 'book_title' => 'Maddrax 2', 'condition' => 'Z2']);
    $this->mock(RomantauschBaxxService::class, function ($mock) {
        $mock->shouldReceive('awardForNewOffers')->once()->andThrow(new LogicException('Boom'));
    });
    Livewire::test(RomantauschBundleForm::class, ['bundleId' => $bundleId])->set('book_numbers', '1-3')->set('condition', 'Z1')->set('photos', [UploadedFile::fake()->image('new-bundle.jpg')])->call('save')->assertHasErrors('book_numbers')->assertNoRedirect();
    $this->assertDatabaseHas('book_offers', ['id' => $offer1->id, 'condition' => 'Z2']);
    $this->assertDatabaseHas('book_offers', ['id' => $offer2->id, 'condition' => 'Z2']);
    $this->assertDatabaseMissing('book_offers', ['bundle_id' => $bundleId, 'book_number' => 3]);
    expect(Storage::disk('public')->allFiles(BookPhotoService::STORAGE_PATH))->toBe([]);
});

test('update bundle blocked when active swaps exist', function (): void {
    $this->putBookData();
    $user = $this->actingMember();
    $otherUser = User::factory()->create();
    $bundleId = (string) Str::uuid();
    $offer = BookOffer::create(['user_id' => $user->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Maddrax 1', 'condition' => 'Z2']);
    BookOffer::create(['user_id' => $user->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 2, 'book_title' => 'Maddrax 2', 'condition' => 'Z2']);
    $request = BookRequest::create(['user_id' => $otherUser->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Maddrax 1', 'condition' => 'Z2']);
    BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
    // Livewire-Komponente blockiert Bearbeitung bei aktiven Swaps
    Livewire::test(RomantauschBundleForm::class, ['bundleId' => $bundleId])->assertRedirect(route('romantausch.index'));
});

test('delete bundle forbidden for non owner', function (): void {
    $this->putBookData();
    $owner = $this->actingMember();
    $bundleId = (string) Str::uuid();
    BookOffer::create(['user_id' => $owner->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Maddrax 1', 'condition' => 'Z2']);
    // Wechsle zu anderem User
    $otherUser = $this->actingMember();
    Livewire::test(RomantauschIndex::class)->call('deleteBundle', $bundleId)->assertStatus(404);
    // Bundle sollte weiterhin existieren
    $this->assertDatabaseHas('book_offers', ['bundle_id' => $bundleId]);
});

test('delete bundle removes associated swaps', function (): void {
    $this->putBookData();
    $user = $this->actingMember();
    $otherUser = User::factory()->create();
    $bundleId = (string) Str::uuid();
    $offer = BookOffer::create(['user_id' => $user->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Maddrax 1', 'condition' => 'Z2']);
    $request = BookRequest::create(['user_id' => $otherUser->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Maddrax 1', 'condition' => 'Z2']);
    $swap = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
    Livewire::test(RomantauschIndex::class)->call('deleteBundle', $bundleId)->assertDispatched('toast');
    $this->assertDatabaseMissing('book_swaps', ['id' => $swap->id]);
    $this->assertDatabaseMissing('book_offers', ['bundle_id' => $bundleId]);
});

test('delete bundle deletes photos from storage', function (): void {
    $this->putBookData();
    $user = $this->actingMember();
    Storage::fake('public');
    $bundleId = (string) Str::uuid();
    $photoPath = 'book-offers/test-photo.jpg';
    Storage::disk('public')->put($photoPath, 'fake image content');
    BookOffer::create(['user_id' => $user->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Maddrax 1', 'condition' => 'Z2', 'photos' => [$photoPath]]);
    BookOffer::create(['user_id' => $user->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 2, 'book_title' => 'Maddrax 2', 'condition' => 'Z2', 'photos' => [$photoPath]]);
    Livewire::test(RomantauschIndex::class)->call('deleteBundle', $bundleId)->assertDispatched('toast');
    Storage::disk('public')->assertMissing($photoPath);
});

test('bundle shows match count for user requests', function (): void {
    $this->putBookData();
    $owner = $this->actingMember();
    $bundleId = (string) Str::uuid();
    // Owner erstellt Stapel mit 5 Büchern
    for ($i = 1; $i <= 5; $i++) {
        BookOffer::create(['user_id' => $owner->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => $i, 'book_title' => "Maddrax {$i}", 'condition' => 'Z2']);
    }
    // Viewer sucht 2 davon
    $viewer = $this->actingMember();
    BookRequest::create(['user_id' => $viewer->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Maddrax 1', 'condition' => 'Z2']);
    BookRequest::create(['user_id' => $viewer->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 3, 'book_title' => 'Maddrax 3', 'condition' => 'Z2']);
    Livewire::test(RomantauschIndex::class)->assertOk()->assertSet('bundles', function ($bundles) {
        $bundle = $bundles->first();

        return $bundle->matching_count === 2 && $bundle->total_count === 5;
    });
});

test('bundle not highlighted for owner', function (): void {
    $this->putBookData();
    $user = $this->actingMember();
    $this->actingAs($user);
    $bundleId = (string) Str::uuid();
    // User erstellt Stapel
    BookOffer::create(['user_id' => $user->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Maddrax 1', 'condition' => 'Z2']);
    BookOffer::create(['user_id' => $user->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 2, 'book_title' => 'Maddrax 2', 'condition' => 'Z2']);
    // User sucht dasselbe Buch (unwahrscheinlich, aber möglich)
    BookRequest::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Maddrax 1', 'condition' => 'Z2']);
    Livewire::test(RomantauschIndex::class)->assertOk()->assertSet('bundles', function ($bundles) {
        $bundle = $bundles->first();

        return $bundle->matching_count === 0;
    });
});

test('is part of bundle returns true for bundle offer', function (): void {
    $user = $this->actingMember();
    $offer = BookOffer::create(['user_id' => $user->id, 'bundle_id' => (string) Str::uuid(), 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Test', 'condition' => 'Z2']);
    expect($offer->isPartOfBundle())->toBeTrue();
});

test('is part of bundle returns false for single offer', function (): void {
    $user = $this->actingMember();
    $offer = BookOffer::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Test', 'condition' => 'Z2']);
    expect($offer->isPartOfBundle())->toBeFalse();
});

test('bundle siblings returns other bundle members', function (): void {
    $user = $this->actingMember();
    $bundleId = (string) Str::uuid();
    $offer1 = BookOffer::create(['user_id' => $user->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Test 1', 'condition' => 'Z2']);
    $offer2 = BookOffer::create(['user_id' => $user->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 2, 'book_title' => 'Test 2', 'condition' => 'Z2']);
    $siblings = $offer1->bundleSiblings();
    expect($siblings)->toHaveCount(1)
        ->and($siblings->first()->id)->toEqual($offer2->id);
});

test('bundle offers returns all bundle members including self', function (): void {
    $user = $this->actingMember();
    $bundleId = (string) Str::uuid();
    $offer1 = BookOffer::create(['user_id' => $user->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Test 1', 'condition' => 'Z2']);
    $offer2 = BookOffer::create(['user_id' => $user->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 2, 'book_title' => 'Test 2', 'condition' => 'Z2']);
    $allOffers = $offer1->bundleOffers();
    expect($allOffers)->toHaveCount(2);
});

test('condition range attribute returns range string', function (): void {
    $user = $this->actingMember();
    $offer = BookOffer::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Test', 'condition' => 'Z1', 'condition_max' => 'Z2']);
    expect($offer->condition_range)->toEqual('Z1 bis Z2');
});

test('condition range attribute returns single condition when no max', function (): void {
    $user = $this->actingMember();
    $offer = BookOffer::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Test', 'condition' => 'Z2']);
    expect($offer->condition_range)->toEqual('Z2');
});

test('index displays bundles grouped', function (): void {
    $this->putBookData();
    $user = $this->actingMember();
    $this->actingAs($user);
    $bundleId = (string) Str::uuid();
    for ($i = 1; $i <= 3; $i++) {
        BookOffer::create(['user_id' => $user->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => $i, 'book_title' => "Maddrax {$i}", 'condition' => 'Z2']);
    }
    Livewire::test(RomantauschIndex::class)->assertOk()->assertSet('bundles', fn ($bundles) => $bundles->count() === 1 && $bundles->first()->bundle_id === $bundleId);
});

test('index separates single offers from bundles', function (): void {
    $this->putBookData();
    $user = $this->actingMember();
    $this->actingAs($user);
    // Ein Stapel
    $bundleId = (string) Str::uuid();
    BookOffer::create(['user_id' => $user->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Maddrax 1', 'condition' => 'Z2']);
    BookOffer::create(['user_id' => $user->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 2, 'book_title' => 'Maddrax 2', 'condition' => 'Z2']);
    // Ein Einzelangebot
    BookOffer::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 10, 'book_title' => 'Maddrax 10', 'condition' => 'Z2']);
    Livewire::test(RomantauschIndex::class)->assertOk()->assertSet('bundles', fn ($b) => $b->count() === 1)->assertSet('offers', fn ($o) => $o->count() === 1);
});

test('update bundle removes photo references from database', function (): void {
    $this->putBookData();
    $user = $this->actingMember();
    Storage::fake('public');
    $bundleId = (string) Str::uuid();
    $photoToRemove = 'book-offers/to-remove.jpg';
    $photoToKeep = 'book-offers/to-keep.jpg';
    Storage::disk('public')->put($photoToRemove, 'remove me');
    Storage::disk('public')->put($photoToKeep, 'keep me');
    for ($i = 1; $i <= 2; $i++) {
        BookOffer::create(['user_id' => $user->id, 'bundle_id' => $bundleId, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => $i, 'book_title' => "Maddrax {$i}", 'condition' => 'Z1', 'photos' => [$photoToRemove, $photoToKeep]]);
    }
    // Bundle aktualisieren mit remove_photos
    Livewire::test(RomantauschBundleForm::class, ['bundleId' => $bundleId])->set('remove_photos', [$photoToRemove])->call('save')->assertRedirect(route('romantausch.index'));
    // DB sollte nur noch das behaltene Foto enthalten
    $offer = BookOffer::where('bundle_id', $bundleId)->first();
    expect($offer->photos)->toHaveCount(1)
        ->toContain($photoToKeep)->not->toContain($photoToRemove);
});
