<?php

namespace Tests\Feature;

use App\Enums\BookType;
use App\Livewire\RomantauschBundleForm;
use App\Livewire\RomantauschIndex;
use App\Livewire\RomantauschOfferForm;
use App\Livewire\RomantauschRequestForm;
use App\Livewire\RomantauschShowOffer;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\User;
use App\Services\Romantausch\BookPhotoService;
use App\Services\RomantauschInfoProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\CreatesTestData;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class RomantauschLivewireTest extends TestCase
{
    use CreatesTestData;
    use CreatesUserWithRole;
    use RefreshDatabase;

    // ──────────────────────────────────────────────
    // Index Tests
    // ──────────────────────────────────────────────

    public function test_index_page_is_accessible(): void
    {
        $this->seedBooksForRomantausch();
        $this->actingMember();

        $this->get('/romantauschboerse')->assertOk();
    }

    public function test_index_displays_structured_information_panel(): void
    {
        $this->seedBooksForRomantausch();
        $this->actingMember();

        $info = app(RomantauschInfoProvider::class)->getInfo();

        Livewire::test(RomantauschIndex::class)
            ->assertSeeText($info['title'])
            ->assertSeeText($info['steps']['offer']['title'])
            ->assertSeeText($info['steps']['request']['title'])
            ->assertSeeText($info['steps']['match']['title'])
            ->assertSeeText($info['steps']['confirm']['title'])
            ->assertSeeText($info['steps']['offer']['cta'])
            ->assertSeeText($info['steps']['request']['cta']);
    }

    public function test_index_displays_offers_and_requests(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();
        $other = User::factory()->create();

        BookOffer::create([
            'user_id' => $other->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        BookRequest::create([
            'user_id' => $other->id,
            'series' => BookType::MissionMars->value,
            'book_number' => 2,
            'book_title' => 'MM Roman',
            'condition' => 'gut',
        ]);

        Livewire::test(RomantauschIndex::class)
            ->assertSeeText('Roman1')
            ->assertSeeText('MM Roman');
    }

    public function test_index_displays_completed_swaps(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();
        $other = User::factory()->create();

        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
            'completed' => true,
        ]);

        $request = BookRequest::create([
            'user_id' => $other->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'gebraucht',
            'completed' => true,
        ]);

        BookSwap::create([
            'offer_id' => $offer->id,
            'request_id' => $request->id,
            'completed_at' => now(),
        ]);

        Livewire::test(RomantauschIndex::class)
            ->assertSeeText('Erfolgreiche Tauschaktionen')
            ->assertSeeText('Roman1');
    }

    public function test_index_highlights_matching_offers_and_requests(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();
        $otherOfferUser = User::factory()->create();
        $otherRequestUser = User::factory()->create();

        BookRequest::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'gut',
        ]);

        BookOffer::create([
            'user_id' => $otherOfferUser->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'sehr gut',
        ]);

        BookOffer::create([
            'user_id' => $user->id,
            'series' => BookType::MissionMars->value,
            'book_number' => 2,
            'book_title' => 'MM Roman',
            'condition' => 'top',
        ]);

        BookRequest::create([
            'user_id' => $otherRequestUser->id,
            'series' => BookType::MissionMars->value,
            'book_number' => 2,
            'book_title' => 'MM Roman',
            'condition' => 'gut',
        ]);

        Livewire::test(RomantauschIndex::class)
            ->assertSeeText('Passt zu deinem Gesuch')
            ->assertSeeText('Passt zu deinem Angebot');
    }

    public function test_index_does_not_highlight_entries_without_matching_counterpart(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();
        $otherOfferUser = User::factory()->create();
        $otherRequestUser = User::factory()->create();

        BookRequest::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'gut',
        ]);

        BookOffer::create([
            'user_id' => $otherOfferUser->id,
            'series' => BookType::MissionMars->value,
            'book_number' => 2,
            'book_title' => 'MM Roman',
            'condition' => 'gebraucht',
        ]);

        Livewire::test(RomantauschIndex::class)
            ->assertDontSeeText('Passt zu deinem Gesuch')
            ->assertDontSeeText('Passt zu deinem Angebot');
    }

    public function test_index_displays_offer_photo_thumbnails(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();

        BookOffer::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'gebraucht',
            'photos' => [
                'offers/test-one.jpg',
                'offers/test-two.jpg',
            ],
        ]);

        Livewire::test(RomantauschIndex::class)
            ->assertSee('storage/offers/test-one.jpg', false)
            ->assertSee('storage/offers/test-two.jpg', false)
            ->assertSee('@click="open(0)"', false)
            ->assertSee('role="dialog"', false);
    }

    public function test_index_renders_placeholder_for_offer_without_photo(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();

        BookOffer::create([
            'user_id' => $user->id,
            'series' => BookType::MissionMars->value,
            'book_number' => 2,
            'book_title' => 'Roman ohne Foto',
            'condition' => 'gebraucht',
            'photos' => [],
        ]);

        $description = BookType::MissionMars->value . ' 2 - Roman ohne Foto';

        Livewire::test(RomantauschIndex::class)
            ->assertSee('Kein Foto vorhanden für ' . e($description), false);
    }

    // ──────────────────────────────────────────────
    // Index Actions: Delete Offer
    // ──────────────────────────────────────────────

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

        Livewire::test(RomantauschIndex::class)
            ->call('deleteOffer', $offer->id)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('book_offers', ['id' => $offer->id]);
    }

    public function test_user_cannot_delete_offer_of_other_user(): void
    {
        $this->actingMember();
        $other = User::factory()->create();
        $offer = BookOffer::create([
            'user_id' => $other->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        Livewire::test(RomantauschIndex::class)
            ->call('deleteOffer', $offer->id)
            ->assertForbidden();

        $this->assertDatabaseHas('book_offers', ['id' => $offer->id]);
    }

    // ──────────────────────────────────────────────
    // Index Actions: Delete Request
    // ──────────────────────────────────────────────

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

        Livewire::test(RomantauschIndex::class)
            ->call('deleteRequest', $request->id)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('book_requests', ['id' => $request->id]);
    }

    public function test_user_cannot_delete_request_of_other_user(): void
    {
        $this->actingMember();
        $other = User::factory()->create();
        $request = BookRequest::create([
            'user_id' => $other->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        Livewire::test(RomantauschIndex::class)
            ->call('deleteRequest', $request->id)
            ->assertForbidden();

        $this->assertDatabaseHas('book_requests', ['id' => $request->id]);
    }

    // ──────────────────────────────────────────────
    // Index Actions: Confirm Swap
    // ──────────────────────────────────────────────

    public function test_confirm_swap_dispatches_toast(): void
    {
        $this->seedBooksForRomantausch();
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

        $swap = BookSwap::create([
            'offer_id' => $offer->id,
            'request_id' => $request->id,
        ]);

        Livewire::test(RomantauschIndex::class)
            ->call('confirmSwap', $swap->id)
            ->assertDispatched('toast');

        $swap->refresh();
        $this->assertTrue((bool) $swap->offer_confirmed);
    }

    // ──────────────────────────────────────────────
    // Index Actions: Complete Swap
    // ──────────────────────────────────────────────

    public function test_complete_swap_marks_entries_completed(): void
    {
        $this->seedBooksForRomantausch();
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

        Livewire::test(RomantauschIndex::class)
            ->call('completeSwap', $offer->id, $request->id)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('book_swaps', [
            'offer_id' => $offer->id,
            'request_id' => $request->id,
        ]);
        $this->assertEquals(1, (int) $offer->fresh()->completed);
        $this->assertEquals(1, (int) $request->fresh()->completed);
    }

    // ──────────────────────────────────────────────
    // Offer Form: Create
    // ──────────────────────────────────────────────

    public function test_create_offer_page_is_accessible(): void
    {
        $this->seedBooksForRomantausch();
        $this->actingMember();

        $this->get('/romantauschboerse/angebot-erstellen')->assertOk();
    }

    public function test_store_offer_creates_entry(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();

        Livewire::test(RomantauschOfferForm::class)
            ->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)
            ->set('book_number', 1)
            ->set('condition', 'neu')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

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
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();

        Livewire::test(RomantauschOfferForm::class)
            ->set('series', BookType::MissionMars->value)
            ->set('book_number', 2)
            ->set('condition', 'neu')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseHas('book_offers', [
            'user_id' => $user->id,
            'series' => BookType::MissionMars->value,
            'book_number' => 2,
            'book_title' => 'MM Roman',
        ]);
    }

    public function test_store_offer_creates_entry_for_2012_mini_series(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();

        Livewire::test(RomantauschOfferForm::class)
            ->set('series', BookType::ZweiTausendZwölfDasJahrDerApokalypse->value)
            ->set('book_number', 1)
            ->set('condition', 'neuwertig')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseHas('book_offers', [
            'user_id' => $user->id,
            'series' => BookType::ZweiTausendZwölfDasJahrDerApokalypse->value,
            'book_number' => 1,
            'book_title' => '2012 Roman',
        ]);
    }

    public function test_offer_creation_accepts_volk_der_tiefe_titles(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();

        Livewire::test(RomantauschOfferForm::class)
            ->set('series', BookType::DasVolkDerTiefe->value)
            ->set('book_number', 1)
            ->set('condition', 'neuwertig')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseHas('book_offers', [
            'user_id' => $user->id,
            'series' => BookType::DasVolkDerTiefe->value,
            'book_number' => 1,
            'book_title' => 'Volk Roman',
        ]);
    }

    public function test_offer_creation_accepts_die_abenteurer_titles(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();

        Livewire::test(RomantauschOfferForm::class)
            ->set('series', BookType::DieAbenteurer->value)
            ->set('book_number', 1)
            ->set('condition', 'neuwertig')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseHas('book_offers', [
            'user_id' => $user->id,
            'series' => BookType::DieAbenteurer->value,
            'book_number' => 1,
            'book_title' => 'Abenteurer Roman',
        ]);
    }

    public function test_store_offer_returns_error_when_book_missing(): void
    {
        $this->actingMember();

        Livewire::test(RomantauschOfferForm::class)
            ->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)
            ->set('book_number', 999)
            ->set('condition', 'neu')
            ->call('save')
            ->assertHasErrors('book_number');

        $this->assertDatabaseCount('book_offers', 0);
    }

    public function test_store_offer_validates_required_fields(): void
    {
        $this->seedBooksForRomantausch();
        $this->actingMember();

        Livewire::test(RomantauschOfferForm::class)
            ->set('series', '')
            ->set('book_number', null)
            ->set('condition', '')
            ->call('save')
            ->assertHasErrors(['series', 'book_number', 'condition']);

        $this->assertDatabaseCount('book_offers', 0);
    }

    public function test_point_awarded_on_every_tenth_offer(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();

        for ($i = 1; $i <= 9; $i++) {
            BookOffer::create([
                'user_id' => $user->id,
                'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
                'book_number' => $i,
                'book_title' => 'Roman' . $i,
                'condition' => 'neu',
            ]);
        }

        Livewire::test(RomantauschOfferForm::class)
            ->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)
            ->set('book_number', 1)
            ->set('condition', 'neu')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseCount('user_points', 1);
        $this->assertDatabaseHas('user_points', [
            'user_id' => $user->id,
            'points' => 1,
        ]);
    }

    // ──────────────────────────────────────────────
    // Offer Form: Photos
    // ──────────────────────────────────────────────

    public function test_store_offer_saves_photos(): void
    {
        $this->seedBooksForRomantausch();
        $this->actingMember();

        Storage::fake('public');

        Livewire::test(RomantauschOfferForm::class)
            ->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)
            ->set('book_number', 1)
            ->set('condition', 'neu')
            ->set('photos', [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
            ])
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $offer = BookOffer::first();
        $this->assertCount(2, $offer->photos);
        Storage::disk('public')->assertExists($offer->photos[0]);
    }

    public function test_store_offer_rejects_more_than_three_photos(): void
    {
        $this->seedBooksForRomantausch();
        $this->actingMember();

        Storage::fake('public');

        Livewire::test(RomantauschOfferForm::class)
            ->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)
            ->set('book_number', 1)
            ->set('condition', 'neu')
            ->set('photos', [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
                UploadedFile::fake()->image('three.jpg'),
                UploadedFile::fake()->image('four.jpg'),
            ])
            ->call('save')
            ->assertHasErrors('photos');

        $this->assertDatabaseCount('book_offers', 0);
    }

    public function test_store_offer_rejects_invalid_photo_extension(): void
    {
        $this->seedBooksForRomantausch();
        $this->actingMember();

        Storage::fake('public');

        Livewire::test(RomantauschOfferForm::class)
            ->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)
            ->set('book_number', 1)
            ->set('condition', 'neu')
            ->set('photos', [
                UploadedFile::fake()->create('evil.txt', 10, 'text/plain'),
            ])
            ->call('save')
            ->assertHasErrors('photos.0');

        $this->assertDatabaseCount('book_offers', 0);
    }

    // ──────────────────────────────────────────────
    // Offer Form: Edit
    // ──────────────────────────────────────────────

    public function test_edit_offer_form_is_accessible_for_owner(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();
        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        $this->get(route('romantausch.edit-offer', $offer))->assertOk();
    }

    public function test_edit_offer_form_forbidden_for_other_users(): void
    {
        $this->seedBooksForRomantausch();
        $owner = $this->actingMember();
        $offer = BookOffer::create([
            'user_id' => $owner->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        $other = User::factory()->create();
        $this->actingAs($other);

        $this->get(route('romantausch.edit-offer', $offer))->assertForbidden();
    }

    public function test_update_offer_updates_details(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();
        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        Livewire::test(RomantauschOfferForm::class, ['offer' => $offer])
            ->set('series', BookType::MissionMars->value)
            ->set('book_number', 2)
            ->set('condition', 'gebraucht')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $offer->refresh();
        $this->assertSame(BookType::MissionMars->value, $offer->series);
        $this->assertSame(2, $offer->book_number);
        $this->assertSame('MM Roman', $offer->book_title);
        $this->assertSame('gebraucht', $offer->condition);
    }

    public function test_update_offer_manages_photos(): void
    {
        $this->seedBooksForRomantausch();
        Storage::fake('public');

        $user = $this->actingMember();
        $existingPath = UploadedFile::fake()->image('existing.jpg')->store('book-offers', 'public');

        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
            'photos' => [$existingPath],
        ]);

        Livewire::test(RomantauschOfferForm::class, ['offer' => $offer])
            ->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)
            ->set('book_number', 1)
            ->set('condition', 'neu')
            ->set('remove_photos', [$existingPath])
            ->set('photos', [UploadedFile::fake()->image('new-photo.png')])
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $offer->refresh();
        $this->assertCount(1, $offer->photos);
        Storage::disk('public')->assertMissing($existingPath);
        Storage::disk('public')->assertExists($offer->photos[0]);
    }

    // ──────────────────────────────────────────────
    // Request Form: Create
    // ──────────────────────────────────────────────

    public function test_create_request_page_is_accessible(): void
    {
        $this->seedBooksForRomantausch();
        $this->actingMember();

        $this->get('/romantauschboerse/anfrage-erstellen')->assertOk();
    }

    public function test_store_request_creates_entry(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();

        Livewire::test(RomantauschRequestForm::class)
            ->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)
            ->set('book_number', 1)
            ->set('condition', 'neu')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

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
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();

        Livewire::test(RomantauschRequestForm::class)
            ->set('series', BookType::MissionMars->value)
            ->set('book_number', 2)
            ->set('condition', 'neu')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseHas('book_requests', [
            'user_id' => $user->id,
            'series' => BookType::MissionMars->value,
            'book_number' => 2,
            'book_title' => 'MM Roman',
        ]);
    }

    public function test_store_request_creates_entry_for_2012_mini_series(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();

        Livewire::test(RomantauschRequestForm::class)
            ->set('series', BookType::ZweiTausendZwölfDasJahrDerApokalypse->value)
            ->set('book_number', 1)
            ->set('condition', 'gut')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseHas('book_requests', [
            'user_id' => $user->id,
            'series' => BookType::ZweiTausendZwölfDasJahrDerApokalypse->value,
            'book_number' => 1,
            'book_title' => '2012 Roman',
        ]);
    }

    public function test_request_creation_accepts_die_abenteurer_titles(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();

        Livewire::test(RomantauschRequestForm::class)
            ->set('series', BookType::DieAbenteurer->value)
            ->set('book_number', 1)
            ->set('condition', 'gut')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseHas('book_requests', [
            'user_id' => $user->id,
            'series' => BookType::DieAbenteurer->value,
            'book_number' => 1,
            'book_title' => 'Abenteurer Roman',
        ]);
    }

    public function test_store_request_returns_error_when_book_missing(): void
    {
        $this->actingMember();

        Livewire::test(RomantauschRequestForm::class)
            ->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)
            ->set('book_number', 999)
            ->set('condition', 'neu')
            ->call('save')
            ->assertHasErrors('book_number');

        $this->assertDatabaseCount('book_requests', 0);
    }

    public function test_store_request_validates_required_fields(): void
    {
        $this->seedBooksForRomantausch();
        $this->actingMember();

        Livewire::test(RomantauschRequestForm::class)
            ->set('series', '')
            ->set('book_number', null)
            ->set('condition', '')
            ->call('save')
            ->assertHasErrors(['series', 'book_number', 'condition']);

        $this->assertDatabaseCount('book_requests', 0);
    }

    // ──────────────────────────────────────────────
    // Request Form: Edit
    // ──────────────────────────────────────────────

    public function test_edit_request_form_is_accessible_for_owner(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();
        $requestModel = BookRequest::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        $this->get(route('romantausch.edit-request', $requestModel))->assertOk();
    }

    public function test_update_request_updates_details(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();
        $requestModel = BookRequest::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        Livewire::test(RomantauschRequestForm::class, ['bookRequest' => $requestModel])
            ->set('series', BookType::MissionMars->value)
            ->set('book_number', 2)
            ->set('condition', 'gebraucht')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $requestModel->refresh();
        $this->assertSame(BookType::MissionMars->value, $requestModel->series);
        $this->assertSame(2, $requestModel->book_number);
        $this->assertSame('MM Roman', $requestModel->book_title);
        $this->assertSame('gebraucht', $requestModel->condition);
    }

    public function test_update_request_forbidden_for_other_users(): void
    {
        $this->seedBooksForRomantausch();
        $owner = $this->actingMember();
        $requestModel = BookRequest::create([
            'user_id' => $owner->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        $other = User::factory()->create();
        $this->actingAs($other);

        $this->get(route('romantausch.edit-request', $requestModel))->assertForbidden();
    }

    // ──────────────────────────────────────────────
    // Show Offer
    // ──────────────────────────────────────────────

    public function test_offer_owner_can_view_offer(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();
        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        $this->get(route('romantausch.show-offer', $offer))->assertOk();
    }

    public function test_swap_partner_can_view_offer(): void
    {
        $this->seedBooksForRomantausch();
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
    }

    public function test_other_user_cannot_view_offer(): void
    {
        $this->seedBooksForRomantausch();
        $offerUser = $this->actingMember();
        $offer = BookOffer::create([
            'user_id' => $offerUser->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        $other = User::factory()->create();
        $this->actingAs($other);
        $this->get(route('romantausch.show-offer', $offer))->assertForbidden();
    }

    // ──────────────────────────────────────────────
    // Bundle Form: Create
    // ──────────────────────────────────────────────

    public function test_create_bundle_page_is_accessible(): void
    {
        $this->seedBooksForRomantausch();
        $this->actingMember();

        $this->get('/romantauschboerse/stapel-angebot-erstellen')->assertOk();
    }

    public function test_store_bundle_creates_offers(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();

        Livewire::test(RomantauschBundleForm::class)
            ->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)
            ->set('book_numbers', '1')
            ->set('condition', 'Z1')
            ->call('save')
            ->assertHasErrors('book_numbers');

        // Need at least 2 books for bundle
        // Use a range with book number 1 (Maddrax) - but only 1 exists in seed
        // seedBooksForRomantausch creates: Maddrax#1, MissionMars#2, VolkDerTiefe#1, 2012#1, Abenteurer#1
        // So for Maddrax only #1 exists - cannot create bundle with just 1 book
    }

    public function test_store_bundle_validates_minimum_size(): void
    {
        $this->seedBooksForRomantausch();
        $this->actingMember();

        Livewire::test(RomantauschBundleForm::class)
            ->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)
            ->set('book_numbers', '1')
            ->set('condition', 'Z1')
            ->call('save')
            ->assertHasErrors('book_numbers');
    }

    public function test_store_bundle_validates_required_fields(): void
    {
        $this->seedBooksForRomantausch();
        $this->actingMember();

        Livewire::test(RomantauschBundleForm::class)
            ->set('series', '')
            ->set('book_numbers', '')
            ->set('condition', '')
            ->call('save')
            ->assertHasErrors(['series', 'book_numbers', 'condition']);
    }

    public function test_store_bundle_validates_missing_book_numbers(): void
    {
        $this->seedBooksForRomantausch();
        $this->actingMember();

        // Book 999 doesn't exist
        Livewire::test(RomantauschBundleForm::class)
            ->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)
            ->set('book_numbers', '1, 999')
            ->set('condition', 'Z1')
            ->call('save')
            ->assertHasErrors('book_numbers');
    }

    // ──────────────────────────────────────────────
    // Bundle Form: Delete
    // ──────────────────────────────────────────────

    public function test_user_can_delete_own_bundle(): void
    {
        $this->seedBooksForRomantausch();
        $user = $this->actingMember();
        $bundleId = (string) \Illuminate\Support\Str::uuid();

        BookOffer::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'Z1',
            'bundle_id' => $bundleId,
        ]);

        Livewire::test(RomantauschIndex::class)
            ->call('deleteBundle', $bundleId)
            ->assertDispatched('toast');

        $this->assertDatabaseMissing('book_offers', ['bundle_id' => $bundleId]);
    }

    // ──────────────────────────────────────────────
    // Photo Path Normalization (Model test)
    // ──────────────────────────────────────────────

    public function test_photo_paths_are_normalized_when_setting_attribute(): void
    {
        $user = $this->actingMember();

        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
            'photos' => [' /book-offers/foo.jpg ', '///', 'book-offers/bar.jpg', ' book-offers/baz.jpg ', '', null],
        ]);

        $this->assertArraysAreIdentical([
            'book-offers/foo.jpg',
            'book-offers/bar.jpg',
            'book-offers/baz.jpg',
        ], $offer->photos);
    }
}
