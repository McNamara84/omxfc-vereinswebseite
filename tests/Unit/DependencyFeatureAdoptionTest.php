<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DependencyFeatureAdoptionTest extends TestCase
{
    public function test_phpunit_stability_diagnostics_are_available_without_masking_the_default_test_command(): void
    {
        $root = dirname(__DIR__, 2);
        $composer = json_decode((string) file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
        $scripts = $composer['scripts'] ?? [];

        $this->assertSame('@php vendor/bin/pest --compact', $scripts['test'] ?? null);
        $this->assertStringContainsString('--repeat=2', $scripts['test:stability:repeat'] ?? '');
        $this->assertStringContainsString('--retry=2', $scripts['test:stability:retry'] ?? '');
    }

    public function test_application_assets_do_not_depend_on_remote_font_or_icon_stylesheets(): void
    {
        $root = dirname(__DIR__, 2);
        $layouts = implode('', array_map(
            static fn (string $layout): string => (string) file_get_contents($layout),
            glob($root.'/resources/views/layouts/*.blade.php') ?: [],
        ));
        $memberMap = (string) file_get_contents($root.'/resources/views/mitglieder/karte.blade.php');

        $this->assertStringNotContainsString('fonts.bunny.net', $layouts);
        $this->assertStringNotContainsString('cdnjs.cloudflare.com/ajax/libs/font-awesome', $memberMap);
    }

    public function test_deployment_pauses_queues_gracefully_and_resumes_before_workers_start(): void
    {
        $workflow = (string) file_get_contents(dirname(__DIR__, 2).'/.github/workflows/deploy.yml');
        $featureCheck = strpos($workflow, 'php artisan queue:pause --help --no-ansi');
        $pause = strpos($workflow, 'php artisan queue:pause --all');
        $stop = strpos($workflow, '$COMPOSE stop --timeout 360 queue');
        $resume = strpos($workflow, 'docker exec maddrax-app php artisan queue:resume --all');
        $start = strpos($workflow, '$COMPOSE up -d --force-recreate --no-deps queue scheduler');

        $this->assertNotFalse($featureCheck);
        $this->assertNotFalse($pause);
        $this->assertNotFalse($stop);
        $this->assertNotFalse($resume);
        $this->assertNotFalse($start);
        $this->assertLessThan($pause, $featureCheck);
        $this->assertLessThan($stop, $pause);
        $this->assertLessThan($resume, $stop);
        $this->assertLessThan($start, $resume);
    }

    public function test_deployment_prunes_only_stale_dangling_images_after_health_checks(): void
    {
        $workflow = (string) file_get_contents(dirname(__DIR__, 2).'/.github/workflows/deploy.yml');
        $connectivityCheck = strpos($workflow, 'curl -f http://localhost:8080');
        $imagePrune = strpos($workflow, 'docker image prune --force --filter "until=168h"');

        $this->assertNotFalse($connectivityCheck);
        $this->assertNotFalse($imagePrune);
        $this->assertLessThan($imagePrune, $connectivityCheck);
        $this->assertStringNotContainsString('docker volume prune', $workflow);
        $this->assertStringNotContainsString('docker system prune', $workflow);
    }
}
