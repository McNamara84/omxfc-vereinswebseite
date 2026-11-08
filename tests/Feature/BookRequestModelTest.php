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

test('factory creates valid request', function (): void {
    $request = BookRequest::factory()->create();
    $this->assertDatabaseHas('book_requests', ['id' => $request->id, 'completed' => false]);
    expect($request->user)->toBeInstanceOf(User::class);
});

test('mass assignment sets attributes', function (): void {
    $user = User::factory()->create();
    $request = BookRequest::create(['user_id' => $user->id, 'series' => 'Test Series', 'book_number' => 42, 'book_title' => 'Test Title', 'condition' => 'neu', 'id' => 999]);
    $request->refresh();
    expect($request->id)->not->toEqual(999)
        ->and($request->series)->toEqual('Test Series')
        ->and($request->completed)->toBeFalsy();
});

test('request belongs to user', function (): void {
    $user = User::factory()->create();
    $request = BookRequest::factory()->for($user)->create();
    expect($request->user->is($user))->toBeTrue();
});

test('request has one swap', function (): void {
    $requestUser = User::factory()->create();
    $offerUser = User::factory()->create();
    $offer = BookOffer::create(['user_id' => $offerUser->id, 'series' => 'Series', 'book_number' => 1, 'book_title' => 'Title', 'condition' => 'neu']);
    $request = BookRequest::factory()->for($requestUser)->create();
    $swap = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
    expect($request->swap->is($swap))->toBeTrue()
        ->and($swap->request->is($request))->toBeTrue();
});
