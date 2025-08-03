<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Process;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FooterVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_footer_displays_version_and_changelog_link(): void
    {
        $version = trim(Process::run('git describe --tags --abbrev=0')->output());
        $response = $this->get('/');
        $response->assertSee("Version {$version}");
        $response->assertSee('/changelog');
    }
}
