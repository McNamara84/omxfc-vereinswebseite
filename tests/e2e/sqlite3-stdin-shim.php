<?php

// Usage: sqlite3-stdin-shim.php <dbPath>
// Reads SQL from STDIN and applies it to the SQLite database via PDO.
// This is used as a Windows fallback when the sqlite3 CLI is not installed.

$dbPath = $argv[1] ?? null;

if (!is_string($dbPath) || $dbPath === '') {
    fwrite(STDERR, "Usage: php tests/e2e/sqlite3-stdin-shim.php <dbPath>\n");
    exit(2);
}

$schemaSql = stream_get_contents(STDIN);
if ($schemaSql === false) {
    fwrite(STDERR, "Failed to read SQL from STDIN.\n");
    exit(2);
}

$pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Same tiny SQL splitter as tests/e2e/load-sqlite-schema.php
$statements = [];
$buffer = '';
$len = strlen($schemaSql);
$inSingle = false;
$inDouble = false;
$inLineComment = false;
$inBlockComment = false;

for ($i = 0; $i < $len; $i++) {
    $ch = $schemaSql[$i];
    $next = $i + 1 < $len ? $schemaSql[$i + 1] : '';

    if ($inLineComment) {
        if ($ch === "\n") {
            $inLineComment = false;
        }
        continue;
    }

    if ($inBlockComment) {
        if ($ch === '*' && $next === '/') {
            $inBlockComment = false;
            $i++;
        }
        continue;
    }

    if (!$inSingle && !$inDouble) {
        if ($ch === '-' && $next === '-') {
            $inLineComment = true;
            $i++;
            continue;
        }

        if ($ch === '/' && $next === '*') {
            $inBlockComment = true;
            $i++;
            continue;
        }
    }

    if (!$inDouble && $ch === "'") {
        if ($inSingle && $next === "'") {
            $buffer .= "''";
            $i++;
            continue;
        }

        $inSingle = !$inSingle;
        $buffer .= $ch;
        continue;
    }

    if (!$inSingle && $ch === '"') {
        $inDouble = !$inDouble;
        $buffer .= $ch;
        continue;
    }

    if (!$inSingle && !$inDouble && $ch === ';') {
        $stmt = trim($buffer);
        if ($stmt !== '') {
            $statements[] = $stmt;
        }
        $buffer = '';
        continue;
    }

    $buffer .= $ch;
}

$tail = trim($buffer);
if ($tail !== '') {
    $statements[] = $tail;
}

$pdo->beginTransaction();
try {
    foreach ($statements as $stmt) {
        $pdo->exec($stmt);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Failed loading SQLite schema from STDIN: {$e->getMessage()}\n");
    exit(1);
}

exit(0);
