import fs from 'fs';
import path from 'path';
import { spawnSync } from 'child_process';
import { runArtisan } from './utils/artisan.js';
import { createPhpProcess, toPhpRuntimePath } from './utils/php.js';

const shouldHideWindowsShell = process.platform === 'win32';

export default async function globalSetup() {
    const databasePath = path.resolve('database/playwright.sqlite');
    const runtimeDatabasePath = toPhpRuntimePath(databasePath);
    const sqliteSchemaPath = path.resolve('database/schema/sqlite-schema.sql');
    const runtimeSqliteSchemaPath = toPhpRuntimePath(sqliteSchemaPath);
    const runToken = process.env.PLAYWRIGHT_RUN_TOKEN ?? 'local-default';
    const readyFilePath = path.resolve('storage/framework/testing', `playwright-ready-${runToken}.flag`);

    process.env.APP_ENV = 'testing';
    process.env.APP_DEBUG = 'false';
    process.env.APP_CONFIG_CACHE = 'bootstrap/cache/config-playwright.php';
    process.env.DB_CONNECTION = 'sqlite';
    process.env.DB_DATABASE = runtimeDatabasePath;
    process.env.SESSION_DRIVER = 'file';
    process.env.CACHE_STORE = 'array';
    process.env.CACHE_DRIVER = 'array';
    process.env.QUEUE_CONNECTION = 'database';
    process.env.MAIL_MAILER = 'array';
    process.env.COVER_RATINGS_ENABLED = 'true';
    process.env.COVER_RATINGS_SYNC_ENABLED = 'false';
    process.env.BILDERSUCHE_ENABLED = 'true';
    process.env.BILDERSUCHE_SYNC_ENABLED = 'false';
    process.env.BILDERSUCHE_AG_TEAM_ID = '900001';
    process.env.MADDRAXIKON_STREAKS_ENABLED = 'true';
    process.env.MADDRAXIKON_STREAKS_STARTS_AT = '2026-01-01T00:00:00Z';
    process.env.MADDRAXIKON_LINKING_ENABLED = 'true';
    process.env.MADDRAXIKON_SYNC_ENABLED = 'true';
    process.env.MADDRAXIKON_AWARDS_ENABLED = 'true';

    if (fs.existsSync(databasePath)) {
        fs.rmSync(databasePath);
    }

    const databaseDirectory = path.dirname(databasePath);
    if (!fs.existsSync(databaseDirectory)) {
        fs.mkdirSync(databaseDirectory, { recursive: true });
    }

    const readyFileDirectory = path.dirname(readyFilePath);
    if (!fs.existsSync(readyFileDirectory)) {
        fs.mkdirSync(readyFileDirectory, { recursive: true });
    }

    if (fs.existsSync(readyFilePath)) {
        fs.rmSync(readyFilePath);
    }

    fs.closeSync(fs.openSync(databasePath, 'w'));

    // The Playwright DB setup relies on the stored SQLite schema dump.
    // The regular Laravel schema loading path may call the external `sqlite3` CLI,
    // which isn't available on all dev machines (especially Windows). We therefore
    // load the dump via a tiny PHP helper and then run migrations afterwards.
    // Note: the schema dump should be kept in sync with migrations; running `migrate`
    // after the dump ensures any new migrations are applied in Playwright runs.
    if (!fs.existsSync(sqliteSchemaPath)) {
        throw new Error(`Missing schema dump: ${sqliteSchemaPath}`);
    }

    const schemaLoader = createPhpProcess(
        ['tests/e2e/load-sqlite-schema.php', runtimeDatabasePath, runtimeSqliteSchemaPath],
        { env: process.env },
    );
    const schemaResult = spawnSync(schemaLoader.command, schemaLoader.args, {
        env: process.env,
        shell: schemaLoader.shell,
        stdio: 'inherit',
        windowsHide: shouldHideWindowsShell,
    });

    if (schemaResult.status !== 0) {
        throw new Error('Failed to load SQLite schema dump for Playwright.');
    }

    // One application bootstrap replaces twelve separate Artisan processes.
    // The schema dump above still precedes the regular migration validation.
    await runArtisan(['migrate', '--seed', '--seeder=Database\\Seeders\\PlaywrightSuiteSeeder']);

    fs.writeFileSync(readyFilePath, 'ready\n');
}
