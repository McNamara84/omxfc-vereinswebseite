<?php

namespace Tests\Unit;

use App\Services\DatabaseMaintenance\DatabaseConnectionResolver;
use App\Services\DatabaseMaintenance\DatabaseMaintenanceException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseConnectionResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        config(['database.default' => 'sqlite']);

        parent::tearDown();
    }

    public function test_current_connection_rejects_sqlite_when_mysql_like_connections_are_required(): void
    {
        config([
            'database.default' => 'sqlite',
            'database-maintenance.require_mysql_like_connection' => true,
        ]);

        $this->expectException(DatabaseMaintenanceException::class);
        $this->expectExceptionMessage('MySQL/MariaDB');

        app(DatabaseConnectionResolver::class)->current();
    }

    public function test_current_connection_accepts_mysql_configuration(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'omxfc_test',
            'database.connections.mysql.username' => 'omxfc',
            'database.connections.mysql.password' => 'secret',
            'database-maintenance.require_mysql_like_connection' => true,
        ]);

        $connection = app(DatabaseConnectionResolver::class)->current();

        $this->assertSame('mysql', $connection['driver']);
        $this->assertSame('omxfc_test', app(DatabaseConnectionResolver::class)->databaseName($connection));
    }
}
