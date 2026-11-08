<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Services\DatabaseMaintenance\DatabaseDumpFile;
use App\Services\DatabaseMaintenance\DatabaseDumpService;
use App\Services\DatabaseMaintenance\DatabaseMaintenanceException;
use App\Services\DatabaseMaintenance\DatabaseMaintenanceLimitService;
use App\Services\DatabaseMaintenance\DatabaseRestoreResult;
use App\Services\DatabaseMaintenance\DatabaseRestoreService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Mockery\MockInterface;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(DatabaseMaintenanceControllerTestFixtures::class);

beforeEach(function (): void {
    $this->storageRoot = storage_path('framework/testing/database-maintenance-feature');
    File::deleteDirectory($this->storageRoot);
    File::ensureDirectoryExists($this->storageRoot);
    config(['database-maintenance.enabled' => true, 'database-maintenance.storage_root' => $this->storageRoot, 'database-maintenance.max_upload_mb' => 10, 'database-maintenance.proxy_limit_mb' => 10, 'database-maintenance.multipart_overhead_mb' => 0, 'database-maintenance.restore_confirmation_text' => 'DATENBANK WIEDERHERSTELLEN']);
});

afterEach(function (): void {
    File::deleteDirectory($this->storageRoot);
});

test('guest is redirected from database admin page', function (): void {
    $this->get(route('admin.datenbank.index'))->assertRedirect(route('login'));
});

test('only admin can view database admin page', function (): void {
    $this->actingAs($this->createUserWithRole(Role::Mitglied))->get(route('admin.datenbank.index'))->assertForbidden();
    $this->actingAs($this->createUserWithRole(Role::Vorstand))->get(route('admin.datenbank.index'))->assertForbidden();
    $this->actingAs($this->createUserWithRole(Role::Admin))->get(route('admin.datenbank.index'))->assertOk()->assertSeeText('Datenbank')->assertSeeText('Dump herunterladen')->assertSeeText('Effektive Upload-Grenze')->assertSee('accept=".sql,.sql.gz"', false)->assertDontSee('accept=".sql,.gz,.sql.gz"', false)->assertSee('<dl class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">', false);
});

test('database dump button bypasses livewire navigation', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $downloadUrl = route('admin.datenbank.dump');
    $html = $this->actingAs($admin)->get(route('admin.datenbank.index'))->assertOk()->getContent();
    $matched = preg_match('/<a\b(?=[^>]*href="'.preg_quote($downloadUrl, '/').'\")[^>]*>/', $html, $matches);
    expect($matched)->toBe(1)
        ->and($matches[0])->not->toContain('wire:navigate');
});

test('admin navigation contains database link', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $this->actingAs($admin)->get(route('dashboard'))->assertOk()->assertSeeText('Datenbank')->assertSee(route('admin.datenbank.index'));
});

test('database admin page requires explicit opt in', function (): void {
    config(['database-maintenance.enabled' => null]);
    $this->actingAs($this->createUserWithRole(Role::Admin))->get(route('admin.datenbank.index'))->assertNotFound();
});

test('database admin page can be disabled', function (): void {
    config(['database-maintenance.enabled' => false]);
    $this->actingAs($this->createUserWithRole(Role::Admin))->get(route('admin.datenbank.index'))->assertNotFound();
});

test('admin can download dump from service', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $dumpPath = $this->storageRoot.DIRECTORY_SEPARATOR.'download.sql.gz';
    File::put($dumpPath, 'dump');
    $this->mock(DatabaseDumpService::class, function (MockInterface $mock) use ($dumpPath): void {
        $mock->shouldReceive('createDownloadDump')->once()->andReturn(new DatabaseDumpFile($dumpPath, 'download.sql.gz'));
    });
    $response = $this->actingAs($admin)->get(route('admin.datenbank.dump'))->assertOk()->assertHeader('content-type', 'application/gzip');
    $contentDisposition = $response->headers->get('content-disposition') ?? '';
    expect(strtolower($contentDisposition))->toContain('attachment')
        ->and($contentDisposition)->toContain('download.sql.gz');
});

test('restore requires recent password confirmation', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $this->actingAs($admin)->post(route('admin.datenbank.restore'))->assertRedirect(route('password.confirm'));
});

test('admin can restore valid sql dump after password confirmation', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $preRestorePath = $this->storageRoot.DIRECTORY_SEPARATOR.'pre-restore.sql.gz';
    $this->mock(DatabaseRestoreService::class, function (MockInterface $mock) use ($preRestorePath): void {
        $mock->shouldReceive('restore')->once()->andReturn(new DatabaseRestoreResult($preRestorePath));
    });
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.datenbank.restore'), ['dump' => UploadedFile::fake()->createWithContent('dump.sql', 'select 1;'), 'confirmation' => 'DATENBANK WIEDERHERSTELLEN'])->assertRedirect(route('admin.datenbank.index'))->assertSessionHas('status');
});

