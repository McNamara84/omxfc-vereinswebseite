<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\DatabaseMaintenance\DatabaseConnectionResolver;
use App\Services\DatabaseMaintenance\DatabaseMaintenanceException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

afterEach(function (): void {
    config(['database.default' => 'sqlite']);
});

test('current connection rejects sqlite when mysql like connections are required', function (): void {
    config(['database.default' => 'sqlite', 'database-maintenance.require_mysql_like_connection' => true]);
    expect(fn () => app(DatabaseConnectionResolver::class)->current())->toThrow(DatabaseMaintenanceException::class, 'MySQL/MariaDB');
});

test('current connection accepts mysql configuration', function (): void {
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => 'omxfc_test', 'database.connections.mysql.username' => 'omxfc', 'database.connections.mysql.password' => 'secret', 'database-maintenance.require_mysql_like_connection' => true]);
    $connection = app(DatabaseConnectionResolver::class)->current();
    expect($connection['driver'])->toBe('mysql')
        ->and(app(DatabaseConnectionResolver::class)->databaseName($connection))->toBe('omxfc_test');
});
