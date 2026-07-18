<?php

namespace Tests\Unit;

use App\Support\MaddraxikonIdentityHmacPeppers;
use LogicException;
use PHPUnit\Framework\TestCase;

class MaddraxikonIdentityHmacPeppersTest extends TestCase
{
    public function test_parser_rejects_duplicate_trimmed_version_names(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Versionsnamen v1 mehrfach');

        MaddraxikonIdentityHmacPeppers::parse(
            'v1:raw:'.str_repeat('a', 32).', v1 :raw:'.str_repeat('b', 32),
        );
    }

    public function test_fingerprint_is_domain_separated_by_wiki_and_version(): void
    {
        $secret = str_repeat('s', 32);
        $fingerprint = MaddraxikonIdentityHmacPeppers::fingerprint(
            'maddraxikon-de',
            'v1',
            $secret,
        );

        $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $fingerprint);
        $this->assertNotSame(
            $fingerprint,
            MaddraxikonIdentityHmacPeppers::fingerprint(
                'anderes-wiki',
                'v1',
                $secret,
            ),
        );
        $this->assertNotSame(
            $fingerprint,
            MaddraxikonIdentityHmacPeppers::fingerprint(
                'maddraxikon-de',
                'v2',
                $secret,
            ),
        );
    }
}