test('restore invalid upload message uses german umlaut', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $this->mock(DatabaseRestoreService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('restore')->never();
    });
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.datenbank.restore'), ['dump' => 'not-a-file', 'confirmation' => 'DATENBANK WIEDERHERSTELLEN'])->assertSessionHasErrors('dump');
    expect(session('errors')->get('dump'))->toContain('Bitte lade eine gültige Datei hoch.');
});

test('restore rejects invalid file extension', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $this->mock(DatabaseRestoreService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('restore')->never();
    });
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.datenbank.restore'), ['dump' => UploadedFile::fake()->createWithContent('dump.txt', 'select 1;'), 'confirmation' => 'DATENBANK WIEDERHERSTELLEN'])->assertSessionHasErrors('dump');
});

test('restore rejects missing confirmation text', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $this->mock(DatabaseRestoreService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('restore')->never();
    });
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.datenbank.restore'), ['dump' => UploadedFile::fake()->createWithContent('dump.sql', 'select 1;'), 'confirmation' => 'FALSCH'])->assertSessionHasErrors('confirmation');
});

test('restore rejects upload when effective limit is zero bytes', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $this->mock(DatabaseMaintenanceLimitService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('limits')->once()->andReturn(['effective_upload_bytes' => 0, 'configured_max_upload_bytes' => 10 * 1024 * 1024]);
    });
    $this->mock(DatabaseRestoreService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('restore')->never();
    });
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.datenbank.restore'), ['dump' => UploadedFile::fake()->createWithContent('dump.sql', 'select 1;'), 'confirmation' => 'DATENBANK WIEDERHERSTELLEN'])->assertSessionHasErrors(['dump' => 'Die aktuell ermittelte Upload-Grenze liegt unter 1 KB. Der Restore ist deshalb blockiert. Bitte passe PHP-, Proxy- oder App-Limits an.']);
});

test('restore rejects upload when effective limit is below one kilobyte', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $this->mock(DatabaseMaintenanceLimitService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('limits')->once()->andReturn(['effective_upload_bytes' => 1023, 'configured_max_upload_bytes' => 10 * 1024 * 1024]);
    });
    $this->mock(DatabaseRestoreService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('restore')->never();
    });
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.datenbank.restore'), ['dump' => UploadedFile::fake()->createWithContent('dump.sql', 'x'), 'confirmation' => 'DATENBANK WIEDERHERSTELLEN'])->assertSessionHasErrors(['dump' => 'Die aktuell ermittelte Upload-Grenze liegt unter 1 KB. Der Restore ist deshalb blockiert. Bitte passe PHP-, Proxy- oder App-Limits an.']);
});

test('restore falls back to configured upload limit when effective limit is unknown', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $preRestorePath = $this->storageRoot.DIRECTORY_SEPARATOR.'pre-restore.sql.gz';
    $this->mock(DatabaseMaintenanceLimitService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('limits')->once()->andReturn(['effective_upload_bytes' => null, 'configured_max_upload_bytes' => 5 * 1024]);
    });
    $this->mock(DatabaseRestoreService::class, function (MockInterface $mock) use ($preRestorePath): void {
        $mock->shouldReceive('restore')->once()->andReturn(new DatabaseRestoreResult($preRestorePath));
    });
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.datenbank.restore'), ['dump' => UploadedFile::fake()->createWithContent('dump.sql', str_repeat('x', 2048)), 'confirmation' => 'DATENBANK WIEDERHERSTELLEN'])->assertRedirect(route('admin.datenbank.index'));
});

test('restore confirmation text may contain comma', function (): void {
    config(['database-maintenance.restore_confirmation_text' => 'DATENBANK, WIEDERHERSTELLEN']);
    $admin = $this->createUserWithRole(Role::Admin);
    $preRestorePath = $this->storageRoot.DIRECTORY_SEPARATOR.'pre-restore.sql.gz';
    $this->mock(DatabaseRestoreService::class, function (MockInterface $mock) use ($preRestorePath): void {
        $mock->shouldReceive('restore')->once()->andReturn(new DatabaseRestoreResult($preRestorePath));
    });
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.datenbank.restore'), ['dump' => UploadedFile::fake()->createWithContent('dump.sql', 'select 1;'), 'confirmation' => 'DATENBANK, WIEDERHERSTELLEN'])->assertRedirect(route('admin.datenbank.index'));
});

test('restore surfaces service failures as validation error', function (): void {
    $admin = $this->createUserWithRole(Role::Admin);
    $this->mock(DatabaseRestoreService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('restore')->once()->andThrow(new DatabaseMaintenanceException('Restore fehlgeschlagen.'));
    });
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])->post(route('admin.datenbank.restore'), ['dump' => UploadedFile::fake()->createWithContent('dump.sql.gz', 'not really gzipped'), 'confirmation' => 'DATENBANK WIEDERHERSTELLEN'])->assertSessionHasErrors('dump');
});
