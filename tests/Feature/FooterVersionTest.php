<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('footer displays version and changelog link', function (): void {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    $version = config('app.version', '0.0.0');
    if ($version === '0.0.0') {
        $revList = Process::run(['git', 'rev-list', '--tags', '--max-count=1']);
        if ($revList->successful()) {
            $commit = trim($revList->output());
            if ($commit !== '') {
                $describe = Process::run(['git', 'describe', '--tags', $commit]);
                if ($describe->successful()) {
                    $gitVersion = trim($describe->output());
                    if ($gitVersion !== '') {
                        $version = $gitVersion;
                    }
                }
            }
        }
    }
    $response = $this->get('/');
    $response->assertSee("Version {$version}");
    $response->assertSee('/changelog');
});
