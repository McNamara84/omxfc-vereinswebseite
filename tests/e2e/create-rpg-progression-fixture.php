<?php

use App\Enums\Role;
use App\Models\RpgCharacter;
use App\Models\Team;
use App\Models\User;
use App\Services\RpgExperienceAwardService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'sqlite' || ! str_ends_with(config('database.connections.sqlite.database'), 'playwright.sqlite')) {
    throw new RuntimeException('This fixture requires the isolated Playwright database.');
}
Http::fake();
$members = Team::membersTeam();
$suffix = Str::uuid();
$leader = User::factory()->create(['email' => "rpg-leader-{$suffix}@example.test", 'current_team_id' => $members->id]);
$player = User::factory()->create(['email' => "rpg-player-{$suffix}@example.test", 'current_team_id' => $members->id]);
foreach ([$leader, $player] as $user) {
    $members->users()->attach($user, ['role' => Role::Mitglied->value]);
}
$team = Team::firstOrCreate(['name' => 'AG Rollenspiel'], ['user_id' => $leader->id, 'personal_team' => false]);
$team->update(['user_id' => $leader->id]);
$team->users()->syncWithoutDetaching([$leader->id => ['role' => Role::Mitglied->value], $player->id => ['role' => Role::Mitglied->value]]);
$character = RpgCharacter::factory()->create([
    'user_id' => $player->id, 'character_name' => 'Arkon EP-Test',
    'payload' => [
        'character' => ['character_name' => 'Arkon EP-Test', 'race' => 'Hydrit', 'culture' => 'Meeresbewohner'],
        'attributes' => ['st' => 0, 'ge' => 0, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0],
        'skills' => [['name' => 'Nahkampf', 'value' => 2]],
        'advantages' => [], 'disadvantages' => [], 'languages' => [], 'advantage_effects' => [],
        'equipment' => ['items' => []],
    ],
]);
if (($argv[1] ?? '') === 'funded') {
    app(RpgExperienceAwardService::class)->award($leader, [
        'submission_key' => (string) Str::uuid(), 'title' => 'Testabenteuer',
        'completed_on' => now()->toDateString(), 'minutes' => 360, 'cycle_bonus' => 0,
        'participants' => [[
            'character_id' => $character->id, 'survived' => true, 'roleplay' => 0,
            'humor' => false, 'rescue' => false, 'unwounded' => false,
            'points' => 60, 'reason' => 'Vorbereitung der Browserprüfung',
        ]],
    ]);
}
echo json_encode(['leader' => $leader->email, 'player' => $player->email, 'character_id' => $character->id], JSON_THROW_ON_ERROR).PHP_EOL;
