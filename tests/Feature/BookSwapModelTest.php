<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('book swap can be created and relations work', function (): void {
    $offerUser = User::factory()->create();
    $requestUser = User::factory()->create();
    $offer = BookOffer::create(['user_id' => $offerUser->id, 'series' => 'Series', 'book_number' => 1, 'book_title' => 'Title', 'condition' => 'neu']);
    $request = BookRequest::create(['user_id' => $requestUser->id, 'series' => 'Series', 'book_number' => 1, 'book_title' => 'Title', 'condition' => 'neu']);
    $swap = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id, 'completed_at' => now()]);
    $this->assertDatabaseHas('book_swaps', ['id' => $swap->id, 'offer_id' => $offer->id, 'request_id' => $request->id]);
    expect($swap->offer->is($offer))->toBeTrue()
        ->and($swap->request->is($request))->toBeTrue()
        ->and($swap->completed_at)->not->toBeNull();
});
