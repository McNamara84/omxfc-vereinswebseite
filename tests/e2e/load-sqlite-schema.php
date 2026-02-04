<?php

// Usage: php tests/e2e/load-sqlite-schema.php <dbPath> <schemaPath>

$dbPath = $argv[1] ?? null;
$schemaPath = $argv[2] ?? null;

if (! is_string($dbPath) || $dbPath === '' || ! is_string($schemaPath) || $schemaPath === '') {
    fwrite(STDERR, "Usage: php tests/e2e/load-sqlite-schema.php <dbPath> <schemaPath>\n");
    // Exit code 2 is reserved for argument validation errors.
    exit(2);
}

if (! file_exists($schemaPath)) {
    fwrite(STDERR, "Schema file not found: {$schemaPath}\n");
    // Exit code 2 is reserved for argument validation errors.
    exit(2);
}

$schemaSql = file_get_contents($schemaPath);
if ($schemaSql === false) {
    fwrite(STDERR, "Failed to read schema file: {$schemaPath}\n");
    // Exit code 2 is reserved for argument validation errors.
    exit(2);
}

// Prefer SQLite's own SQL parsing by using the SQLite3 extension. This avoids having
// to implement (and maintain) a custom SQL statement splitter.
if (class_exists(SQLite3::class)) {
    $db = new SQLite3($dbPath);
    $db->enableExceptions(true);
    $db->busyTimeout(5000);

    try {
        $db->exec('BEGIN');
        $db->exec($schemaSql);
        $db->exec('COMMIT');
    } catch (Throwable $e) {
        try {
            $db->exec('ROLLBACK');
        } catch (Throwable $rollbackError) {
            // ignore
        }

        fwrite(STDERR, "Failed loading SQLite schema: {$e->getMessage()}\n");
        exit(1);
    } finally {
        $db->close();
    }
} else {
    // Fallback: try PDO::exec() for multi-statement SQL (driver-dependent).
    $pdo = new PDO('sqlite:'.$dbPath, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    try {
        $pdo->beginTransaction();
        $pdo->exec($schemaSql);
        $pdo->commit();
    } catch (Throwable $e) {
        try {
            $pdo->rollBack();
        } catch (Throwable $rollbackError) {
            // ignore
        }

        fwrite(STDERR, "Failed loading SQLite schema (PDO fallback): {$e->getMessage()}\n");
        exit(1);
    }
}

fwrite(STDOUT, "OK\n");
