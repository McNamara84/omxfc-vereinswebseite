import fs from 'fs';
import path from 'path';
import { spawnSync } from 'child_process';
import { runArtisan } from './utils/artisan.js';

export default async function globalSetup() {
    const databasePath = path.resolve('database/playwright.sqlite');
    const sqliteSchemaPath = path.resolve('database/schema/sqlite-schema.sql');

    process.env.APP_ENV = 'testing';
    process.env.APP_DEBUG = 'false';
    process.env.APP_KEY = process.env.APP_KEY ?? 'base64:oK0ZsJlI+o7C++h527lMcrrO4jzZrXqhouB/p0l+gFw=';
    process.env.DB_CONNECTION = 'sqlite';
    process.env.DB_DATABASE = databasePath;
    process.env.SESSION_DRIVER = 'file';
    process.env.CACHE_DRIVER = 'array';
    process.env.QUEUE_CONNECTION = 'database';
    process.env.MAIL_MAILER = 'array';

    if (fs.existsSync(databasePath)) {
        fs.rmSync(databasePath);
    }

    const databaseDirectory = path.dirname(databasePath);
    if (!fs.existsSync(databaseDirectory)) {
        fs.mkdirSync(databaseDirectory, { recursive: true });
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

    const schemaResult = spawnSync(
        'php',
        ['tests/e2e/load-sqlite-schema.php', databasePath, sqliteSchemaPath],
        {
            env: process.env,
            stdio: 'inherit',
        },
    );

    if (schemaResult.status !== 0) {
        throw new Error('Failed to load SQLite schema dump for Playwright.');
    }

    await runArtisan(['migrate']);

    await runArtisan(['db:seed', '--class=Database\\Seeders\\TodoCategorySeeder']);
    await runArtisan(['db:seed', '--class=Database\\Seeders\\TodoPlaywrightSeeder']);
    await runArtisan(['db:seed', '--class=Database\\Seeders\\DashboardSampleSeeder']);
}
