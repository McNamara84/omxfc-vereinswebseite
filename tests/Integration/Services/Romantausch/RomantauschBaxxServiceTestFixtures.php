<?php

declare(strict_types=1);

namespace Tests\Integration\Services\Romantausch;

use App\Enums\Role;
use App\Models\BaxxEarningRule;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\Team;
use App\Models\User;
use App\Services\Romantausch\RomantauschBaxxService;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait RomantauschBaxxServiceTestFixtures
{
    use RefreshDatabase;

    private RomantauschBaxxService $service;

    private function createMemberWithOtherCurrentTeam(Team $membersTeam, Team $otherTeam): User
    {
        $user = User::factory()->create(['current_team_id' => $otherTeam->id]);
        $membersTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
        $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);

        return $user;
    }

    private function configureRule(string $actionKey, array $attributes): void
    {
        BaxxEarningRule::query()->where('action_key', $actionKey)->firstOrFail()->update($attributes);
    }

    private function createOffer(User $user, int $bookNumber): BookOffer
    {
        return BookOffer::create(['user_id' => $user->id, 'series' => 'Maddrax', 'book_number' => $bookNumber, 'book_title' => 'Band '.$bookNumber, 'condition' => 'Z0', 'photos' => []]);
    }

    private function createRequest(User $user, int $bookNumber): BookRequest
    {
        return BookRequest::create(['user_id' => $user->id, 'series' => 'Maddrax', 'book_number' => $bookNumber, 'book_title' => 'Band '.$bookNumber, 'condition' => 'Z0']);
    }
}
