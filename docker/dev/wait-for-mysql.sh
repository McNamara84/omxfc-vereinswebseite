#!/bin/sh

set -eu

until php -r 'try { new PDO(sprintf("mysql:host=%s;port=%s;dbname=%s", getenv("DB_HOST") ?: "db", getenv("DB_PORT") ?: "3306", getenv("DB_DATABASE") ?: "omxfc_dev"), getenv("DB_USERNAME") ?: "omxfc", getenv("DB_PASSWORD") ?: "omxfc"); exit(0); } catch (Throwable $exception) { fwrite(STDERR, $exception->getMessage().PHP_EOL); exit(1); }'; do
    sleep 2
done