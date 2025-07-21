<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\BookRequest;
use App\Models\BookOffer;
use App\Models\BookSwap;

class BookRequestModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_creates_valid_request(): void
    {
        $request = BookRequest::factory()->create();

        $this->assertDatabaseHas('book_requests', [
            'id' => $request->id,
            'completed' => false,
        ]);
        $this->assertInstanceOf(User::class, $request->user);
    }

    public function test_mass_assignment_sets_attributes(): void
    {
        $user = User::factory()->create();
        $request = BookRequest::create([
            'user_id' => $user->id,
            'series' => 'Test Series',
            'book_number' => 42,
            'book_title' => 'Test Title',
            'condition' => 'neu',
            'id' => 999,
        ]);

        $request->refresh();

        $this->assertNotEquals(999, $request->id);
        $this->assertEquals('Test Series', $request->series);
        $this->assertFalse((bool) $request->completed);
    }

    public function test_request_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $request = BookRequest::factory()->for($user)->create();

        $this->assertTrue($request->user->is($user));
    }

    public function test_request_has_one_swap(): void
    {
        $requestUser = User::factory()->create();
        $offerUser = User::factory()->create();

        $offer = BookOffer::create([
            'user_id' => $offerUser->id,
            'series' => 'Series',
            'book_number' => 1,
            'book_title' => 'Title',
            'condition' => 'neu',
        ]);

        $request = BookRequest::factory()->for($requestUser)->create();

        $swap = BookSwap::create([
            'offer_id' => $offer->id,
            'request_id' => $request->id,
        ]);

        $this->assertTrue($request->swap->is($swap));
        $this->assertTrue($swap->request->is($request));
    }
}
