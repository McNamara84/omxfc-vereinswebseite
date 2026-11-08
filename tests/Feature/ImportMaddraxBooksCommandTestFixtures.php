<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

trait ImportMaddraxBooksCommandTestFixtures
{
    use RefreshDatabase;

    private string $testStoragePath;

    private function writeValidFiles(): void
    {
        $rows = ['maddrax.json' => [$this->row(1, 'Euree', 'Maddrax')], 'hardcovers.json' => [$this->row(1, null, 'Hardcover')], 'missionmars.json' => [$this->row(1, null, 'Mission Mars')], 'volkdertiefe.json' => [$this->row(1, null, 'Das Volk der Tiefe')], '2012.json' => [$this->row(1, null, '2012')], 'abenteurer.json' => [$this->row(1, null, 'Die Abenteurer')]];
        foreach ($rows as $filename => $data) {
            File::put(storage_path('app/private/'.$filename), json_encode($data));
        }
    }

    /** @return array<string, mixed> */
    private function row(int $number, ?string $cycle, string $title): array
    {
        return ['nummer' => $number, 'titel' => $title, 'zyklus' => $cycle, 'text' => ['Autor A', 'Autor B'], 'maddraxikon_seitentitel' => 'MX '.$number];
    }
}
