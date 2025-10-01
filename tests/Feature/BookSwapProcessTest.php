<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\Book;
use App\Mail\BookSwapMatched;
use Illuminate\Support\Facades\Mail;
use App\Models\Team;
use App\Enums\BookType;

class BookSwapProcessTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Mitglied->value]);
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
        $response = $this->post(route('romantausch.store-offer'), [
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'condition' => 'neu',
        ]);

        $response->assertRedirect(route('romantausch.index'));
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

        $this->actingAs($userA)->post(route('romantausch.store-request'), [
            'series' => $bookTwo->type->value,
            'book_number' => $bookTwo->roman_number,
            'condition' => 'gut',
        ])->assertRedirect(route('romantausch.index'));

        $this->actingAs($userB)->post(route('romantausch.store-request'), [
            'series' => $bookOne->type->value,
            'book_number' => $bookOne->roman_number,
            'condition' => 'gut',
        ])->assertRedirect(route('romantausch.index'));

        $this->actingAs($userB)->post(route('romantausch.store-offer'), [
            'series' => $bookTwo->type->value,
            'book_number' => $bookTwo->roman_number,
            'condition' => 'gut',
        ])->assertRedirect(route('romantausch.index'));

        $this->actingAs($userA)->post(route('romantausch.store-offer'), [
            'series' => $bookOne->type->value,
            'book_number' => $bookOne->roman_number,
            'condition' => 'gut',
        ])->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseCount('book_swaps', 2);

        $userASwap = BookSwap::whereHas('request', fn ($query) => $query->where('user_id', $userA->id))->first();
        $userBSwap = BookSwap::whereHas('request', fn ($query) => $query->where('user_id', $userB->id))->first();

        $this->assertNotNull($userASwap);
        $this->assertNotNull($userBSwap);

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

        $this->actingAs($offerUser)->post(route('romantausch.confirm-swap', $swap));
        $this->actingAs($requestUser)->post(route('romantausch.confirm-swap', $swap));

        $swap->refresh();
        $this->assertNotNull($swap->completed_at);
        $this->assertTrue((bool) $offer->fresh()->completed);
        $this->assertTrue((bool) $request->fresh()->completed);
        $this->assertDatabaseCount('user_points', 2);
    }
}
