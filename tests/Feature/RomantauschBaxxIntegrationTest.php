<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Romantausch\BundleWorkflow;
use App\Actions\Romantausch\SwapWorkflow;
use App\Enums\BookType;
use App\Livewire\RomantauschOfferForm;
use App\Livewire\RomantauschRequestForm;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\Team;
use Livewire\Livewire;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RomantauschBaxxIntegrationTestFixtures::class);

test('creating offer awards points in members wallet', function (): void {
    $this->createBook(1);
    $membersTeam = Team::membersTeam();
    $otherTeam = Team::factory()->create();
    $user = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);
    $this->configureRule('romantausch_offer', ['points' => 4, 'every_count' => 1, 'is_active' => true]);
    $this->actingAs($user);
    Livewire::test(RomantauschOfferForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_number', 1)->set('condition', 'neu')->call('save')->assertRedirect(route('romantausch.index'));
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'team_id' => $membersTeam->id, 'points' => 4]);
    $this->assertDatabaseMissing('user_points', ['user_id' => $user->id, 'team_id' => $otherTeam->id, 'points' => 4]);
});

test('creating request awards points in members wallet', function (): void {
    $this->createBook(2);
    $membersTeam = Team::membersTeam();
    $otherTeam = Team::factory()->create();
    $user = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);
    $this->configureRule('romantausch_request', ['points' => 3, 'every_count' => 1, 'is_active' => true]);
    $this->actingAs($user);
    Livewire::test(RomantauschRequestForm::class)->set('series', BookType::MaddraxDieDunkleZukunftDerErde->value)->set('book_number', 2)->set('condition', 'gut')->call('save')->assertRedirect(route('romantausch.index'));
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'team_id' => $membersTeam->id, 'points' => 3]);
});

test('bundle creation counts each offer for baxx awards', function (): void {
    $this->createBook(3);
    $this->createBook(4);
    $membersTeam = Team::membersTeam();
    $otherTeam = Team::factory()->create();
    $user = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);
    $this->configureRule('romantausch_offer', ['points' => 2, 'every_count' => 2, 'is_active' => true]);
    app(BundleWorkflow::class)->create($user, ['series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_numbers' => '3, 4', 'condition' => 'Z0']);
    $this->assertDatabaseHas('user_points', ['user_id' => $user->id, 'team_id' => $membersTeam->id, 'points' => 2]);
});

test('confirmed swap awards both participants in members wallet', function (): void {
    $membersTeam = Team::membersTeam();
    $otherTeam = Team::factory()->create();
    $offerUser = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);
    $requestUser = $this->createMemberWithOtherCurrentTeam($membersTeam, $otherTeam);
    $this->configureRule('romantausch_swap_complete', ['points' => 6, 'every_count' => 1, 'is_active' => true]);
    $offer = BookOffer::create(['user_id' => $offerUser->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 10, 'book_title' => 'Band 10', 'condition' => 'gut']);
    $request = BookRequest::create(['user_id' => $requestUser->id, 'series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 10, 'book_title' => 'Band 10', 'condition' => 'gut']);
    $swap = BookSwap::create(['offer_id' => $offer->id, 'request_id' => $request->id]);
    $service = app(SwapWorkflow::class);
    $service->confirm($offerUser, $swap->id);
    $result = $service->confirm($requestUser, $swap->id);
    expect($result->completed)->toBeTrue()
        ->and(array_sum($result->awards))->toBeGreaterThan(0);
    $this->assertDatabaseHas('user_points', ['user_id' => $offerUser->id, 'team_id' => $membersTeam->id, 'points' => 6]);
    $this->assertDatabaseHas('user_points', ['user_id' => $requestUser->id, 'team_id' => $membersTeam->id, 'points' => 6]);
});
