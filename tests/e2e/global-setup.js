import fs from 'fs';
import path from 'path';
import { config as loadEnv } from 'dotenv';
import { runArtisan } from './utils/artisan.js';

loadEnv({ path: '.env.testing', quiet: true });

export default async function globalSetup() {
    const databasePath = path.resolve('database/playwright.sqlite');

    process.env.APP_ENV = 'testing';
    process.env.APP_DEBUG = 'false';
    if (!process.env.APP_KEY) {
        throw new Error('APP_KEY environment variable must be provided for Playwright tests.');
    }
    process.env.DB_CONNECTION = 'sqlite';
    process.env.DB_DATABASE = databasePath;
    process.env.SESSION_DRIVER = 'file';
    process.env.CACHE_STORE = 'array';
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

    await runArtisan('migrate:fresh');
    await runArtisan('db:seed --class="Database\\\\Seeders\\\\TodoCategorySeeder"');
    await runArtisan('db:seed --class="Database\\\\Seeders\\\\TodoPlaywrightSeeder"');
    await runArtisan('db:seed --class="Database\\\\Seeders\\\\DashboardSampleSeeder"');
    await runArtisan('db:seed --class="Database\\\\Seeders\\\\ReviewsPlaywrightSeeder"');
}
