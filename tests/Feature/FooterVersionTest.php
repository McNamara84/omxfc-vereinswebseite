<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class FooterVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_displays_version_and_changelog_link(): void
    {
        $process = Process::run(['bash', '-c', 'git describe --tags $(git rev-list --tags --max-count=1)']);
        $version = $process->successful() ? trim($process->output()) : '0.0.0';

        $response = $this->get('/');
        $response->assertSee("Version {$version}");
        $response->assertSee('/changelog');
    }
}
