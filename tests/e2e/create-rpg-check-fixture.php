<?php

use App\Models\RpgCharacter;
use App\Models\Team;
use App\Models\User;
use App\Services\RpgCheckDice;
use App\Services\RpgCheckService;
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
$users = [];
foreach (['leader', 'player', 'other', 'outsider'] as $role) {
    $user = User::factory()->create(['email' => "rpg-check-{$role}-{$suffix}@example.test", 'current_team_id' => $members->id]);
    $members->users()->attach($user, ['role' => 'Mitglied']);
    $users[$role] = $user;
}
$team = Team::firstOrCreate(['name' => 'AG Rollenspiel'], ['user_id' => $users['leader']->id, 'personal_team' => false]);
$team->update(['user_id' => $users['leader']->id]);
foreach (['leader', 'player', 'other'] as $role) {
    $team->users()->syncWithoutDetaching([$users[$role]->id => ['role' => 'Mitglied']]);
}
$characters = [];
foreach (['player' => 'Arkon', 'other' => 'Birka'] as $role => $name) {
    $characters[$role] = RpgCharacter::factory()->create([
        'user_id' => $users[$role]->id, 'character_name' => $name.' Probe-Test '.substr($suffix, 0, 8),
        'payload' => [
            'character' => ['character_name' => $name, 'race' => 'Hydrit', 'culture' => 'Meeresbewohner'],
            'attributes' => ['st' => 1, 'ge' => 0, 'ro' => 0, 'wi' => 0, 'wa' => 1, 'in' => 0, 'au' => 0],
            'skills' => [['name' => 'Nahkampf', 'value' => 2], ['name' => 'Heimlichkeit', 'value' => 3]],
            'advantages' => [], 'disadvantages' => [], 'advantage_effects' => [], 'languages' => [],
            'equipment' => ['items' => []],
        ],
    ])->refresh();
}
$check = null;
if (($argv[1] ?? '') === 'tie') {
    $app->instance(RpgCheckDice::class, new class extends RpgCheckDice
    {
        public function roll(): array
        {
            return [3, 4];
        }
    });
    $service = app(RpgCheckService::class);
    $batch = $service->create($users['leader'], [
        'submission_key' => (string) Str::uuid(), 'description' => 'Gleichstand '.$suffix,
        'visibility' => 'open', 'mode' => 'opposed', 'difficulty' => null,
        'participants' => array_map(fn ($c) => ['character_id' => $c->id, 'revision' => 0,
            'check_type' => 'attribute', 'attribute_key' => 'wa', 'skill_name' => null, 'modifiers' => []], array_values($characters)),
    ]);
    $check = $batch->checks()->firstOrFail();
    foreach ($check->participants as $p) {
        $service->roll(User::findOrFail($p->owner_id), $check->id, $p->id);
    }
}
echo json_encode([
    ...array_map(fn ($u) => $u->email, $users),
    'characters' => array_map(fn ($c) => ['id' => $c->id, 'name' => $c->character_name], $characters),
    'check_id' => $check?->id,
], JSON_THROW_ON_ERROR).PHP_EOL;
