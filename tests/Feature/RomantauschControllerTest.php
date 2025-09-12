<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\Book;
use App\Models\BookSwap;
use App\Enums\BookType;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use App\Http\Controllers\RomantauschController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\View\View;
use Mockery;
use Illuminate\Support\Str;

class RomantauschControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function actingMember(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Mitglied->value]);
        return $user;
    }

    private function putBookData(): void
    {
        Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
            'type' => BookType::MaddraxDieDunkleZukunftDerErde,
        ]);
        Book::create([
            'roman_number' => 2,
            'title' => 'MM Roman',
            'author' => 'Author',
            'type' => BookType::MissionMars,
        ]);
    }

    public function test_complete_swap_marks_entries_completed(): void
    {
        $this->putBookData();
        $user = $this->actingMember();
        $other = User::factory()->create();
        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);
        $request = BookRequest::create([
            'user_id' => $other->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'gebraucht',
        ]);

        $this->actingAs($user);
        $this->post("/romantauschboerse/{$offer->id}/{$request->id}/abschliessen")
            ->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseHas('book_swaps', [
            'offer_id' => $offer->id,
            'request_id' => $request->id,
        ]);
        $this->assertEquals(1, (int) $offer->fresh()->completed);
        $this->assertEquals(1, (int) $request->fresh()->completed);
    }

    public function test_create_offer_loads_books_from_database(): void
    {
        $this->putBookData();

        $this->actingAs($this->actingMember());
        $response = $this->get('/romantauschboerse/angebot-erstellen');

        $response->assertOk();
        $response->assertViewIs('romantausch.create_offer');
        $this->assertSame('Roman1', $response->viewData('books')->first()->title);
    }

    public function test_store_offer_creates_entry_when_book_found(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->post('/romantauschboerse/angebot-speichern', [
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'condition' => 'neu',
        ]);

        $response->assertRedirect(route('romantausch.index', [], false));
        $this->assertDatabaseHas('book_offers', [
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);
    }

    public function test_store_offer_creates_entry_for_mission_mars(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->post('/romantauschboerse/angebot-speichern', [
            'series' => BookType::MissionMars->value,
            'book_number' => 2,
            'condition' => 'neu',
        ]);

        $response->assertRedirect(route('romantausch.index', [], false));
        $this->assertDatabaseHas('book_offers', [
            'user_id' => $user->id,
            'series' => BookType::MissionMars->value,
            'book_number' => 2,
            'book_title' => 'MM Roman',
            'condition' => 'neu',
        ]);
    }

    public function test_point_awarded_on_every_tenth_offer(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

        // create nine existing offers for the user
        for ($i = 1; $i <= 9; $i++) {
            BookOffer::create([
                'user_id' => $user->id,
                'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
                'book_number' => $i,
                'book_title' => 'Roman'.$i,
                'condition' => 'neu',
            ]);
        }

        $this->post('/romantauschboerse/angebot-speichern', [
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'condition' => 'neu',
        ]);

        $this->assertDatabaseCount('user_points', 1);
        $this->assertDatabaseHas('user_points', [
            'user_id' => $user->id,
            'points' => 1,
        ]);
    }

    public function test_store_offer_saves_photos(): void
    {
        $this->putBookData();
        $user = $this->actingMember();
        $this->actingAs($user);

        Storage::fake('public');

        $response = $this->post('/romantauschboerse/angebot-speichern', [
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'condition' => 'neu',
            'photos' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
            ],
        ]);

        $response->assertRedirect(route('romantausch.index', [], false));
        $offer = BookOffer::first();
        $this->assertCount(2, $offer->photos);
        Storage::disk('public')->assertExists($offer->photos[0]);
    }

    public function test_store_offer_accepts_all_allowed_photo_extensions(): void
    {
        $this->putBookData();
        $user = $this->actingMember();
        $this->actingAs($user);

        Storage::fake('public');

        foreach (RomantauschController::ALLOWED_PHOTO_EXTENSIONS as $ext) {
            $response = $this->post('/romantauschboerse/angebot-speichern', [
                'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
                'book_number' => 1,
                'condition' => 'neu',
                'photos' => [UploadedFile::fake()->image("a.$ext")],
            ]);

            $response->assertRedirect(route('romantausch.index', [], false));
        }
    }

    public function test_store_offer_rejects_invalid_photo_extension(): void
    {
        $this->putBookData();
        $user = $this->actingMember();
        $this->actingAs($user);

        Storage::fake('public');

        $response = $this->from(route('romantausch.create-offer', [], false))->post('/romantauschboerse/angebot-speichern', [
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'condition' => 'neu',
            'photos' => [
                UploadedFile::fake()->create('evil.txt', 10, 'text/plain'),
            ],
        ]);

        $response->assertRedirect(route('romantausch.create-offer', [], false));
        $response->assertSessionHasErrors('photos.0');
        $this->assertDatabaseCount('book_offers', 0);
        $this->assertCount(0, Storage::disk('public')->allFiles());
    }

    public function test_store_offer_sanitizes_photo_filenames(): void
    {
        $this->putBookData();
        $user = $this->actingMember();
        $this->actingAs($user);

        Storage::fake('public');

        $response = $this->post('/romantauschboerse/angebot-speichern', [
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'condition' => 'neu',
            'photos' => [UploadedFile::fake()->image('räum lich!.JPG')],
        ]);

        $response->assertRedirect(route('romantausch.index', [], false));
        $offer = BookOffer::first();
        $this->assertCount(1, $offer->photos);
        $path = $offer->photos[0];
        $expectedSlug = Str::slug('räum lich!');
        $this->assertMatchesRegularExpression("/^book-offers\/{$expectedSlug}-[0-9a-f\-]{36}\.jpg$/", $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_store_offer_uses_fallback_name_when_slug_empty(): void
    {
        $this->putBookData();
        $user = $this->actingMember();
        $this->actingAs($user);

        Storage::fake('public');

        $response = $this->post('/romantauschboerse/angebot-speichern', [
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'condition' => 'neu',
            'photos' => [UploadedFile::fake()->image('!!!.png')],
        ]);

        $response->assertRedirect(route('romantausch.index', [], false));
        $offer = BookOffer::first();
        $this->assertCount(1, $offer->photos);
        $path = $offer->photos[0];
        $this->assertMatchesRegularExpression('/^book-offers\/photo-[0-9a-f\-]{36}\.png$/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_store_offer_handles_photo_upload_failure(): void
    {
        $this->putBookData();
        $user = $this->actingMember();
        $this->actingAs($user);

        Storage::fake('public');

        $first = UploadedFile::fake()->image('a.jpg');
        $failingFile = UploadedFile::fake()->image('b.jpg');
        $failing = Mockery::mock($failingFile)->makePartial();
        $failing->shouldReceive('storeAs')->once()->andThrow(new \Exception('fail'));

        $response = $this->from(route('romantausch.create-offer', [], false))->post('/romantauschboerse/angebot-speichern', [
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'condition' => 'neu',
            'photos' => [$first, $failing],
        ]);

        $response->assertRedirect(route('romantausch.create-offer', [], false));
        $response->assertSessionHas('error', 'Foto-Upload fehlgeschlagen. Bitte versuche es erneut.');
        $this->assertDatabaseCount('book_offers', 0);
        $this->assertCount(0, Storage::disk('public')->allFiles());

        $this->get(route('romantausch.create-offer', [], false))
            ->assertSee('Foto-Upload fehlgeschlagen. Bitte versuche es erneut.');
    }

    public function test_offer_detail_view_requires_match(): void
    {
        $this->putBookData();
        $offerUser = $this->actingMember();
        $requestUser = User::factory()->create();

        $offer = BookOffer::create([
            'user_id' => $offerUser->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        $request = BookRequest::create([
            'user_id' => $requestUser->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'gebraucht',
        ]);

        BookSwap::create([
            'offer_id' => $offer->id,
            'request_id' => $request->id,
        ]);

        $this->actingAs($requestUser);
        $this->get(route('romantausch.show-offer', $offer))->assertOk();

        $other = User::factory()->create();
        $this->actingAs($other);
        $this->get(route('romantausch.show-offer', $offer))->assertForbidden();
    }

    public function test_offer_owner_can_view_offer_without_swap(): void
    {
        $this->putBookData();
        $user = $this->actingMember();
        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        $other = User::factory()->create();

        $this->actingAs($user);
        $this->get(route('romantausch.show-offer', $offer))->assertOk();

        $this->actingAs($other);
        $this->get(route('romantausch.show-offer', $offer))->assertForbidden();
    }

    public function test_offer_detail_handles_swap_with_missing_request(): void
    {
        $this->putBookData();
        $offerUser = $this->actingMember();
        $otherUser = User::factory()->create();

        $offer = BookOffer::create([
            'user_id' => $offerUser->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        $swap = new BookSwap(['offer_id' => $offer->id, 'request_id' => 999]);
        $swap->setRelation('request', null);
        $offer->setRelation('swap', $swap);

        $this->actingAs($offerUser);
        $response = app(RomantauschController::class)->showOffer($offer);
        $this->assertInstanceOf(View::class, $response);

        $this->actingAs($otherUser);
        $this->expectException(HttpException::class);
        app(RomantauschController::class)->showOffer($offer);
    }

    public function test_store_offer_returns_error_when_book_missing(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->from('/romantauschboerse/angebot-erstellen')
            ->post('/romantauschboerse/angebot-speichern', [
                'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
                'book_number' => 2,
                'condition' => 'neu',
            ]);

        $response->assertRedirect('/romantauschboerse/angebot-erstellen');
        $response->assertSessionHas('error', 'Ausgewählter Roman nicht gefunden.');
        $this->assertDatabaseCount('book_offers', 0);
    }

    public function test_index_displays_offers_requests_and_swaps(): void
    {
        $this->putBookData();
        $user = $this->actingMember();
        $other = User::factory()->create();

        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        $request = BookRequest::create([
            'user_id' => $other->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'gebraucht',
        ]);

        $swap = \App\Models\BookSwap::create([
            'offer_id' => $offer->id,
            'request_id' => $request->id,
            'completed_at' => now(),
        ]);

        $this->actingAs($user);
        $response = $this->get('/romantauschboerse');

        $response->assertOk();
        $response->assertViewIs('romantausch.index');
        $this->assertTrue($response->viewData('offers')->isEmpty());
        $this->assertTrue($response->viewData('requests')->isEmpty());
        $this->assertTrue($response->viewData('activeSwaps')->isEmpty());
        $this->assertTrue($response->viewData('completedSwaps')->first()->is($swap));
    }

    public function test_create_request_loads_books_from_database(): void
    {
        $this->putBookData();

        $this->actingAs($this->actingMember());
        $response = $this->get('/romantauschboerse/anfrage-erstellen');

        $response->assertOk();
        $response->assertViewIs('romantausch.create_request');
        $this->assertSame('Roman1', $response->viewData('books')->first()->title);
    }

    public function test_store_request_creates_entry_when_book_found(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->post('/romantauschboerse/anfrage-speichern', [
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'condition' => 'neu',
        ]);

        $response->assertRedirect(route('romantausch.index', [], false));
        $this->assertDatabaseHas('book_requests', [
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);
    }

    public function test_store_request_creates_entry_for_mission_mars(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->post('/romantauschboerse/anfrage-speichern', [
            'series' => BookType::MissionMars->value,
            'book_number' => 2,
            'condition' => 'neu',
        ]);

        $response->assertRedirect(route('romantausch.index', [], false));
        $this->assertDatabaseHas('book_requests', [
            'user_id' => $user->id,
            'series' => BookType::MissionMars->value,
            'book_number' => 2,
            'book_title' => 'MM Roman',
            'condition' => 'neu',
        ]);
    }

    public function test_store_request_returns_error_when_book_missing(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->from('/romantauschboerse/anfrage-erstellen')
            ->post('/romantauschboerse/anfrage-speichern', [
                'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
                'book_number' => 2,
                'condition' => 'neu',
            ]);

        $response->assertRedirect('/romantauschboerse/anfrage-erstellen');
        $response->assertSessionHas('error', 'Ausgewählter Roman nicht gefunden.');
        $this->assertDatabaseCount('book_requests', 0);
    }

    public function test_user_can_delete_own_offer(): void
    {
        $user = $this->actingMember();
        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        $this->actingAs($user);
        $this->post("/romantauschboerse/{$offer->id}/angebot-loeschen")
            ->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseMissing('book_offers', ['id' => $offer->id]);
    }

    public function test_user_cannot_delete_offer_of_other_user(): void
    {
        $user = $this->actingMember();
        $other = User::factory()->create();
        $offer = BookOffer::create([
            'user_id' => $other->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        $this->actingAs($user);
        $this->post("/romantauschboerse/{$offer->id}/angebot-loeschen")->assertForbidden();

        $this->assertDatabaseHas('book_offers', ['id' => $offer->id]);
    }

    public function test_user_can_delete_own_request(): void
    {
        $user = $this->actingMember();
        $request = BookRequest::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        $this->actingAs($user);
        $this->post("/romantauschboerse/{$request->id}/anfrage-loeschen")
            ->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseMissing('book_requests', ['id' => $request->id]);
    }

    public function test_user_cannot_delete_request_of_other_user(): void
    {
        $user = $this->actingMember();
        $other = User::factory()->create();
        $request = BookRequest::create([
            'user_id' => $other->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        $this->actingAs($user);
        $this->post("/romantauschboerse/{$request->id}/anfrage-loeschen")->assertForbidden();

        $this->assertDatabaseHas('book_requests', ['id' => $request->id]);
    }
}
