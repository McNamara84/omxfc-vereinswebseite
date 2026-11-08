<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\User;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(BookOfferModelTestFixtures::class);

test('book offer can be created', function (): void {
    $user = $this->createMember();
    $offer = BookOffer::create(['user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu']);
    $this->assertDatabaseHas('book_offers', ['id' => $offer->id, 'user_id' => $user->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'book_title' => 'Roman1', 'condition' => 'neu', 'completed' => false]);
});

test('book offer belongs to a user', function (): void {
    $user = $this->createMember();
    $offer = BookOffer::create(['user_id' => $user->id, 'series' => 'Series', 'book_number' => 1, 'book_title' => 'Title', 'condition' => 'good']);
    expect($offer->user->is($user))->toBeTrue();
});

test('book offer has one swap', function (): void {
    $user = $this->createMember();
    $other = User::factory()->create();
    $offer = BookOffer::create(['user_id' => $user->id, 'series' => 'Series', 'book_number' => 1, 'book_title' => 'Title', 'condition' => 'good']);
    $request = BookRequest::create(['user_id' => $other->id, 'series' => 'Series', 'book_number' => 1, 'book_title' => 'Title', 'condition' => 'good']);
    $swap = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id, 'completed_at' => now()]);
    expect($offer->swap->is($swap))->toBeTrue();
});

test('completed attribute can be updated', function (): void {
    $user = $this->createMember();
    $offer = BookOffer::create(['user_id' => $user->id, 'series' => 'Series', 'book_number' => 1, 'book_title' => 'Title', 'condition' => 'good']);
    $offer->update(['completed' => true]);
    $offer->refresh();
    expect($offer->completed)->toBeTruthy();
});
