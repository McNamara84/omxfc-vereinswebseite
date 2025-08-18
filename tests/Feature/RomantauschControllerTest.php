<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\Book;
use App\Enums\BookType;

class RomantauschControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Mitglied']);
        return $user;
    }

    private function putBookData(): void
    {
        Book::create([
            'roman_number' => 1,
            'title' => 'Roman1',
            'author' => 'Author',
            'type' => BookType::MaddraxDieDunkleZukunftDerErde,
        ]);
    }

    public function test_complete_swap_marks_entries_completed(): void
    {
        $this->putBookData();
        $user = $this->actingMember();
        $other = User::factory()->create();
        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => 'Maddrax - Die dunkle Zukunft der Erde',
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);
        $request = BookRequest::create([
            'user_id' => $other->id,
            'series' => 'Maddrax - Die dunkle Zukunft der Erde',
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'gebraucht',
        ]);

        $this->actingAs($user);
        $this->post("/romantauschboerse/{$offer->id}/{$request->id}/abschliessen")
            ->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseHas('book_swaps', [
            'offer_id' => $offer->id,
            'request_id' => $request->id,
        ]);
        $this->assertEquals(1, (int) $offer->fresh()->completed);
        $this->assertEquals(1, (int) $request->fresh()->completed);
    }

    public function test_create_offer_loads_books_from_database(): void
    {
        $this->putBookData();

        $this->actingAs($this->actingMember());
        $response = $this->get('/romantauschboerse/angebot-erstellen');

        $response->assertOk();
        $response->assertViewIs('romantausch.create_offer');
        $this->assertSame('Roman1', $response->viewData('books')->first()->title);
    }

    public function test_store_offer_creates_entry_when_book_found(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->post('/romantauschboerse/angebot-speichern', [
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'condition' => 'neu',
        ]);

        $response->assertRedirect(route('romantausch.index', [], false));
        $this->assertDatabaseHas('book_offers', [
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);
    }

    public function test_point_awarded_on_every_tenth_offer(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

        // create nine existing offers for the user
        for ($i = 1; $i <= 9; $i++) {
            BookOffer::create([
                'user_id' => $user->id,
                'series' => 'Maddrax - Die dunkle Zukunft der Erde',
                'book_number' => $i,
                'book_title' => 'Roman'.$i,
                'condition' => 'neu',
            ]);
        }

        $this->post('/romantauschboerse/angebot-speichern', [
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'condition' => 'neu',
        ]);

        $this->assertDatabaseCount('user_points', 1);
        $this->assertDatabaseHas('user_points', [
            'user_id' => $user->id,
            'points' => 1,
        ]);
    }

    public function test_store_offer_returns_error_when_book_missing(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->from('/romantauschboerse/angebot-erstellen')
            ->post('/romantauschboerse/angebot-speichern', [
                'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
                'book_number' => 2,
                'condition' => 'neu',
            ]);

        $response->assertRedirect('/romantauschboerse/angebot-erstellen');
        $response->assertSessionHas('error', 'Ausgewählter Roman nicht gefunden.');
        $this->assertDatabaseCount('book_offers', 0);
    }

    public function test_index_displays_offers_requests_and_swaps(): void
    {
        $this->putBookData();
        $user = $this->actingMember();
        $other = User::factory()->create();

        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => 'Maddrax - Die dunkle Zukunft der Erde',
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        $request = BookRequest::create([
            'user_id' => $other->id,
            'series' => 'Maddrax - Die dunkle Zukunft der Erde',
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'gebraucht',
        ]);

        $swap = \App\Models\BookSwap::create([
            'offer_id' => $offer->id,
            'request_id' => $request->id,
            'completed_at' => now(),
        ]);

        $this->actingAs($user);
        $response = $this->get('/romantauschboerse');

        $response->assertOk();
        $response->assertViewIs('romantausch.index');
        $this->assertTrue($response->viewData('offers')->isEmpty());
        $this->assertTrue($response->viewData('requests')->isEmpty());
        $this->assertTrue($response->viewData('activeSwaps')->isEmpty());
        $this->assertTrue($response->viewData('completedSwaps')->first()->is($swap));
    }

    public function test_create_request_loads_books_from_database(): void
    {
        $this->putBookData();

        $this->actingAs($this->actingMember());
        $response = $this->get('/romantauschboerse/anfrage-erstellen');

        $response->assertOk();
        $response->assertViewIs('romantausch.create_request');
        $this->assertSame('Roman1', $response->viewData('books')->first()->title);
    }

    public function test_store_request_creates_entry_when_book_found(): void
    {
        $this->putBookData();

        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->post('/romantauschboerse/anfrage-speichern', [
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'condition' => 'neu',
        ]);

        $response->assertRedirect(route('romantausch.index', [], false));
        $this->assertDatabaseHas('book_requests', [
            'user_id' => $user->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);
    }

    public function test_store_request_returns_error_when_book_missing(): void
    {
        $user = $this->actingMember();
        $this->actingAs($user);

        $response = $this->from('/romantauschboerse/anfrage-erstellen')
            ->post('/romantauschboerse/anfrage-speichern', [
                'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
                'book_number' => 2,
                'condition' => 'neu',
            ]);

        $response->assertRedirect('/romantauschboerse/anfrage-erstellen');
        $response->assertSessionHas('error', 'Ausgewählter Roman nicht gefunden.');
        $this->assertDatabaseCount('book_requests', 0);
    }

    public function test_user_can_delete_own_offer(): void
    {
        $user = $this->actingMember();
        $offer = BookOffer::create([
            'user_id' => $user->id,
            'series' => 'Maddrax - Die dunkle Zukunft der Erde',
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        $this->actingAs($user);
        $this->post("/romantauschboerse/{$offer->id}/angebot-loeschen")
            ->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseMissing('book_offers', ['id' => $offer->id]);
    }

    public function test_user_cannot_delete_offer_of_other_user(): void
    {
        $user = $this->actingMember();
        $other = User::factory()->create();
        $offer = BookOffer::create([
            'user_id' => $other->id,
            'series' => 'Maddrax - Die dunkle Zukunft der Erde',
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        $this->actingAs($user);
        $this->post("/romantauschboerse/{$offer->id}/angebot-loeschen")->assertForbidden();

        $this->assertDatabaseHas('book_offers', ['id' => $offer->id]);
    }

    public function test_user_can_delete_own_request(): void
    {
        $user = $this->actingMember();
        $request = BookRequest::create([
            'user_id' => $user->id,
            'series' => 'Maddrax - Die dunkle Zukunft der Erde',
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        $this->actingAs($user);
        $this->post("/romantauschboerse/{$request->id}/anfrage-loeschen")
            ->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseMissing('book_requests', ['id' => $request->id]);
    }

    public function test_user_cannot_delete_request_of_other_user(): void
    {
        $user = $this->actingMember();
        $other = User::factory()->create();
        $request = BookRequest::create([
            'user_id' => $other->id,
            'series' => 'Maddrax - Die dunkle Zukunft der Erde',
            'book_number' => 1,
            'book_title' => 'Roman1',
            'condition' => 'neu',
        ]);

        $this->actingAs($user);
        $this->post("/romantauschboerse/{$request->id}/anfrage-loeschen")->assertForbidden();

        $this->assertDatabaseHas('book_requests', ['id' => $request->id]);
    }
}
