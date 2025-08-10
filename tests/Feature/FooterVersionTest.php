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
        $commit = trim(Process::run(['git', 'rev-list', '--tags', '--max-count=1'])->output());

        $version = '0.0.0';

        if ($commit !== '') {
            $describe = Process::run(['git', 'describe', '--tags', $commit]);

            if ($describe->successful()) {
                $version = trim($describe->output());
            }
        }

        $response = $this->get('/');
        $response->assertSee("Version {$version}");
        $response->assertSee('/changelog');
    }
}
