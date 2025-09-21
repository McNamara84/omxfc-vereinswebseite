import fs from 'fs';
import path from 'path';
import { runArtisan } from './utils/artisan.js';

export default async function globalSetup() {
    const databasePath = path.resolve('database/playwright.sqlite');

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

    await runArtisan('migrate:fresh');
    await runArtisan('db:seed --class="Database\\\\Seeders\\\\TodoCategorySeeder"');
    await runArtisan('db:seed --class="Database\\\\Seeders\\\\TodoPlaywrightSeeder"');
}
