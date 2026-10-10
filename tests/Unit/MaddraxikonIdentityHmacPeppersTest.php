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
        ->toBe('91a60628bb868121a5c23ddef0e208f5340766e5ed91ee171cd9a573c81da47d')
        ->toBe(strtolower($fingerprint))
        ->and(MaddraxikonIdentityHmacPeppers::fingerprint('anderes-wiki', 'v1', $secret))->not->toBe($fingerprint)
        ->and(MaddraxikonIdentityHmacPeppers::fingerprint('maddraxikon-de', 'v2', $secret))->not->toBe($fingerprint)
        ->and(MaddraxikonIdentityHmacPeppers::fingerprint('maddraxikon-de', 'v1', str_repeat('t', 32)))->not->toBe($fingerprint);
});

test('parser preserves rotation order, encoded prefixes and colon-containing secrets', function () {
    expect(MaddraxikonIdentityHmacPeppers::parse(' , v2 : raw:'.str_repeat('a', 32).':suffix , v1: base64:YWJj , '))
        ->toBe(['v2' => 'raw:'.str_repeat('a', 32).':suffix', 'v1' => 'base64:YWJj'])
        ->and(MaddraxikonIdentityHmacPeppers::parse(''))->toBeEmpty()
        ->and(MaddraxikonIdentityHmacPeppers::parse('v1'))->toBe(['v1' => '']);
});

test('resolver preserves a secondary legacy key and accepts exact byte and version boundaries', function () {
    $secret = str_repeat('s', 32);
    $longVersion = str_repeat('v', 64);

    expect(MaddraxikonIdentityHmacPeppers::resolve([
        ' v2._- ' => ' raw:'.$secret.' ',
        $longVersion => $secret.'extra',
        12 => $secret,
        'legacy-app-key' => $secret,
    ]))->toBe(['v2._-' => $secret, $longVersion => $secret.'extra', 12 => $secret, 'legacy-app-key' => $secret]);
});

test('resolver rejects absent configuration', function (mixed $configuration) {
    expect(fn () => MaddraxikonIdentityHmacPeppers::resolve($configuration))
        ->toThrow(LogicException::class, 'muss vor der Kontoverknüpfung konfiguriert');
})->with([[null], [false], [''], ['v1:secret'], [[]]]);

test('resolver rejects invalid entries', function (array $configuration) {
    expect(fn () => MaddraxikonIdentityHmacPeppers::resolve($configuration))
        ->toThrow(LogicException::class, 'ungültigen Eintrag');
})->with([
    'empty version' => [['' => str_repeat('s', 32)]],
    'whitespace version' => [['  ' => str_repeat('s', 32)]],
    'invalid version character' => [['v/1' => str_repeat('s', 32)]],
    'too long version' => [[str_repeat('v', 65) => str_repeat('s', 32)]],
    'empty secret' => [['v1' => '']],
    'whitespace secret' => [['v1' => '  ']],
    'array secret' => [['v1' => []]],
    'null secret' => [['v1' => null]],
    'boolean secret' => [['v1' => true]],
]);

test('resolver rejects short decoded secrets', function (string $secret) {
    expect(fn () => MaddraxikonIdentityHmacPeppers::resolve(['v1' => $secret]))
        ->toThrow(LogicException::class, 'mindestens 32 Byte');
})->with([str_repeat('s', 31), 'raw:'.str_repeat('s', 31), 'base64:'.base64_encode(str_repeat('s', 31))]);

test('resolver rejects malformed Base64 and a primary legacy key', function () {
    expect(fn () => MaddraxikonIdentityHmacPeppers::resolve(['v1' => 'base64:'.str_repeat('!', 48)]))
        ->toThrow(LogicException::class, 'nicht gültig Base64-kodiert')
        ->and(fn () => MaddraxikonIdentityHmacPeppers::resolve(['legacy-app-key' => str_repeat('s', 32)]))
        ->toThrow(LogicException::class, 'nicht der primäre Identitäts-Pepper');
});

test('resolver decodes a Base64-encoded pepper', function () {
    $secret = str_repeat('s', 32);
    $encodedSecret = base64_encode($secret);

    expect($encodedSecret)->toBeBase64()
        ->and(MaddraxikonIdentityHmacPeppers::resolve([
            'v1' => 'base64:'.$encodedSecret,
        ]))->toBe(['v1' => $secret]);
});
