<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Services\DatabaseMaintenance\DatabaseDumpFile;
use App\Services\DatabaseMaintenance\DatabaseDumpService;
use App\Services\DatabaseMaintenance\DatabaseMaintenanceException;
use App\Services\DatabaseMaintenance\DatabaseMaintenanceLimitService;
use App\Services\DatabaseMaintenance\DatabaseRestoreResult;
use App\Services\DatabaseMaintenance\DatabaseRestoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class DatabaseMaintenanceControllerTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    private string $storageRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storageRoot = storage_path('framework/testing/database-maintenance-feature');
        File::deleteDirectory($this->storageRoot);
        File::ensureDirectoryExists($this->storageRoot);

        config([
            'database-maintenance.enabled' => true,
            'database-maintenance.storage_root' => $this->storageRoot,
            'database-maintenance.max_upload_mb' => 10,
            'database-maintenance.proxy_limit_mb' => 10,
            'database-maintenance.multipart_overhead_mb' => 0,
            'database-maintenance.restore_confirmation_text' => 'DATENBANK WIEDERHERSTELLEN',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storageRoot);

        parent::tearDown();
    }

    public function test_guest_is_redirected_from_database_admin_page(): void
    {
        $this->get(route('admin.datenbank.index'))
            ->assertRedirect(route('login'));
    }

    public function test_only_admin_can_view_database_admin_page(): void
    {
        $this->actingAs($this->createUserWithRole(Role::Mitglied))
            ->get(route('admin.datenbank.index'))
            ->assertForbidden();

        $this->actingAs($this->createUserWithRole(Role::Vorstand))
            ->get(route('admin.datenbank.index'))
            ->assertForbidden();

        $this->actingAs($this->createUserWithRole(Role::Admin))
            ->get(route('admin.datenbank.index'))
            ->assertOk()
            ->assertSeeText('Datenbank')
            ->assertSeeText('Dump herunterladen')
            ->assertSeeText('Effektive Upload-Grenze')
            ->assertSee('accept=".sql,.sql.gz"', false)
            ->assertDontSee('accept=".sql,.gz,.sql.gz"', false)
            ->assertSee('<dl class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">', false);
    }

    public function test_admin_navigation_contains_database_link(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Datenbank')
            ->assertSee(route('admin.datenbank.index'));
    }

    public function test_database_admin_page_requires_explicit_opt_in(): void
    {
        config(['database-maintenance.enabled' => null]);

        $this->actingAs($this->createUserWithRole(Role::Admin))
            ->get(route('admin.datenbank.index'))
            ->assertNotFound();
    }

    public function test_database_admin_page_can_be_disabled(): void
    {
        config(['database-maintenance.enabled' => false]);

        $this->actingAs($this->createUserWithRole(Role::Admin))
            ->get(route('admin.datenbank.index'))
            ->assertNotFound();
    }

    public function test_admin_can_download_dump_from_service(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $dumpPath = $this->storageRoot.DIRECTORY_SEPARATOR.'download.sql.gz';
        File::put($dumpPath, 'dump');

        $this->mock(DatabaseDumpService::class, function (MockInterface $mock) use ($dumpPath): void {
            $mock->shouldReceive('createDownloadDump')
                ->once()
                ->andReturn(new DatabaseDumpFile($dumpPath, 'download.sql.gz'));
        });

        $this->actingAs($admin)
            ->get(route('admin.datenbank.dump'))
            ->assertOk()
            ->assertHeader('content-type', 'application/gzip');
    }

    public function test_restore_requires_recent_password_confirmation(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $this->actingAs($admin)
            ->post(route('admin.datenbank.restore'))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_admin_can_restore_valid_sql_dump_after_password_confirmation(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $preRestorePath = $this->storageRoot.DIRECTORY_SEPARATOR.'pre-restore.sql.gz';

        $this->mock(DatabaseRestoreService::class, function (MockInterface $mock) use ($preRestorePath): void {
            $mock->shouldReceive('restore')
                ->once()
                ->andReturn(new DatabaseRestoreResult($preRestorePath));
        });

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.datenbank.restore'), [
                'dump' => UploadedFile::fake()->createWithContent('dump.sql', 'select 1;'),
                'confirmation' => 'DATENBANK WIEDERHERSTELLEN',
            ])
            ->assertRedirect(route('admin.datenbank.index'))
            ->assertSessionHas('status');
    }

    public function test_restore_invalid_upload_message_uses_german_umlaut(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $this->mock(DatabaseRestoreService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('restore')->never();
        });

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.datenbank.restore'), [
                'dump' => 'not-a-file',
                'confirmation' => 'DATENBANK WIEDERHERSTELLEN',
            ])
            ->assertSessionHasErrors('dump');

        $this->assertContains('Bitte lade eine gültige Datei hoch.', session('errors')->get('dump'));
    }

    public function test_restore_rejects_invalid_file_extension(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $this->mock(DatabaseRestoreService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('restore')->never();
        });

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.datenbank.restore'), [
                'dump' => UploadedFile::fake()->createWithContent('dump.txt', 'select 1;'),
                'confirmation' => 'DATENBANK WIEDERHERSTELLEN',
            ])
            ->assertSessionHasErrors('dump');
    }

    public function test_restore_rejects_missing_confirmation_text(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $this->mock(DatabaseRestoreService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('restore')->never();
        });

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.datenbank.restore'), [
                'dump' => UploadedFile::fake()->createWithContent('dump.sql', 'select 1;'),
                'confirmation' => 'FALSCH',
            ])
            ->assertSessionHasErrors('confirmation');
    }

    public function test_restore_rejects_upload_when_effective_limit_is_zero_bytes(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $this->mock(DatabaseMaintenanceLimitService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('limits')
                ->once()
                ->andReturn([
                    'effective_upload_bytes' => 0,
                    'configured_max_upload_bytes' => 10 * 1024 * 1024,
                ]);
        });

        $this->mock(DatabaseRestoreService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('restore')->never();
        });

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.datenbank.restore'), [
                'dump' => UploadedFile::fake()->createWithContent('dump.sql', 'select 1;'),
                'confirmation' => 'DATENBANK WIEDERHERSTELLEN',
            ])
            ->assertSessionHasErrors('dump');
    }

    public function test_restore_rejects_upload_when_effective_limit_is_below_one_kilobyte(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $this->mock(DatabaseMaintenanceLimitService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('limits')
                ->once()
                ->andReturn([
                    'effective_upload_bytes' => 1023,
                    'configured_max_upload_bytes' => 10 * 1024 * 1024,
                ]);
        });

        $this->mock(DatabaseRestoreService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('restore')->never();
        });

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.datenbank.restore'), [
                'dump' => UploadedFile::fake()->createWithContent('dump.sql', 'x'),
                'confirmation' => 'DATENBANK WIEDERHERSTELLEN',
            ])
            ->assertSessionHasErrors('dump');
    }

    public function test_restore_falls_back_to_configured_upload_limit_when_effective_limit_is_unknown(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);
        $preRestorePath = $this->storageRoot.DIRECTORY_SEPARATOR.'pre-restore.sql.gz';

        $this->mock(DatabaseMaintenanceLimitService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('limits')
                ->once()
                ->andReturn([
                    'effective_upload_bytes' => null,
                    'configured_max_upload_bytes' => 5 * 1024,
                ]);
        });

        $this->mock(DatabaseRestoreService::class, function (MockInterface $mock) use ($preRestorePath): void {
            $mock->shouldReceive('restore')
                ->once()
                ->andReturn(new DatabaseRestoreResult($preRestorePath));
        });

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.datenbank.restore'), [
                'dump' => UploadedFile::fake()->createWithContent('dump.sql', str_repeat('x', 2048)),
                'confirmation' => 'DATENBANK WIEDERHERSTELLEN',
            ])
            ->assertRedirect(route('admin.datenbank.index'));
    }

    public function test_restore_confirmation_text_may_contain_comma(): void
    {
        config(['database-maintenance.restore_confirmation_text' => 'DATENBANK, WIEDERHERSTELLEN']);

        $admin = $this->createUserWithRole(Role::Admin);
        $preRestorePath = $this->storageRoot.DIRECTORY_SEPARATOR.'pre-restore.sql.gz';

        $this->mock(DatabaseRestoreService::class, function (MockInterface $mock) use ($preRestorePath): void {
            $mock->shouldReceive('restore')
                ->once()
                ->andReturn(new DatabaseRestoreResult($preRestorePath));
        });

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.datenbank.restore'), [
                'dump' => UploadedFile::fake()->createWithContent('dump.sql', 'select 1;'),
                'confirmation' => 'DATENBANK, WIEDERHERSTELLEN',
            ])
            ->assertRedirect(route('admin.datenbank.index'));
    }

    public function test_restore_surfaces_service_failures_as_validation_error(): void
    {
        $admin = $this->createUserWithRole(Role::Admin);

        $this->mock(DatabaseRestoreService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('restore')
                ->once()
                ->andThrow(new DatabaseMaintenanceException('Restore fehlgeschlagen.'));
        });

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.datenbank.restore'), [
                'dump' => UploadedFile::fake()->createWithContent('dump.sql.gz', 'not really gzipped'),
                'confirmation' => 'DATENBANK WIEDERHERSTELLEN',
            ])
            ->assertSessionHasErrors('dump');
    }
}
