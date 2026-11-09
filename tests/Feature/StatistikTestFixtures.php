<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMemberClientSnapshot;
use Tests\Concerns\CreatesUserWithRole;

trait StatistikTestFixtures
{
    use CreatesMemberClientSnapshot;
    use CreatesUserWithRole;
    use RefreshDatabase;

    private string $testStoragePath;

    private function expectedBaxxLabel(string $sectionId): string
    {
        $cost = Reward::where('slug', 'statistik-'.$sectionId)->value('cost_baxx');

        return $cost.' Baxx';
    }

    private function createDataFile(): void
    {
        $data = [['nummer' => 1, 'titel' => 'Roman1', 'text' => ['Author1'], 'bewertung' => 4.0, 'stimmen' => 10, 'personen' => ['Char1', 'Char2'], 'schlagworte' => ['Thema1', 'Thema2']], ['nummer' => 2, 'titel' => 'Roman2', 'text' => ['Author1', 'Author2'], 'bewertung' => 5.0, 'stimmen' => 20, 'personen' => ['Char2', 'Char3'], 'schlagworte' => ['Thema2', 'Thema3']]];
        $path = storage_path('app/private/maddrax.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, json_encode($data));
    }

    private function createHardcoversFile(): void
    {
        $data = [];
        for ($i = 1; $i <= 30; $i++) {
            // Start ratings at 3.1 and increment by 0.1 for each hardcover
            $rating = 3.1 + ($i - 1) * 0.1;
            // subtract 1 because loop starts at 1
            $data[] = ['nummer' => $i, 'titel' => 'HC'.$i, 'bewertung' => $rating, 'text' => ['HC Author'.($i % 2 + 1)]];
        }
        $path = storage_path('app/private/hardcovers.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, json_encode($data));
    }

    private function createMissionMarsFile(): void
    {
        $data = [];
        $authors = ['Mission Mars Autor 1', 'Mission Mars Autor 2', 'Mission Mars Autor 3'];
        for ($i = 1; $i <= 12; $i++) {
            $authorIndex = ($i - 1) % count($authors);
            $entryAuthors = [$authors[$authorIndex]];
            if ($i % 5 === 0) {
                $entryAuthors[] = 'Mission Mars Co-Autor';
            }
            $data[] = ['nummer' => $i, 'titel' => 'Mission Mars '.$i, 'bewertung' => 3.5 + $i * 0.05, 'text' => $entryAuthors];
        }
        $path = storage_path('app/private/missionmars.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, json_encode($data));
    }

    private function createVolkDerTiefeFile(): void
    {
        $data = [];
        $authors = ['Volk der Tiefe Autor 1', 'Volk der Tiefe Autor 2'];
        for ($i = 1; $i <= 8; $i++) {
            $entryAuthors = [$authors[$i % 2]];
            if ($i % 3 === 0) {
                $entryAuthors[] = 'Volk der Tiefe Co-Autor';
            }
            $data[] = ['nummer' => $i, 'titel' => 'Das Volk der Tiefe '.$i, 'bewertung' => 3.8 + $i * 0.05, 'text' => $entryAuthors];
        }
        $path = storage_path('app/private/volkdertiefe.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, json_encode($data));
    }

    private function createZweitausendzwoelfFile(): void
    {
        $data = [];
        $authors = ['2012 Autor 1', '2012 Autor 2'];
        for ($i = 1; $i <= 6; $i++) {
            $entryAuthors = [$authors[$i % 2]];
            if ($i % 3 === 0) {
                $entryAuthors[] = '2012 Co-Autor';
            }
            $data[] = ['nummer' => $i, 'titel' => '2012 - Das Jahr der Apokalypse '.$i, 'bewertung' => 3.5 + $i * 0.1, 'text' => $entryAuthors];
        }
        $path = storage_path('app/private/2012.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, json_encode($data));
    }

    private function createAbenteurerFile(): void
    {
        $data = [];
        $authors = ['Abenteurer Autor 1', 'Abenteurer Autor 2'];
        for ($i = 1; $i <= 6; $i++) {
            $entryAuthors = [$authors[$i % 2]];
            if ($i % 3 === 0) {
                $entryAuthors[] = 'Abenteurer Co-Autor';
            }
            $data[] = ['nummer' => $i, 'titel' => 'Die Abenteurer '.$i, 'bewertung' => 3.5 + $i * 0.1, 'text' => $entryAuthors];
        }
        $path = storage_path('app/private/abenteurer.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, json_encode($data));
    }

    private function createTeamplayerDataFile(): void
    {
        $data = [];
        $coAuthor = 'Common';
        // Authors 1-10 appear twice
        for ($i = 1; $i <= 10; $i++) {
            for ($j = 1; $j <= 2; $j++) {
                $data[] = ['nummer' => count($data) + 1, 'titel' => 'Roman'.$i.'-'.$j, 'text' => ['Author'.$i, $coAuthor], 'bewertung' => 4.0, 'stimmen' => 10, 'personen' => []];
            }
        }
        // Author11 appears once
        $data[] = ['nummer' => count($data) + 1, 'titel' => 'Roman-extra', 'text' => ['Author11', $coAuthor], 'bewertung' => 3.0, 'stimmen' => 5, 'personen' => []];
        $path = storage_path('app/private/maddrax.json');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, json_encode($data));
    }

    private function purchaseStatistikReward(User $user, string ...$sectionIds): void
    {
        foreach ($sectionIds as $sectionId) {
            $slug = 'statistik-'.$sectionId;
            $reward = Reward::where('slug', $slug)->firstOrFail();
            RewardPurchase::create(['user_id' => $user->id, 'reward_id' => $reward->id, 'cost_baxx' => $reward->cost_baxx, 'purchased_at' => now()]);
        }
    }
}
