<?php

use App\Enums\BookType;
use App\Enums\Role;
use App\Enums\TourAssignmentSource;
use App\Models\Book;
use App\Models\Team;
use App\Models\TourAssignment;
use App\Models\TourRewardGrant;
use App\Models\User;
use App\Models\UserPoint;
use App\Services\MaddraxDataService;
use App\Services\Maddraxikon\MaddraxikonSnapshotRepository;
use App\Services\Profile\SeriesProfileOptions;
use App\Services\TourAssignmentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'sqlite' || basename(str_replace('\\', '/', (string) config('database.connections.sqlite.database'))) !== 'playwright.sqlite') {
    throw new RuntimeException('This fixture requires the isolated Playwright database.');
}
Http::fake();
if (($argv[1] ?? '') === 'inspect') {
    $userId = (int) ($argv[2] ?? 0);
    echo json_encode([
        'grants' => TourRewardGrant::where('user_id', $userId)->count(),
        'points' => (int) UserPoint::where('user_id', $userId)->sum('points'),
        'completed' => TourAssignment::where('user_id', $userId)->where('tour_key', 'profilpflege')->where('tour_version', 3)->value('status'),
    ], JSON_THROW_ON_ERROR);
    exit;
}

// Seed missing catalog data only in the guarded test database, never in app storage.
if (MaddraxDataService::loadData() === []) {
    DB::transaction(fn () => app(MaddraxikonSnapshotRepository::class)->storeAndActivate(
        (string) Str::uuid(),
        ['maddrax' => json_encode([[
            'nummer' => 1, 'titel' => 'Tour-Testroman', 'text' => ['Tour-Testautor'], 'zyklus' => 'Tour-Testzyklus',
            'personen' => ['Tour-Testfigur'], 'orte' => ['Tour-Testort'], 'schlagworte' => ['Tour-Testthema'],
        ]], JSON_THROW_ON_ERROR)],
    ));
}
Book::firstOrCreate(['type' => BookType::MaddraxHardcover, 'roman_number' => 1], ['title' => 'Tour-Testhardcover', 'author' => 'Tour-Testautor']);
$team = Team::membersTeam() ?? throw new RuntimeException('Missing seeded members team.');
$user = User::factory()->create(['email' => 'profile-tour-'.Str::uuid().'@example.test', 'current_team_id' => $team->id]);
$team->users()->attach($user, ['role' => Role::Mitglied->value]);
$values = [];
foreach (app(SeriesProfileOptions::class)->all() as $field => $options) {
    $values[$field] = $options[0] ?? throw new RuntimeException('Missing option: '.$field);
}
$values['lieblingsmutation'] = 'Tour-Testmutation';
if (($argv[1] ?? '') === 'complete') {
    $user->forceFill($values)->save();
    TourAssignment::create([
        'user_id' => $user->id, 'tour_key' => 'profilpflege', 'tour_version' => 2,
        'status' => 'completed', 'assigned_via' => 'system', 'assigned_at' => now()->subMonth(), 'completed_at' => now()->subMonth(),
    ]);
}
$service = app(TourAssignmentService::class);
$service->assignIfMissing($user, 'hauptmenue', TourAssignmentSource::System);
$assignment = $service->assignIfMissing($user, 'profilpflege', TourAssignmentSource::System);
echo json_encode(['email' => $user->email, 'userId' => $user->id, 'assignmentId' => $assignment->id, 'values' => $values], JSON_THROW_ON_ERROR);
