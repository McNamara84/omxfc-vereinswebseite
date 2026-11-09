<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PollStatus;
use App\Enums\PollVisibility;
use App\Enums\Role;
use App\Models\Poll;
use App\Models\PollOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Tests\Concerns\CreatesUserWithRole;

trait PageNavigationPollTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private function createPoll(string $visibility = 'public', array $attributes = []): Poll
    {
        $member = $this->createUserWithRole(Role::Mitglied);
        $poll = Poll::create(array_merge(['question' => 'Wohin führt das nächste Treffen?', 'menu_label' => 'Abstimmung über unser nächstes Treffen', 'visibility' => PollVisibility::from($visibility), 'status' => PollStatus::Active, 'starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'activated_at' => now(), 'created_by_user_id' => $member->id], $attributes));
        PollOption::create(['poll_id' => $poll->id, 'label' => 'Köln', 'sort_order' => 0]);

        return $poll;
    }

    private function usePollRoute(): void
    {
        request()->setRouteResolver(fn () => (new Route('GET', '/umfrage', fn () => null))->name('umfrage.aktuell'));
    }
}
