<?php

use App\Enums\BookType;
use App\Enums\Role;
use App\Enums\TodoStatus;
use App\Models\Book;
use App\Models\Team;
use App\Models\Todo;
use App\Models\User;
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
$scenario = $argv[1] ?? '';
if (! in_array($scenario, ['review', 'challenge'], true)) {
    throw new InvalidArgumentException('Expected a review or challenge fixture.');
}
Http::fake();
$members = Team::membersTeam() ?? throw new RuntimeException('Seed the Playwright suite first.');

// Every invocation creates independent records, including retries and other projects.
// Write before reading inside the transaction to avoid SQLite lock upgrades and
// serialize book-number allocation when browser projects create fixtures together.
$fixture = DB::transaction(function () use ($scenario, $members): array {
    $suffix = (string) Str::uuid();
    $member = User::factory()->create([
        'email' => "ux-{$scenario}-{$suffix}@example.test",
        'current_team_id' => $members->id,
    ]);
    $members->users()->attach($member, ['role' => Role::Mitglied->value]);

    if ($scenario === 'review') {
        $book = Book::create([
            'roman_number' => (int) Book::where('type', BookType::MaddraxDieDunkleZukunftDerErde)->max('roman_number') + 1,
            'title' => 'UX-Rezensionsprüfung '.$suffix,
            'author' => 'UX-Testautor',
            'cycle' => 'UX-Testzyklus',
            'type' => BookType::MaddraxDieDunkleZukunftDerErde,
        ]);

        return ['email' => $member->email, 'bookId' => $book->id];
    }

    $todo = Todo::create([
        'team_id' => $members->id,
        'created_by' => $member->id,
        'assigned_to' => $member->id,
        'title' => 'UX-Abschlussprüfung '.$suffix,
        'description' => 'Eigene abgeschlossene Challenge für diesen Browser-Testversuch.',
        'points' => 5,
        'status' => TodoStatus::Completed,
        'completed_at' => now(),
    ]);

    return ['todoId' => $todo->id, 'todoTitle' => $todo->title];
}, 5);

echo json_encode($fixture, JSON_THROW_ON_ERROR).PHP_EOL;
