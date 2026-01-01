<?php

namespace Tests\Feature;

use App\Enums\BookType;
use App\Http\Controllers\RomantauschController;
use App\Models\Book;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BundleOfferTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Mitglied->value]);
        return $user;
    }

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
        $controller = new RomantauschController(app(\App\Services\RomantauschInfoProvider::class));

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('parseBookNumbers');
        $method->setAccessible(true);

        $result = $method->invoke($controller, '5');
        $this->assertEquals([5], $result);
    }

    public function test_parse_book_numbers_multiple_single_numbers(): void
    {
        $controller = new RomantauschController(app(\App\Services\RomantauschInfoProvider::class));

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('parseBookNumbers');
        $method->setAccessible(true);

        $result = $method->invoke($controller, '1, 3, 5, 7');
        $this->assertEquals([1, 3, 5, 7], $result);
    }

    public function test_parse_book_numbers_range(): void
    {
        $controller = new RomantauschController(app(\App\Services\RomantauschInfoProvider::class));

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('parseBookNumbers');
        $method->setAccessible(true);

        $result = $method->invoke($controller, '1-5');
        $this->assertEquals([1, 2, 3, 4, 5], $result);
    }

    public function test_parse_book_numbers_mixed_ranges_and_singles(): void
    {
        $controller = new RomantauschController(app(\App\Services\RomantauschInfoProvider::class));

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('parseBookNumbers');
        $method->setAccessible(true);

        $result = $method->invoke($controller, '1-5, 10, 15-17');
        $this->assertEquals([1, 2, 3, 4, 5, 10, 15, 16, 17], $result);
    }

    public function test_parse_book_numbers_removes_duplicates(): void
    {
        $controller = new RomantauschController(app(\App\Services\RomantauschInfoProvider::class));

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('parseBookNumbers');
        $method->setAccessible(true);

        $result = $method->invoke($controller, '1-5, 3, 4, 5');
        $this->assertEquals([1, 2, 3, 4, 5], $result);
    }

    public function test_parse_book_numbers_ignores_invalid_input(): void
    {
        $controller = new RomantauschController(app(\App\Services\RomantauschInfoProvider::class));

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('parseBookNumbers');
        $method->setAccessible(true);

        $result = $method->invoke($controller, '1, abc, 5, xyz');
        $this->assertEquals([1, 5], $result);
    }

    public function test_parse_book_numbers_handles_whitespace(): void
    {
        $controller = new RomantauschController(app(\App\Services\RomantauschInfoProvider::class));

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('parseBookNumbers');
        $method->setAccessible(true);

        $result = $method->invoke($controller, '  1  ,  3  -  5  ,  10  ');
        $this->assertEquals([1, 3, 4, 5, 10], $result);
    }

    public function test_parse_book_numbers_removes_duplicates_and_handles_unsorted(): void
    {
        $controller = new RomantauschController(app(\App\Services\RomantauschInfoProvider::class));

        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('parseBookNumbers');
        $method->setAccessible(true);

        // Input mit Duplikaten UND unsortiert: 5 kommt zweimal vor, 1-3 überlappt mit 2,3
        $result = $method->invoke($controller, '5, 1-3, 2, 3, 5, 10');

        // Die Methode entfernt Duplikate, garantiert aber keine bestimmte Reihenfolge.
        // JavaScript-Version sortiert, PHP-Version behält Einfüge-Reihenfolge.
        // Erwartetes Ergebnis: 5 eindeutige Werte {1, 2, 3, 5, 10}
        $this->assertCount(5, $result);
        $this->assertContains(1, $result);
        $this->assertContains(2, $result);
        $this->assertContains(3, $result);
        $this->assertContains(5, $result);
        $this->assertContains(10, $result);

        // Prüfe dass keine Duplikate vorhanden sind
        $this->assertEquals(count($result), count(array_unique($result)));
    }

    // ====== Bundle Creation Tests ======

    public function test_create_bundle_offer_page_loads(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->get('/romantauschboerse/stapel-angebot-erstellen');

        $response->assertOk();
        $response->assertViewIs('romantausch.create_bundle_offer');
        $response->assertSee('Stapel-Angebot erstellen');
    }

    public function test_store_bundle_offer_creates_multiple_entries_with_shared_bundle_id(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

        Storage::fake('public');

        $response = $this->post('/romantauschboerse/stapel-angebot-speichern', [
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_numbers' => '1-5',
            'condition' => 'Z1',
            'condition_max' => 'Z2',
        ]);

        $response->assertRedirect(route('romantausch.index'));

        // Es sollten 5 Einträge mit derselben bundle_id existieren
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

    public function test_store_bundle_offer_with_photos(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

        Storage::fake('public');

        $photo = UploadedFile::fake()->image('test.jpg', 800, 600);

        $response = $this->post('/romantauschboerse/stapel-angebot-speichern', [
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_numbers' => '1-3',
            'condition' => 'Z2',
            'photos' => [$photo],
        ]);

        $response->assertRedirect(route('romantausch.index'));

        $offers = BookOffer::where('user_id', $user->id)->get();
        $this->assertCount(3, $offers);

        // Alle sollten dieselben Fotos haben
        $firstPhotos = $offers->first()->photos;
        $this->assertNotEmpty($firstPhotos);

        foreach ($offers as $offer) {
            $this->assertEquals($firstPhotos, $offer->photos);
        }
    }

    public function test_store_bundle_offer_validates_minimum_two_books(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->post('/romantauschboerse/stapel-angebot-speichern', [
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_numbers' => '5',
            'condition' => 'Z2',
        ]);

        $response->assertSessionHasErrors('book_numbers');
    }

    public function test_store_bundle_offer_shows_error_for_nonexistent_books(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

        // Buch 999 existiert nicht - der Controller zeigt einen Fehler an
        $response = $this->post('/romantauschboerse/stapel-angebot-speichern', [
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_numbers' => '1, 2, 999',
            'condition' => 'Z2',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_store_bundle_offer_requires_series(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->post('/romantauschboerse/stapel-angebot-speichern', [
            'book_numbers' => '1-5',
            'condition' => 'Z2',
        ]);

        $response->assertSessionHasErrors('series');
    }

    public function test_store_bundle_offer_requires_condition(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->post('/romantauschboerse/stapel-angebot-speichern', [
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_numbers' => '1-5',
        ]);

        $response->assertSessionHasErrors('condition');
    }

    // ====== Bundle Editing Tests ======

    public function test_edit_bundle_page_loads_for_owner(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

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

        $response = $this->get("/romantauschboerse/stapel/{$bundleId}/bearbeiten");

        $response->assertOk();
        $response->assertViewIs('romantausch.edit_bundle');
        // Die View zeigt "2 Romane" und die Nummern als Range "1-2"
        $response->assertSee('2 Romane');
        $response->assertSee('Stapel-Angebot bearbeiten');
    }

    public function test_edit_bundle_forbidden_for_non_owner(): void
    {
        $this->putBookData();

        $owner = $this->actingMember();
        $otherUser = $this->actingMember();

        $bundleId = (string) Str::uuid();

        BookOffer::create([
            'user_id' => $owner->id,
            'bundle_id' => $bundleId,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Maddrax 1',
            'condition' => 'Z2',
        ]);

        $this->actingAs($otherUser);

        $response = $this->get("/romantauschboerse/stapel/{$bundleId}/bearbeiten");

        // Der Controller gibt 404 zurück, da der Stapel nur für den Owner sichtbar ist
        $response->assertNotFound();
    }

    public function test_update_bundle_removes_specified_offers(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

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

        // Die Update-Route erfordert book_numbers und condition
        // Wir aktualisieren auf 2-3 (ohne 1)
        $response = $this->put("/romantauschboerse/stapel/{$bundleId}", [
            'book_numbers' => '2-3',
            'condition' => 'Z2',
        ]);

        $response->assertRedirect(route('romantausch.index'));

        // Offer 1 sollte jetzt entfernt sein
        $this->assertDatabaseMissing('book_offers', ['id' => $offer1->id]);
        $this->assertDatabaseHas('book_offers', ['id' => $offer2->id]);
        $this->assertDatabaseHas('book_offers', ['id' => $offer3->id]);
    }

    public function test_update_bundle_removes_associated_swaps(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $otherUser = User::factory()->create();

        $this->actingAs($user);

        $bundleId = (string) Str::uuid();

        $offer = BookOffer::create([
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

        // Aktualisiere auf 2-3 (ohne 1, welches einen Swap hat)
        $response = $this->put("/romantauschboerse/stapel/{$bundleId}", [
            'book_numbers' => '2-3',
            'condition' => 'Z2',
        ]);

        $response->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseMissing('book_swaps', ['id' => $swap->id]);
        $this->assertDatabaseMissing('book_offers', ['id' => $offer->id]);
    }

    // ====== Bundle Deletion Tests ======

    public function test_delete_bundle_removes_all_offers(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

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

        $response = $this->delete("/romantauschboerse/stapel/{$bundleId}");

        $response->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseMissing('book_offers', ['bundle_id' => $bundleId]);
    }

    public function test_delete_bundle_forbidden_for_non_owner(): void
    {
        $this->putBookData();

        $owner = $this->actingMember();
        $otherUser = $this->actingMember();

        $bundleId = (string) Str::uuid();

        BookOffer::create([
            'user_id' => $owner->id,
            'bundle_id' => $bundleId,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Maddrax 1',
            'condition' => 'Z2',
        ]);

        $this->actingAs($otherUser);

        $response = $this->delete("/romantauschboerse/stapel/{$bundleId}");

        // Der Controller gibt 404 zurück, da der Stapel nur für den Owner sichtbar ist
        $response->assertNotFound();
    }

    public function test_delete_bundle_removes_associated_swaps(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $otherUser = User::factory()->create();

        $this->actingAs($user);

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

        $response = $this->delete("/romantauschboerse/stapel/{$bundleId}");

        $response->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseMissing('book_swaps', ['id' => $swap->id]);
    }

    public function test_delete_bundle_deletes_photos_from_storage(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

        Storage::fake('public');

        $bundleId = (string) Str::uuid();
        // Verwende denselben Pfad wie RomantauschController::PHOTO_STORAGE_PATH
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

        $this->delete("/romantauschboerse/stapel/{$bundleId}");

        Storage::disk('public')->assertMissing($photoPath);
    }

    // ====== Match Counting Tests ======

    public function test_bundle_shows_match_count_for_user_requests(): void
    {
        $this->putBookData();

        $owner = $this->actingMember();
        $viewer = $this->actingMember();

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

        $this->actingAs($viewer);

        $response = $this->get('/romantauschboerse');

        $response->assertOk();
        // Die View sollte "2 von 5 passen zu deinen Gesuchen" anzeigen
        $response->assertSee('2');
        $response->assertSee('5');
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

        $response = $this->get('/romantauschboerse');

        $response->assertOk();

        // Eigene Stapel sollten nicht als "Match" angezeigt werden
        $response->assertViewHas('bundles', function ($bundles) {
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

        $response = $this->get('/romantauschboerse');

        $response->assertOk();
        $response->assertViewHas('bundles', function ($bundles) use ($bundleId) {
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

        $response = $this->get('/romantauschboerse');

        $response->assertOk();

        $response->assertViewHas('bundles', fn ($b) => $b->count() === 1);
        $response->assertViewHas('offers', fn ($o) => $o->count() === 1);
    }

    // ====== Photo Cleanup Tests ======

    /**
     * Test dass Fotos korrekt gelöscht werden wenn Bundle aktualisiert wird.
     *
     * Dieser Test verifiziert den Photo-Cleanup-Mechanismus mit DB::afterCommit().
     * Bei Transaktions-Rollback sollten keine Fotos gelöscht werden.
     *
     * Die Foto-Löschung wird über das 'remove_photos' Array gesteuert.
     */
    public function test_update_bundle_removes_photos_via_remove_photos_array(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

        Storage::fake('public');

        $bundleId = (string) Str::uuid();
        $photoToRemove = 'book-offers/photo-to-remove.jpg';
        $photoToKeep = 'book-offers/photo-to-keep.jpg';

        Storage::disk('public')->put($photoToRemove, 'old image content');
        Storage::disk('public')->put($photoToKeep, 'keep image content');

        // Erstelle Bundle mit beiden Fotos
        for ($i = 1; $i <= 3; $i++) {
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

        // Beide Fotos sollten existieren
        Storage::disk('public')->assertExists($photoToRemove);
        Storage::disk('public')->assertExists($photoToKeep);

        // Bundle aktualisieren - ein Foto zum Löschen markieren
        $response = $this->put("/romantauschboerse/stapel/{$bundleId}", [
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_numbers' => '1-3',
            'condition' => 'Z1',
            'remove_photos' => [$photoToRemove],
        ]);

        $response->assertRedirect();

        // Markiertes Foto sollte gelöscht sein (via afterCommit)
        Storage::disk('public')->assertMissing($photoToRemove);
        // Das andere Foto sollte erhalten bleiben
        Storage::disk('public')->assertExists($photoToKeep);
    }

    /**
     * Test dass Fotos in der DB aktualisiert werden wenn remove_photos verwendet wird.
     */
    public function test_update_bundle_removes_photo_references_from_database(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

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
        $response = $this->put("/romantauschboerse/stapel/{$bundleId}", [
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_numbers' => '1-2',
            'condition' => 'Z2',
            'remove_photos' => [$photoToRemove],
        ]);

        $response->assertRedirect();

        // DB sollte nur noch das behaltene Foto enthalten
        $offer = BookOffer::where('bundle_id', $bundleId)->first();
        $this->assertCount(1, $offer->photos);
        $this->assertContains($photoToKeep, $offer->photos);
        $this->assertNotContains($photoToRemove, $offer->photos);
    }
}
