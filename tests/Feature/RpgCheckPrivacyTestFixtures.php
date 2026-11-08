<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;
use Illuminate\Testing\TestResponse;
use Symfony\Component\DomCrawler\Crawler;
use Tests\Support\RpgCheckFixtures;

trait RpgCheckPrivacyTestFixtures
{
    use RefreshDatabase, RpgCheckFixtures;

    private function assertSecret(array $data): void
    {
        $forbidden = ['difficulty', 'modifiers', 'modifier_total', 'base_total', 'attribute_value', 'skill_value', 'dice', 'die_one', 'die_two', 'total', 'raw_total', 'margin', 'result_kind', 'winner_position', 'comparison_margin', 'resolution_reason', 'resolved_at', 'completed_at', 'executable'];
        foreach ($data as $key => $value) {
            expect($forbidden)->not->toContain($key);
            if (is_array($value)) {
                $this->assertSecret($value);
            }
        }
    }

    private function assertSecretHtml(TestResponse $response, array $projection): void
    {
        $response->assertOk()->assertDontSee('SECRET-SITUATION');
        $this->assertSecret($projection);
        expect($response->viewData('data'))->toBe($projection);
        // Check the complete data actually embedded for Alpine. Short numbers
        // anywhere in the page can also occur in unrelated CSRF tokens or IDs.
        $component = (new Crawler($response->getContent()))->filter('[x-data^="rpgChecks("]');
        expect($component)->toHaveCount(1)
            ->and($component->attr('x-data'))->toBe('rpgChecks('.Js::from(['initial' => ['checks' => [$projection]], 'url' => $projection['url'], 'detail' => true])->toHtml().')');
    }
}
