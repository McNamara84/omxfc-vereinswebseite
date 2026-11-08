<?php

declare(strict_types=1);

namespace Tests\Support;

use Pest\Factories\Attribute;
use Pest\PendingCalls\TestCall;
use Pest\TestSuite;

/**
 * Preserve PHPUnit metadata for which Pest 5 has no fluent equivalent.
 * Keep the factory interaction here so upgrades can verify a single bridge.
 */
final class PhpunitMetadata
{
    /** @param class-string $attribute */
    public static function method(TestCall $test, string $attribute): TestCall
    {
        $test->testCaseMethod->attributes[] = new Attribute($attribute, []);

        return $test;
    }

    /** @param class-string $attribute */
    public static function onFile(string $filename, string $attribute, string ...$arguments): void
    {
        $factory = TestSuite::getInstance()->tests->get(realpath($filename));
        if ($factory === null) {
            // TIA can exclude this file before its metadata is registered.
            return;
        }

        $factory->attributes[] = new Attribute($attribute, $arguments);
    }
}
