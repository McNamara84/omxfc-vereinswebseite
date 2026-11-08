<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('standard fixtures can be modified inside the test transaction', function (): void {
    $admin = User::where('email', 'info@maddraxikon.com')->sole();
    $admin->update(['name' => 'Changed inside this transaction']);
    Team::membersTeam()->update(['description' => 'Changed inside this transaction']);
    expect($admin->fresh()->name)->toBe('Changed inside this transaction');
});

test('standard fixtures have no changes from previous tests', function (): void {
    expect(User::where('email', 'info@maddraxikon.com')->sole()->name)->toBe('Holger Ehrmann')
        ->and(Team::membersTeam()->description)->not->toBe('Changed inside this transaction');
    $this->assertDatabaseCount('users', 2);
})->depends('standard fixtures can be modified inside the test transaction');
