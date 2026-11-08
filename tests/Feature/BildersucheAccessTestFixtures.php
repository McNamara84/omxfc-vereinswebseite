<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesBildersucheCover;
use Tests\Concerns\CreatesUserWithRole;

trait BildersucheAccessTestFixtures
{
    use CreatesBildersucheCover;
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function joinAg(User $user): Team
    {
        $team = Team::factory()->create(['name' => 'AG Maddraxikon', 'personal_team' => false]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);
        config(['bildersuche.ag_team_id' => $team->id]);

        return $team;
    }

    private function assertSingleSearch(callable $request): void
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $request();
            $queries = collect(DB::getQueryLog())->pluck('query');
            $searchQueries = $queries->filter(fn (string $sql) => str_contains($sql, 'normalized_keyword LIKE'));
            expect($searchQueries)->toHaveCount(2)
                ->and($searchQueries->filter(fn (string $sql) => str_contains($sql, 'count(*)')))->toHaveCount(1);
            foreach (['books', 'book_cover_keywords'] as $relation) {
                expect($queries->filter(fn (string $sql) => preg_match('/^select \* from [`"]'.$relation.'[`"] /i', $sql)))->toHaveCount(1);
            }
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
    }
}
