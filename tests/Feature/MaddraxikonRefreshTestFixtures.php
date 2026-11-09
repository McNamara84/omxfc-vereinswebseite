<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Maddraxikon\MaddraxikonSnapshotRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

trait MaddraxikonRefreshTestFixtures
{
    use RefreshDatabase;

    private string $testStoragePath;

    private function articleHtml(int $number, string $cycle): string
    {
        return '<html><body><b>'.$number.'</b><table>
            <tr><td>Erstmals&nbsp;erschienen:</td><td>2026-09-01</td></tr>
            <tr><td>Zyklus:</td><td>'.$cycle.' (1)</td></tr>
            <tr><td>Titel:</td><th>Roman '.$number.'</th></tr>
            <tr><td>Text:</td><td>Autor</td></tr>
        </table></body></html>';
    }

    /** @return array<string, mixed> */
    private function row(int $number, string $cycle, string $title): array
    {
        return ['nummer' => $number, 'evt' => '2026-09-01', 'zyklus' => $cycle, 'titel' => $title, 'text' => ['Autor'], 'bewertung' => null, 'stimmen' => 0, 'personen' => null, 'schlagworte' => null, 'orte' => null, 'maddraxikon_seitentitel' => 'MX '.$number];
    }

    /** @param array<string, array<int, array<string, mixed>>> $datasets */
    private function activateSnapshot(string $id, array $datasets): void
    {
        $json = collect($datasets)->map(static fn (array $rows): string => (string) json_encode($rows, JSON_THROW_ON_ERROR))->all();
        DB::transaction(fn () => app(MaddraxikonSnapshotRepository::class)->storeAndActivate($id, $json));
    }
}
