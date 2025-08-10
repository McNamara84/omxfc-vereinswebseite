<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Mail\BookSwapMatched;
use Illuminate\Support\Facades\Mail;
use App\Models\Team;

class BookSwapProcessTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Mitglied']);
        return $user;
    }

    public function test_match_created_and_mail_sent_when_offer_added(): void
    {
        Mail::fake();

        $requestUser = $this->createMember();
        $offerUser = $this->createMember();

        $request = BookRequest::create([
            'user_id' => $requestUser->id,
            'series' => 'Series',
            'book_number' => 1,
            'book_title' => 'Title',
            'condition' => 'neu',
        ]);

        $this->actingAs($offerUser);
        $this->post(route('romantausch.store-offer'), [
            'book_number' => 1,
            'condition' => 'neu',
        ]);

        $this->assertDatabaseCount('book_swaps', 1);
        $swap = BookSwap::first();
        $this->assertEquals($request->id, $swap->request_id);

        Mail::assertQueued(BookSwapMatched::class);
    }

    public function test_confirmations_complete_swap_and_award_points(): void
    {
        $offerUser = $this->createMember();
        $requestUser = $this->createMember();

        $offer = BookOffer::create([
            'user_id' => $offerUser->id,
            'series' => 'Series',
            'book_number' => 1,
            'book_title' => 'Title',
            'condition' => 'neu',
        ]);

        $request = BookRequest::create([
            'user_id' => $requestUser->id,
            'series' => 'Series',
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
