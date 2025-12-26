<?php

// Usage: php tests/e2e/load-sqlite-schema.php <dbPath> <schemaPath>

$dbPath = $argv[1] ?? null;
$schemaPath = $argv[2] ?? null;

if (!is_string($dbPath) || $dbPath === '' || !is_string($schemaPath) || $schemaPath === '') {
    fwrite(STDERR, "Usage: php tests/e2e/load-sqlite-schema.php <dbPath> <schemaPath>\n");
    exit(2);
}

if (!file_exists($schemaPath)) {
    fwrite(STDERR, "Schema file not found: {$schemaPath}\n");
    exit(2);
}

$schemaSql = file_get_contents($schemaPath);
if ($schemaSql === false) {
    fwrite(STDERR, "Failed to read schema file: {$schemaPath}\n");
    exit(2);
}

$pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// A tiny SQL splitter that ignores semicolons inside strings and strips comments.
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
        // Start of line comment: -- ...
        if ($ch === '-' && $next === '-') {
            $inLineComment = true;
            $i++;
            continue;
        }

        // Start of block comment: /* ... */
        if ($ch === '/' && $next === '*') {
            $inBlockComment = true;
            $i++;
            continue;
        }
    }

    // Toggle string states.
    if (!$inDouble && $ch === "'") {
        // SQLite escapes single quotes by doubling them: ''
        $prev = $i > 0 ? $schemaSql[$i - 1] : '';
        if ($inSingle && $next === "'") {
            // Escaped quote inside string.
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
    fwrite(STDERR, "Failed loading SQLite schema: {$e->getMessage()}\n");
    exit(1);
}

fwrite(STDOUT, "OK\n");
