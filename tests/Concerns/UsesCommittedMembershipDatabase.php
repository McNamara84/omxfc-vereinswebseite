<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;

/** These tests exercise production transaction boundaries rather than RefreshDatabase's wrapper. */
trait UsesCommittedMembershipDatabase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->artisan('migrate:fresh', ['--seed' => true])->assertSuccessful();
        $this->assertSame(0, DB::transactionLevel());
    }

    protected function tearDown(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        RefreshDatabaseState::$migrated = false;
        RefreshDatabaseState::$inMemoryConnections = [];
        parent::tearDown();
    }
}
