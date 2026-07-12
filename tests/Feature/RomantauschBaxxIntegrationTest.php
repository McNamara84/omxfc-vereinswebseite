<?php

namespace Tests\Feature;

use App\Enums\BookType;
use App\Enums\Role;
use App\Livewire\RomantauschOfferForm;
use App\Livewire\RomantauschRequestForm;
use App\Models\BaxxEarningRule;
use App\Models\Book;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\Team;
use App\Models\User;
use App\Services\Romantausch\BundleService;
use App\Services\Romantausch\SwapMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RomantauschBaxxIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_offer_awards_points_in_members_wallet(): void
    {
        $this->createBook(1);
        $membersTeam = Team::membersTeam();
        $otherTeam = Team::factory()->create();
        $user = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);

        $this->configureRule('romantausch_offer', [
            'points' => 4,
            'every_count' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(RomantauschOfferForm::class)
            ->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)
            ->set('book_number', 1)
            ->set('condition', 'neu')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseHas('user_points', [
            'user_id' => $user->id,
            'team_id' => $membersTeam->id,
            'points' => 4,
        ]);
        $this->assertDatabaseMissing('user_points', [
            'user_id' => $user->id,
            'team_id' => $otherTeam->id,
            'points' => 4,
        ]);
    }

    public function test_creating_request_awards_points_in_members_wallet(): void
    {
        $this->createBook(2);
        $membersTeam = Team::membersTeam();
        $otherTeam = Team::factory()->create();
        $user = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);

        $this->configureRule('romantausch_request', [
            'points' => 3,
            'every_count' => 1,
            'is_active' => true,
        ]);

        $this->actingAs($user);

        Livewire::test(RomantauschRequestForm::class)
            ->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)
            ->set('book_number', 2)
            ->set('condition', 'gut')
            ->call('save')
            ->assertRedirect(route('romantausch.index'));

        $this->assertDatabaseHas('user_points', [
            'user_id' => $user->id,
            'team_id' => $membersTeam->id,
            'points' => 3,
        ]);
    }

    public function test_bundle_creation_counts_each_offer_for_baxx_awards(): void
    {
        $this->createBook(3);
        $this->createBook(4);
        $membersTeam = Team::membersTeam();
        $otherTeam = Team::factory()->create();
        $user = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);

        $this->configureRule('romantausch_offer', [
            'points' => 2,
            'every_count' => 2,
            'is_active' => true,
        ]);

        app(BundleService::class)->createBundle(
            BookType::MaddraxDieDunkleZukunftDerErde->value,
            [3, 4],
            'Z0',
            null,
            [],
            $user->id,
        );

        $this->assertDatabaseHas('user_points', [
            'user_id' => $user->id,
            'team_id' => $membersTeam->id,
            'points' => 2,
        ]);
    }

    public function test_confirmed_swap_awards_both_participants_in_members_wallet(): void
    {
        $membersTeam = Team::membersTeam();
        $otherTeam = Team::factory()->create();
        $offerUser = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);
        $requestUser = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);

        $this->configureRule('romantausch_swap_complete', [
            'points' => 6,
            'every_count' => 1,
            'is_active' => true,
        ]);

        $offer = BookOffer::create([
            'user_id' => $offerUser->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 10,
            'book_title' => 'Band 10',
            'condition' => 'gut',
        ]);

        $request = BookRequest::create([
            'user_id' => $requestUser->id,
            'series' => BookType::MaddraxDieDunkleZukunftDerErde->value,
            'book_number' => 10,
            'book_title' => 'Band 10',
            'condition' => 'gut',
        ]);

        $swap = BookSwap::create([
            'offer_id' => $offer->id,
            'request_id' => $request->id,
        ]);

        $service = app(SwapMatchingService::class);
        $service->confirmSwap($swap->fresh(['offer.user', 'request.user']), $offerUser);
        $result = $service->confirmSwap($swap->fresh(['offer.user', 'request.user']), $requestUser);

        $this->assertTrue($result['completed']);
        $this->assertTrue($result['points_awarded']);
        $this->assertDatabaseHas('user_points', [
            'user_id' => $offerUser->id,
            'team_id' => $membersTeam->id,
            'points' => 6,
        ]);
        $this->assertDatabaseHas('user_points', [
            'user_id' => $requestUser->id,
            'team_id' => $membersTeam->id,
            'points' => 6,
        ]);
    }

    private function createMemberWithOtherCurrentTeam(Team $membersTeam, Team $otherTeam): User
    {
        $user = User::factory()->create([
            'current_team_id' => $otherTeam->id,
        ]);

        $membersTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
        $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);

        return $user;
    }

    private function createBook(int $romanNumber): void
    {
        Book::create([
            'roman_number' => $romanNumber,
            'title' => 'Roman '.$romanNumber,
            'author' => 'Autor '.$romanNumber,
            'type' => BookType::MaddraxDieDunkleZukunftDerErde,
        ]);
    }

    private function configureRule(string $actionKey, array $attributes): void
    {
        BaxxEarningRule::query()
            ->where('action_key', $actionKey)
            ->firstOrFail()
            ->update($attributes);
    }
}
