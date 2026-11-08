<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function Tools\Ci\compatibilityIssues;

require_once dirname(__DIR__, 2).'/tools/ci/php-compatibility.php';

class PhpCompatibilityAuditTest extends TestCase
{
    #[DataProvider('obsoleteCode')]
    public function test_it_detects_obsolete_php_usage_without_running_it(string $code): void
    {
        $this->assertNotEmpty(compatibilityIssues('<?php '.$code));
    }

    public static function obsoleteCode(): iterable
    {
        yield 'implicit CSV read escape' => ['fgetcsv($stream, 0, ";");'];
        yield 'implicit CSV write escape' => ['fputcsv($stream, $fields);'];
        yield 'implicit CSV string escape' => ['str_getcsv($line);'];
        yield 'fully qualified obsolete function' => ['\\imagedestroy($image);'];
        yield 'type alias' => ['is_integer($value);'];
        yield 'mbregex' => ['mb_ereg("test", $value);'];
        yield 'obsolete mbstring encoder' => ['mb_convert_encoding($html, "HTML-ENTITIES", "UTF-8");'];
        yield 'locale sorting' => ['sort($values, SORT_LOCALE_STRING);'];
        yield 'PDO driver constant' => ['$options = [PDO::MYSQL_ATTR_SSL_CA => $path];'];
        yield 'obsolete cast' => ['$value = (boolean) $input;'];
        yield 'finally return' => ['function value() { try { return 1; } finally { return 2; } }'];
        yield 'constructor return' => ['class Example { function __construct() { return 1; } }'];
        yield 'destructor generator' => ['class Example { function __destruct() { yield 1; } }'];
        yield 'soft-deprecated serialization' => ['class Example { function __sleep() { return []; } }'];
        yield 'nullable debug info' => ['class Example { function __debugInfo(): ?array { return []; } }'];
        yield 'union debug info' => ['class Example { function __debugInfo(): array|null { return []; } }'];
        yield 'obsolete define argument' => ['define("VALUE", 1, false);'];
        yield 'namespace class constant' => ['class Example { const NAMESPACE = "value"; }'];
    }

    #[DataProvider('compatibleCode')]
    public function test_it_preserves_valid_contexts_and_modern_replacements(string $code): void
    {
        $this->assertSame([], compatibilityIssues('<?php '.$code));
    }

    public static function compatibleCode(): iterable
    {
        yield 'named CSV escape' => ['fputcsv($stream, $fields, escape: "");'];
        yield 'positional CSV escape' => ['fgetcsv($stream, 0, ";", "\"", "");'];
        yield 'legacy CSV escape is explicit' => ['fgetcsv($stream, escape: "\\\\");'];
        yield 'collection method is unrelated' => ['$values->is_integer($value);'];
        yield 'SQL is unrelated' => ['$sql = "select strftime(time) from visits";'];
        yield 'nested closure may return inside finally' => ['function value() { try { return 1; } finally { $fn = fn () => 2; $cb = function () { return 3; }; } }'];
        yield 'nested closure in constructor may return' => ['class Example { function __construct() { $fn = function () { return 1; }; return; } }'];
        yield 'normal debug info' => ['class Example { function __debugInfo(): array { return []; } }'];
        yield 'PDO driver replacement' => ['$options = [Pdo\\Mysql::ATTR_SSL_CA => $path];'];
    }
}
