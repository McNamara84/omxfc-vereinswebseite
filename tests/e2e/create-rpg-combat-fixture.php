<?php

use App\Models\RpgCharacter;
use App\Models\RpgCombat;
use App\Models\RpgCombatDecision;
use App\Models\Team;
use App\Models\User;
use App\Services\RpgCombat\CombatDice;
use App\Services\RpgCombat\CombatService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'sqlite' || ! str_ends_with(config('database.connections.sqlite.database'), 'playwright.sqlite')) {
    throw new RuntimeException('Only the isolated Playwright database is permitted.');
}
Http::fake();
if (($argv[1] ?? '') === 'expire') {
    $combat = RpgCombat::findOrFail((int) $argv[2]);
    $combat->decisions()->where('status', 'pending')->update(['due_at' => now('UTC')->subMinute()]);
    Artisan::call('rpg:process-combats');
    echo json_encode(['status' => $combat->fresh()->status, 'automatic' => RpgCombatDecision::where('rpg_combat_id', $combat->id)->where('automatic', true)->count()]);
    exit;
}
if (($argv[1] ?? '') === 'replace-leader') {
    $combat = RpgCombat::findOrFail((int) $argv[2]);
    Team::findOrFail($combat->team_id)->update(['user_id' => User::where('email', $argv[3])->sole()->id]);
    echo json_encode(['changed' => true]);
    exit;
}
$scenario = $argv[1] ?? 'invitation';
$suffix = Str::uuid();
$members = Team::membersTeam();
$users = [];
foreach (['leader', 'player', 'other', 'outsider', 'replacement'] as $role) {
    $user = User::factory()->create(['email' => "combat-{$role}-{$suffix}@example.test", 'current_team_id' => $members->id]);
    $members->users()->attach($user, ['role' => 'Mitglied']);
    $users[$role] = $user;
}
$team = Team::firstOrCreate(['name' => 'AG Rollenspiel'], ['user_id' => $users['leader']->id, 'personal_team' => false]);
$team->update(['user_id' => $users['leader']->id]);
foreach (['leader', 'player', 'other', 'replacement'] as $role) {
    $team->users()->syncWithoutDetaching([$users[$role]->id => ['role' => 'Mitglied']]);
}
$characters = [];
foreach (['player' => 'Arkon', 'other' => 'Mira'] as $role => $name) {
    $attributes = ['st' => $role === 'player' ? 5 : 0, 'ge' => 0, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0];
    $skillValue = $role === 'player' ? 20 : 0;
    if ($scenario === 'invitation') {
        // Match the numeric strings persisted by the character editor.
        $attributes = array_map('strval', $attributes);
        $skillValue = (string) $skillValue;
    }
    $characters[$role] = RpgCharacter::factory()->create(['user_id' => $users[$role]->id, 'character_name' => $name.' Kampf '.substr($suffix, 0, 8),
        'payload' => ['character' => ['character_name' => $name, 'race' => 'Hydrit', 'culture' => 'Meeresbewohner'],
            'attributes' => $attributes,
            'skills' => [['name' => 'Nahkampf', 'value' => $skillValue]], 'advantages' => [], 'disadvantages' => [], 'advantage_effects' => [], 'equipment' => ['items' => []]]]);
}
$extra = [];
if (in_array($scenario, ['psychic', 'simultaneous'], true)) {
    $payload = $characters['player']->payload;
    if ($scenario === 'psychic') {
        $payload['attributes']['wi'] = 3;
        $payload['advantages'] = ['Psychische Kraft'];
        $payload['advantage_effects'] = [['name' => 'Psychische Kraft', 'target' => 'Pyrokinese']];
        $payload['skills'][] = ['name' => 'Pyrokinese', 'value' => 20];
    } else {
        $payload['skills'][0]['value'] = 0;
    }
    $characters['player']->update(['payload' => $payload]);
    $app->instance(CombatDice::class, new class extends CombatDice
    {
        public function roll(int $count = 2): array
        {
            return array_fill(0, $count, 4);
        }
    });
    $service = $app->make(CombatService::class);
    $combat = $service->challenge($users['player'], ['submission_key' => (string) Str::uuid(), 'character_id' => $characters['player']->id,
        'opponent_id' => $characters['other']->id, 'revision' => $characters['player']->refresh()->revision, 'opponent_revision' => $characters['other']->refresh()->revision, 'distance' => 1]);
    $combat = $service->command($users['other'], $combat->id, 'accept', (string) Str::uuid());
    foreach (['prepare', 'initiative'] as $type) {
        foreach (['player' => 1, 'other' => 2] as $role => $side) {
            $decision = $combat->decisions()->where('type', $type)->where('side', $side)->where('status', 'pending')->sole();
            $combat = $service->decide($users[$role], $combat->id, $decision->id, $type === 'prepare' ? ['weapons' => [], 'shield' => false, 'skill' => 'Nahkampf'] : []);
        }
    }
    $extra['combat'] = $combat->id;
}
echo json_encode([...array_map(fn ($u) => $u->email, $users), 'characters' => array_map(fn ($c) => ['id' => $c->id, 'name' => $c->character_name], $characters), ...$extra], JSON_THROW_ON_ERROR).PHP_EOL;
