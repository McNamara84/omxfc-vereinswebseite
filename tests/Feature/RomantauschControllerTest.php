<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Models\BookOffer;
use App\Models\BookRequest;
use Illuminate\Support\Facades\Storage;

class RomantauschControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
    }

    private function actingMember(): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Mitglied']);
        return $user;
    }

    private function putBookData(): void
    {
        Storage::disk('private')->put('maddrax.json', json_encode([
            ['nummer' => 1, 'titel' => 'Roman1']
        ]));
    }

    public function test_create_offer_returns_error_if_json_missing(): void
    {
        $path = storage_path('app/private/maddrax.json');
        rename($path, $path . '.bak');

        $this->actingAs($this->actingMember());
        $this->get('/romantauschboerse/create-offer')->assertStatus(500);

        rename($path . '.bak', $path);
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
        $this->post("/romantauschboerse/{$offer->id}/{$request->id}/complete")
            ->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseHas('book_swaps', [
            'offer_id' => $offer->id,
            'request_id' => $request->id,
        ]);
        $this->assertEquals(1, (int) $offer->fresh()->completed);
        $this->assertEquals(1, (int) $request->fresh()->completed);
    }
}
