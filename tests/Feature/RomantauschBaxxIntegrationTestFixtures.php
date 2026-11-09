<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BookType;
use App\Enums\Role;
use App\Models\BaxxEarningRule;
use App\Models\Book;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

trait RomantauschBaxxIntegrationTestFixtures
{
    use RefreshDatabase;

    private function createMemberWithOtherCurrentTeam(Team $membersTeam, Team $otherTeam): User
    {
        $user = User::factory()->create(['current_team_id' => $otherTeam->id]);
        $membersTeam->users()->attach($user, ['role' => Role::Mitglied->value]);
        $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);

        return $user;
    }

    private function createBook(int $romanNumber): void
    {
        Book::create(['roman_number' => $romanNumber, 'title' => 'Roman '.$romanNumber, 'author' => 'Autor '.$romanNumber, 'type' => BookType::MaddraxDieDunkleZukunftDerErde]);
    }

    private function configureRule(string $actionKey, array $attributes): void
    {
        BaxxEarningRule::query()->where('action_key', $actionKey)->firstOrFail()->update($attributes);
    }
}
