<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PollStatus;
use App\Support\Navigation\PageNavigation;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(PageNavigationPollTestFixtures::class);

test('public poll page uses the dynamic menu label for its breadcrumb', function (bool $authenticated): void {
    $poll = $this->createPoll();
    if ($authenticated) {
        $this->actingAs($poll->createdBy);
    }
    $response = $this->get(route('umfrage.aktuell'))->assertOk();
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('nav[aria-label="Brotkrumen"] [aria-current="page"]')->text())->toBe($poll->menu_label)
        ->and(app(PageNavigation::class)->resolve($authenticated ? $poll->createdBy : null, 'public')['title'])->toBe($poll->menu_label);
})->with([[false], [true]]);

test('member page navigation uses the active poll label', function (string $visibility): void {
    $poll = $this->createPoll($visibility);
    $this->usePollRoute();
    $page = app(PageNavigation::class)->resolve($poll->createdBy, 'member');
    expect($page['title'])->toBe($poll->menu_label)
        ->and(array_column($page['breadcrumbs'], 'label'))->toBe(['Dashboard', $poll->menu_label]);
})->with([['public'], ['internal']]);

test('public page navigation hides polls outside its visibility and voting window', function (string $visibility, string $status, int $startHours, int $endHours): void {
    $poll = $this->createPoll($visibility, ['status' => PollStatus::from($status), 'starts_at' => now()->addHours($startHours), 'ends_at' => now()->addHours($endHours)]);
    $this->usePollRoute();
    $page = app(PageNavigation::class)->resolve(null, 'public');
    expect($page['title'])->toBe('Details')
        ->and(array_column($page['breadcrumbs'], 'label'))->not->toContain($poll->menu_label);
})->with([['internal', 'active', -1, 1], ['public', 'active', 1, 2], ['public', 'active', -2, -1], ['public', 'draft', -1, 1], ['public', 'archived', -1, 1]]);
