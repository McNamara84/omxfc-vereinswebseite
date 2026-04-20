<?php

namespace Tests\Feature;

use App\Enums\BookType;
use App\Enums\Role;
use App\Livewire\RomantauschIndex;
use App\Livewire\RomantauschOfferForm;
use App\Livewire\RomantauschRequestForm;
use App\Mail\BookSwapMatched;
use App\Models\Book;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\Team;
use App\Models\User;
use App\Services\Romantausch\SwapMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class BookSwapProcessTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        return $user;
    }

    public function test_offer_does_not_create_match_without_reciprocal_entries(): void
    {
        Mail::fake();

        $requestUser = $this->createMember();
        $offerUser = $this->createMember();

        Book::create([
            'roman_number' => 1,
            'title' => 'Title',
            'author' => 'Author',
            'type' => BookType::MaddraxDieDunkleZukunftDerErde,
        ]);

        $request = BookRequest::create([
            'user_id' => $requestUser->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Title',
            'condition' => 'neu',
        ]);

        $this->actingAs($offerUser);

        Livewire::test(RomantauschOfferForm::class)
            ->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)
            ->set('book_number', 1)
            ->set('condition', 'neu')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseCount('book_swaps', 0);
        Mail::assertNothingQueued();
    }

    public function test_reciprocal_match_creates_two_swaps_and_notifies_both_users(): void
    {
        Mail::fake();

        $userA = $this->createMember();
        $userB = $this->createMember();

        $bookOne = Book::create([
            'roman_number' => 1,
            'title' => 'Roman Eins',
            'author' => 'Autor Eins',
            'type' => BookType::MaddraxDieDunkleZukunftDerErde,
        ]);

        $bookTwo = Book::create([
            'roman_number' => 2,
            'title' => 'Roman Zwei',
            'author' => 'Autor Zwei',
            'type' => BookType::MaddraxDieDunkleZukunftDerErde,
        ]);

        $this->actingAs($userA);
        Livewire::test(RomantauschRequestForm::class)
            ->set('series', $bookTwo->type->value)
            ->set('book_number', $bookTwo->roman_number)
            ->set('condition', 'gut')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $this->actingAs($userB);
        Livewire::test(RomantauschRequestForm::class)
            ->set('series', $bookOne->type->value)
            ->set('book_number', $bookOne->roman_number)
            ->set('condition', 'gut')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $this->actingAs($userB);
        Livewire::test(RomantauschOfferForm::class)
            ->set('series', $bookTwo->type->value)
            ->set('book_number', $bookTwo->roman_number)
            ->set('condition', 'gut')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $this->actingAs($userA);
        Livewire::test(RomantauschOfferForm::class)
            ->set('series', $bookOne->type->value)
            ->set('book_number', $bookOne->roman_number)
            ->set('condition', 'gut')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseCount('book_swaps', 2);

        $userASwap = BookSwap::whereHas('request', fn ($query) => $query->where('user_id', $userA->id))->first();
        $userBSwap = BookSwap::whereHas('request', fn ($query) => $query->where('user_id', $userB->id))->first();

        $this->assertNotNull($userASwap);
        $this->assertNotNull($userBSwap);

        Mail::assertQueued(BookSwapMatched::class, 2);
    }

    public function test_reciprocal_matching_uses_key_intersection_for_multiple_candidates(): void
    {
        Mail::fake();

        $userA = $this->createMember();
        $userB = $this->createMember();

        $bookOne = Book::create([
            'roman_number' => 1,
            'title' => 'Roman Eins',
            'author' => 'Autor Eins',
            'type' => BookType::MaddraxDieDunkleZukunftDerErde,
        ]);

        $bookTwo = Book::create([
            'roman_number' => 2,
            'title' => 'Roman Zwei',
            'author' => 'Autor Zwei',
            'type' => BookType::MaddraxDieDunkleZukunftDerErde,
        ]);

        $bookThree = Book::create([
            'roman_number' => 3,
            'title' => 'Roman Drei',
            'author' => 'Autor Drei',
            'type' => BookType::MaddraxDieDunkleZukunftDerErde,
        ]);

        BookRequest::create([
            'user_id' => $userA->id,
            'series' => $bookTwo->type->value,
            'book_number' => $bookTwo->roman_number,
            'book_title' => $bookTwo->title,
            'condition' => 'gut',
        ]);

        BookRequest::create([
            'user_id' => $userA->id,
            'series' => $bookThree->type->value,
            'book_number' => $bookThree->roman_number,
            'book_title' => $bookThree->title,
            'condition' => 'gut',
        ]);

        $offerForBookTwo = BookOffer::create([
            'user_id' => $userB->id,
            'series' => $bookTwo->type->value,
            'book_number' => $bookTwo->roman_number,
            'book_title' => $bookTwo->title,
            'condition' => 'gut',
        ]);

        $offerForBookThree = BookOffer::create([
            'user_id' => $userB->id,
            'series' => $bookThree->type->value,
            'book_number' => $bookThree->roman_number,
            'book_title' => $bookThree->title,
            'condition' => 'gut',
        ]);

        $this->actingAs($userB);
        Livewire::test(RomantauschRequestForm::class)
            ->set('series', $bookOne->type->value)
            ->set('book_number', $bookOne->roman_number)
            ->set('condition', 'gut')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $this->actingAs($userA);
        Livewire::test(RomantauschOfferForm::class)
            ->set('series', $bookOne->type->value)
            ->set('book_number', $bookOne->roman_number)
            ->set('condition', 'gut')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseCount('book_swaps', 2);

        $swaps = BookSwap::with(['offer', 'request'])->get();

        $this->assertTrue($swaps->contains(fn ($swap) => $swap->offer->user_id === $userB->id && in_array($swap->offer->book_number, [$bookTwo->roman_number, $bookThree->roman_number], true)));
        $this->assertTrue($swaps->contains(fn ($swap) => $swap->request->user_id === $userB->id && $swap->offer->user_id === $userA->id));
        $this->assertTrue($swaps->contains(fn ($swap) => $swap->offer->user_id === $userB->id && $swap->request->user_id === $userA->id));

        Mail::assertQueued(BookSwapMatched::class, 2);
    }

    public function test_reciprocal_matching_handles_series_with_pipe_character(): void
    {
        Mail::fake();

        $userA = $this->createMember();
        $userB = $this->createMember();

        $series = 'Custom|Series';

        // UserB erstellt zunächst ein Gesuch für Buch 1
        $request = BookRequest::create([
            'user_id' => $userB->id,
            'series' => $series,
            'book_number' => 1,
            'book_title' => 'Custom Request',
            'condition' => 'gut',
        ]);

        // UserA erstellt ein Gesuch für Buch 2
        $reciprocalRequest = BookRequest::create([
            'user_id' => $userA->id,
            'series' => $series,
            'book_number' => 2,
            'book_title' => 'Custom Follow-Up',
            'condition' => 'gut',
        ]);

        // UserB erstellt ein Angebot für Buch 2 (erfüllt UserA's Gesuch)
        $reciprocalOffer = BookOffer::create([
            'user_id' => $userB->id,
            'series' => $series,
            'book_number' => 2,
            'book_title' => 'Custom Reciprocal',
            'condition' => 'gut',
        ]);

        // Jetzt erstellt UserA ein Angebot für Buch 1 (erfüllt UserB's Gesuch)
        // Dies sollte das reziproke Matching auslösen
        $offer = BookOffer::create([
            'user_id' => $userA->id,
            'series' => $series,
            'book_number' => 1,
            'book_title' => 'Custom Offer',
            'condition' => 'gut',
        ]);

        // Führe Matching über öffentliche API aus
        $service = app(SwapMatchingService::class);
        $service->matchSwap($offer, 'offer');

        $this->assertDatabaseCount('book_swaps', 2);

        $this->assertDatabaseHas('book_swaps', [
            'offer_id' => $offer->id,
            'request_id' => $request->id,
        ]);

        $this->assertDatabaseHas('book_swaps', [
            'offer_id' => $reciprocalOffer->id,
            'request_id' => $reciprocalRequest->id,
        ]);

        Mail::assertQueued(BookSwapMatched::class, 2);
    }

    public function test_confirmations_complete_swap_and_award_points(): void
    {
        $offerUser = $this->createMember();
        $requestUser = $this->createMember();

        $offer = BookOffer::create([
            'user_id' => $offerUser->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Title',
            'condition' => 'neu',
        ]);

        $request = BookRequest::create([
            'user_id' => $requestUser->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Title',
            'condition' => 'neu',
        ]);

        $swap = BookSwap::create([
            'offer_id' => $offer->id,
            'request_id' => $request->id,
        ]);

        $this->actingAs($offerUser);
        Livewire::test(RomantauschIndex::class)
            ->call('confirmSwap', $swap->id);

        $this->actingAs($requestUser);
        Livewire::test(RomantauschIndex::class)
            ->call('confirmSwap', $swap->id);

        $swap->refresh();
        $this->assertNotNull($swap->completed_at);
        $this->assertTrue((bool) $offer->fresh()->completed);
        $this->assertTrue((bool) $request->fresh()->completed);
        $this->assertDatabaseCount('user_points', 2);
    }
}
