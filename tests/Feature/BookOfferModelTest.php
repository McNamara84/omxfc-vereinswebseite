<?php

namespace Tests\Feature;

use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Enums\BookType;

class BookOfferModelTest extends TestCase
{
    use RefreshDatabase;

    private function createMember(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Mitglied->value]);

        return $user;
    }

    public function test_book_offer_can_be_created(): void
    {
        $user = $this->createMember();

        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        $this->assertDatabaseHas('book_offers', [
            'id' => $offer->id,
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
            'completed' => false,
        ]);
    }

    public function test_book_offer_belongs_to_a_user(): void
    {
        $user = $this->createMember();
        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => 'Series',
            'book_number' => 1,
            'book_title' => 'Title',
            'condition' => 'good',
        ]);

        $this->assertTrue($offer->user->is($user));
    }

    public function test_book_offer_has_one_swap(): void
    {
        $user = $this->createMember();
        $other = User::factory()->create();

        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => 'Series',
            'book_number' => 1,
            'book_title' => 'Title',
            'condition' => 'good',
        ]);

        $request = BookRequest::create([
            'user_id' => $other->id,
            'series' => 'Series',
            'book_number' => 1,
            'book_title' => 'Title',
            'condition' => 'good',
        ]);

        $swap = BookSwap::create([
            'offer_id' => $offer->id,
            'request_id' => $request->id,
            'completed_at' => now(),
        ]);

        $this->assertTrue($offer->swap->is($swap));
    }

    public function test_completed_attribute_can_be_updated(): void
    {
        $user = $this->createMember();
        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => 'Series',
            'book_number' => 1,
            'book_title' => 'Title',
            'condition' => 'good',
        ]);

        $offer->update(['completed' => true]);
        $offer->refresh();

        $this->assertTrue((bool) $offer->completed);
    }
}
