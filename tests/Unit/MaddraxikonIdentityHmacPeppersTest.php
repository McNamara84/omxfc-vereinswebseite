<?php

declare(strict_types=1);

use App\Support\MaddraxikonIdentityHmacPeppers;

mutates(MaddraxikonIdentityHmacPeppers::class);
covers(MaddraxikonIdentityHmacPeppers::class);

test('parser rejects duplicate trimmed version names', function () {
    expect(fn () => MaddraxikonIdentityHmacPeppers::parse(
        'v1:raw:'.str_repeat('a', 32).', v1 :raw:'.str_repeat('b', 32),
    ))->toThrow(LogicException::class, 'Versionsnamen v1 mehrfach');
});

test('fingerprint is domain-separated by wiki and version', function () {
    $secret = str_repeat('s', 32);
    $fingerprint = MaddraxikonIdentityHmacPeppers::fingerprint(
        'maddraxikon-de',
        'v1',
        $secret,
    );

    expect($fingerprint)->toBeHexadecimal()
        ->toHaveLength(64)
        ->toBe(strtolower($fingerprint))
        ->and(MaddraxikonIdentityHmacPeppers::fingerprint('anderes-wiki', 'v1', $secret))->not->toBe($fingerprint)
        ->and(MaddraxikonIdentityHmacPeppers::fingerprint('maddraxikon-de', 'v2', $secret))->not->toBe($fingerprint);
});

test('resolver decodes a Base64-encoded pepper', function () {
    $secret = str_repeat('s', 32);
    $encodedSecret = base64_encode($secret);

    expect($encodedSecret)->toBeBase64()
        ->and(MaddraxikonIdentityHmacPeppers::resolve([
            'v1' => 'base64:'.$encodedSecret,
        ]))->toBe(['v1' => $secret]);
});
