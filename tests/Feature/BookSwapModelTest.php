<?php

namespace Tests\Feature;

use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookSwapModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_book_swap_can_be_created_and_relations_work(): void
    {
        $offerUser = User::factory()->create();
        $requestUser = User::factory()->create();

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
            'completed_at' => now(),
        ]);

        $this->assertDatabaseHas('book_swaps', [
            'id' => $swap->id,
            'offer_id' => $offer->id,
            'request_id' => $request->id,
        ]);
        $this->assertTrue($swap->offer->is($offer));
        $this->assertTrue($swap->request->is($request));
        $this->assertNotNull($swap->completed_at);
    }
}
