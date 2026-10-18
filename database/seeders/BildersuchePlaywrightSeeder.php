<?php

namespace Database\Seeders;

use App\Enums\BookType;
use App\Models\BookCover;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\CoverRatings\BookCoverKeywordSynchronizer;
use Illuminate\Database\Seeder;
use RuntimeException;

class BildersuchePlaywrightSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing')) {
            throw new RuntimeException('Bildersuche-Testdaten dürfen nur in der Testumgebung angelegt werden.');
        }
        $members = Team::membersTeam();
        if (! $members) {
            throw new RuntimeException('Das Mitglieder-Team für die Testdaten fehlt.');
        }
        $ag = Team::query()->firstOrCreate(['name' => 'AG Maddraxikon'], ['user_id' => $members->user_id, 'personal_team' => false]);
        foreach (['chromium', 'firefox', 'webkit'] as $browser) {
            foreach (['ag', 'purchase', 'locked'] as $flow) {
                foreach ([0, 1, 2] as $retry) {
                    $user = User::factory()->create([
                        'email' => "bildersuche-{$flow}-{$browser}-{$retry}@example.com",
                        'current_team_id' => $members->id,
                    ]);
                    $members->users()->attach($user, ['role' => 'Mitglied']);
                    if ($flow === 'ag') {
                        $ag->users()->attach($user, ['role' => 'Mitglied']);
                    }
                    if ($flow === 'purchase') {
                        UserPoint::create(['user_id' => $user->id, 'team_id' => $members->id, 'points' => 100]);
                    }
                }
            }
        }
        foreach ([BookType::MaddraxDieDunkleZukunftDerErde, BookType::MissionMars] as $index => $type) {
            $cover = BookCover::query()->whereHas('book', fn ($books) => $books->where('type', $type))->firstOrFail();
            $keywords = $index === 0
                ? ['Aruula', 'Eisgebirge', 'F-17 Alpha 2', 'Matthew Drax', 'Taratze', 'Schwert']
                : ['Bolluna', 'Matthew Drax', 'Jandra', 'Wulfane'];
            app(BookCoverKeywordSynchronizer::class)->sync($cover, $cover->source_file_title, ['keywords' => $keywords, 'error' => null]);
        }
    }
}
