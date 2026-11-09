<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;

trait NewsletterArchivTestFixtures
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $navigation
     * @return array<int, string>
     */
    private function sectionItemTitles(array $navigation, string $sectionTitle): array
    {
        $section = collect($navigation['sections'] ?? [])->firstWhere('title', $sectionTitle);

        return collect($section['items'] ?? [])->pluck('title')->all();
    }
}
