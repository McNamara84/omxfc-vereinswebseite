<?php

use App\Enums\Role;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'sqlite'
    || ! str_ends_with(config('database.connections.sqlite.database'), 'playwright.sqlite')) {
    throw new RuntimeException('This fixture requires the isolated Playwright database.');
}
$team = Team::membersTeam();
// Sample members must not trigger real geocoding during a map test.
User::whereNull('lat')->orWhereNull('lon')->update(['lat' => 52.52, 'lon' => 13.405]);
$user = User::factory()->create(['email' => 'features-'.Str::uuid().'@example.test', 'current_team_id' => $team->id, 'lat' => 52.52, 'lon' => 13.405]);
$team->users()->attach($user, ['role' => Role::Admin->value]);
foreach (Reward::where('category', 'Statistiken')->orWhereIn('slug', ['mitgliederkarte', 'maddraxiversum'])->get() as $reward) {
    RewardPurchase::create(['user_id' => $user->id, 'reward_id' => $reward->id, 'wallet_team_id' => $team->id, 'cost_baxx' => $reward->cost_baxx, 'purchased_at' => now()]);
}
echo json_encode(['email' => $user->email], JSON_THROW_ON_ERROR);
