<?php

namespace Tests\Feature;

use App\Enums\PollStatus;
use App\Enums\PollVisibility;
use App\Enums\Role;
use App\Models\Poll;
use App\Models\PollOption;
use App\Support\Navigation\PageNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\DomCrawler\Crawler;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class PageNavigationPollTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_public_poll_page_uses_the_dynamic_menu_label_for_its_breadcrumb(bool $authenticated): void
    {
        $poll = $this->createPoll();
        if ($authenticated) {
            $this->actingAs($poll->createdBy);
        }
        $response = $this->get(route('umfrage.aktuell'))->assertOk();
        $crawler = new Crawler($response->getContent());
        $this->assertSame($poll->menu_label, $crawler->filter('nav[aria-label="Brotkrumen"] [aria-current="page"]')->text());
        $this->assertSame($poll->menu_label, app(PageNavigation::class)->resolve($authenticated ? $poll->createdBy : null, 'public')['title']);
    }

    #[TestWith(['public'])]
    #[TestWith(['internal'])]
    public function test_member_page_navigation_uses_the_active_poll_label(string $visibility): void
    {
        $poll = $this->createPoll($visibility);
        $this->usePollRoute();

        $page = app(PageNavigation::class)->resolve($poll->createdBy, 'member');

        $this->assertSame($poll->menu_label, $page['title']);
        $this->assertSame(['Dashboard', $poll->menu_label], array_column($page['breadcrumbs'], 'label'));
    }

    #[TestWith(['internal', 'active', -1, 1])]
    #[TestWith(['public', 'active', 1, 2])]
    #[TestWith(['public', 'active', -2, -1])]
    #[TestWith(['public', 'draft', -1, 1])]
    #[TestWith(['public', 'archived', -1, 1])]
    public function test_public_page_navigation_hides_polls_outside_its_visibility_and_voting_window(string $visibility, string $status, int $startHours, int $endHours): void
    {
        $poll = $this->createPoll($visibility, [
            'status' => PollStatus::from($status),
            'starts_at' => now()->addHours($startHours),
            'ends_at' => now()->addHours($endHours),
        ]);
        $this->usePollRoute();

        $page = app(PageNavigation::class)->resolve(null, 'public');

        $this->assertSame('Details', $page['title']);
        $this->assertNotContains($poll->menu_label, array_column($page['breadcrumbs'], 'label'));
    }

    private function createPoll(string $visibility = 'public', array $attributes = []): Poll
    {
        $member = $this->createUserWithRole(Role::Mitglied);
        $poll = Poll::create(array_merge([
            'question' => 'Wohin führt das nächste Treffen?',
            'menu_label' => 'Abstimmung über unser nächstes Treffen',
            'visibility' => PollVisibility::from($visibility),
            'status' => PollStatus::Active,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'activated_at' => now(),
            'created_by_user_id' => $member->id,
        ], $attributes));
        PollOption::create(['poll_id' => $poll->id, 'label' => 'Köln', 'sort_order' => 0]);

        return $poll;
    }

    private function usePollRoute(): void
    {
        request()->setRouteResolver(fn () => (new Route('GET', '/umfrage', fn () => null))->name('umfrage.aktuell'));
    }
}
