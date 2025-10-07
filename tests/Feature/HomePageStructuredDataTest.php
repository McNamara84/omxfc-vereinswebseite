<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class HomePageStructuredDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_structured_data_excludes_search_action_when_search_is_not_public(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        $structuredData = $this->extractStructuredData($response->getContent());
        $webSite = collect($structuredData['@graph'])->firstWhere('@type', 'WebSite');

        $this->assertIsArray($webSite);
        $this->assertArrayNotHasKey('potentialAction', $webSite);
    }

    public function test_home_page_structured_data_includes_search_action_when_public_route_exists(): void
    {
        config(['services.kompendium.public_search' => true]);

        $searchRoute = Route::getRoutes()->getByName('kompendium.search');

        $this->assertNotNull($searchRoute, 'Expected the kompendium.search route to be registered.');

        $response = $this->get('/');

        $response->assertOk();

        $structuredData = $this->extractStructuredData($response->getContent());
        $webSite = collect($structuredData['@graph'])->firstWhere('@type', 'WebSite');

        $this->assertIsArray($webSite);
        $this->assertArrayHasKey('potentialAction', $webSite);
        $this->assertSame(
            route('kompendium.search') . '?q={search_term_string}',
            $webSite['potentialAction']['target']
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function extractStructuredData(string $html): array
    {
        $crawler = new Crawler($html);
        $scriptNode = $crawler->filter('script[type="application/ld+json"]')->first();

        $this->assertTrue($scriptNode->count() > 0, 'Expected structured data script to exist on the page.');

        $structuredData = json_decode($scriptNode->text(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($structuredData);

        return $structuredData;
    }
}
