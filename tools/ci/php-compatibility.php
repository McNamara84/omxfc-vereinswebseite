<?php

declare(strict_types=1);

namespace Tools\Ci;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;

require_once dirname(__DIR__, 2).'/vendor/autoload.php';

/** @return list<string> */
function compatibilityIssues(string $code): array
{
    $nodes = (new ParserFactory)->createForVersion(PhpVersion::fromString('8.5'))->parse($code) ?? [];
    $issues = [];
    $obsolete = array_fill_keys(explode(' ', 'imagedestroy curl_close curl_share_close finfo_close utf8_encode utf8_decode strftime gmstrftime is_double is_long is_integer doubleval strcoll metaphone spl_classes spl_object_hash mb_ereg mb_ereg_match mb_ereg_replace mb_ereg_replace_callback mb_ereg_search mb_ereg_search_getpos mb_ereg_search_getregs mb_ereg_search_init mb_ereg_search_pos mb_ereg_search_regs mb_ereg_search_setpos mb_eregi mb_eregi_replace mb_regex_encoding mb_regex_set_options mb_split mhash mhash_count mhash_get_block_size mhash_get_hash_name mhash_keygen_s2k'), true);
    $visit = function (Node $node, bool $finally = false, ?string $method = null) use (&$visit, &$issues, $obsolete, $code): void {
        if ($node instanceof Node\FunctionLike || $node instanceof Stmt\ClassLike) {
            $finally = false;
            $method = $node instanceof Stmt\ClassMethod ? strtolower($node->name->toString()) : null;
        }
        $finally = $finally || $node instanceof Stmt\Finally_;
        $add = static function (string $message) use (&$issues, $node): void {
            $issues[] = 'line '.$node->getStartLine().': '.$message;
        };

        if ($node instanceof Stmt\Return_ && $finally) {
            $add('return inside finally discards the try/catch result');
        }
        if (in_array($method, ['__construct', '__destruct'], true)
            && (($node instanceof Stmt\Return_ && $node->expr !== null) || $node instanceof Expr\Yield_ || $node instanceof Expr\YieldFrom)) {
            $add('constructors/destructors must not return values or yield');
        }
        if ($node instanceof Stmt\ClassMethod && in_array(strtolower($node->name->toString()), ['__sleep', '__wakeup'], true)) {
            $add('use __serialize()/__unserialize() instead of soft-deprecated serialization hooks');
        }
        if ($node instanceof Stmt\ClassMethod && strtolower($node->name->toString()) === '__debuginfo'
            && ($node->returnType instanceof Node\NullableType
                || ($node->returnType instanceof Node\UnionType && array_any($node->returnType->types, static fn ($type) => $type instanceof Node\Identifier && strtolower($type->toString()) === 'null')))) {
            $add('__debugInfo() must not declare a nullable return');
        }
        if ($node instanceof Expr\FuncCall && $node->name instanceof Node\Name && count($node->name->getParts()) === 1) {
            $name = strtolower($node->name->toString());
            if (isset($obsolete[$name])) {
                $add('deprecated PHP function '.$name.'()');
            }
            $escapePosition = ['fgetcsv' => 4, 'fputcsv' => 4, 'str_getcsv' => 3][$name] ?? null;
            if ($escapePosition !== null) {
                $explicit = false;
                foreach ($node->args as $index => $arg) {
                    $explicit = $explicit || $arg->unpack || $arg->name?->toString() === 'escape' || ($index === $escapePosition && $arg->name === null);
                }
                if (! $explicit) {
                    $add($name.'() requires an explicit CSV escape argument');
                }
            }
            if ($name === 'define' && (count($node->args) >= 3 || array_any($node->args, static fn ($arg) => $arg->name?->toString() === 'case_insensitive'))) {
                $add('remove the obsolete third define() argument');
            }
            if ($name === 'mb_convert_encoding') {
                foreach ($node->args as $index => $arg) {
                    if (($index > 0 || $arg->name?->toString() === 'to_encoding') && $arg->value instanceof Node\Scalar\String_
                        && in_array(strtoupper($arg->value->value), ['HTML-ENTITIES', 'BASE64', 'QPRINT', 'UUENCODE'], true)) {
                        $add('use a dedicated encoder instead of mb_convert_encoding() for non-text encodings');
                    }
                }
            }
        }
        if ($node instanceof Expr\ConstFetch && in_array(strtoupper($node->name->toString()), ['SORT_LOCALE_STRING', 'DATE_RFC7231', 'MB_ONIGURUMA_VERSION'], true)) {
            $add('deprecated PHP constant '.$node->name->toString());
        }
        if ($node instanceof Expr\ClassConstFetch && $node->class instanceof Node\Name && $node->name instanceof Node\Identifier
            && strtolower($node->class->toString()) === 'pdo' && preg_match('/^(MYSQL|PGSQL|SQLITE|SQLSRV)_/', $node->name->toString())) {
            $add('use the PDO driver class constant instead of PDO::'.$node->name->toString());
        }
        if ($node instanceof Expr\Cast && preg_match('/^\(\s*(boolean|integer|double|binary)\s*\)/i', substr($code, $node->getStartFilePos(), $node->getEndFilePos() - $node->getStartFilePos() + 1))) {
            $add('use a canonical PHP cast name');
        }
        if (($node instanceof Stmt\ClassLike || $node instanceof Stmt\Function_) && $node->name !== null) {
            $name = strtolower($node->name->toString());
            if (in_array($name, ['let', 'is'], true) || ($node instanceof Stmt\Function_ && $name === 'readonly') || ($node instanceof Stmt\ClassLike && $name === '_')) {
                $add('reserved PHP identifier '.$name);
            }
        }
        if ($node instanceof Stmt\ClassConst) {
            foreach ($node->consts as $constant) {
                if (strtolower($constant->name->toString()) === 'namespace') {
                    $add('namespace is deprecated as a class constant name');
                }
            }
        }
        foreach ($node->getSubNodeNames() as $property) {
            foreach (is_array($node->$property) ? $node->$property : [$node->$property] as $child) {
                if ($child instanceof Node) {
                    $visit($child, $finally, $method);
                }
            }
        }
    };
    foreach ($nodes as $node) {
        $visit($node);
    }

    return $issues;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    $root = dirname(__DIR__, 2);
    $files = [];
    $compiledViews = in_array('--compiled-views', $argv, true);
    foreach ($compiledViews ? ['storage/framework/views'] : ['app', 'bootstrap', 'config', 'database', 'routes', 'resources/views', 'tests', 'tools'] as $directory) {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/'.$directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php' && ! str_contains(str_replace('\\', '/', $file->getPathname()), '/bootstrap/cache/')) {
                $files[] = $file->getPathname();
            }
        }
    }
    foreach ($compiledViews ? [] : ['artisan', 'server.php', 'public/index.php', 'rector.php'] as $file) {
        $files[] = $root.'/'.$file;
    }
    if ($files === []) {
        fwrite(STDERR, "No PHP files were available for the compatibility check.\n");
        exit(1);
    }
    $failures = [];
    foreach ($files as $file) {
        try {
            foreach (compatibilityIssues(file_get_contents($file)) as $issue) {
                $failures[] = substr($file, strlen($root) + 1).': '.$issue;
            }
            $process = proc_open([PHP_BINARY, '-d', 'error_reporting=-1', '-d', 'display_errors=stderr', '-l', $file], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (! is_resource($process)) {
                throw new \RuntimeException('Cannot start the PHP syntax check.');
            }
            $diagnostic = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            if (proc_close($process) !== 0 || preg_match('/(?:PHP )?Deprecated:/i', $diagnostic)) {
                $failures[] = $file.': '.$diagnostic;
            }
        } catch (\Throwable $error) {
            $failures[] = $file.': '.$error->getMessage();
        }
    }
    echo 'PHP 8.5/8.6 source check: '.count($files).' files; '.count($failures)." issues.\n";
    if ($failures !== []) {
        fwrite(STDERR, implode("\n", $failures)."\n");
        exit(1);
    }
}
