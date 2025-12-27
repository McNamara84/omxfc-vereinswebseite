<?php

// Usage: sqlite3-stdin-shim.php <dbPath>
// Reads SQL from STDIN and applies it to the SQLite database via PDO.
// This is used as a Windows fallback when the sqlite3 CLI is not installed.

$dbPath = $argv[1] ?? null;

if (!is_string($dbPath) || $dbPath === '') {
    fwrite(STDERR, "Usage: php tests/e2e/sqlite3-stdin-shim.php <dbPath>\n");
    // Exit code 2 is reserved for argument validation errors.
    exit(2);
}

$schemaSql = stream_get_contents(STDIN);
if ($schemaSql === false) {
    fwrite(STDERR, "Failed to read SQL from STDIN.\n");
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

        fwrite(STDERR, "Failed loading SQLite schema from STDIN: {$e->getMessage()}\n");
        exit(1);
    } finally {
        $db->close();
    }
} else {
    // Fallback: try PDO::exec() for multi-statement SQL (driver-dependent).
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [
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

        fwrite(STDERR, "Failed loading SQLite schema from STDIN (PDO fallback): {$e->getMessage()}\n");
        exit(1);
    }
}

exit(0);
