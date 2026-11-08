<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\RomanExcerpt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('it generates a stable typesense document id from the path', function (): void {
    $path = 'romane/maddrax/001 - Der Gott aus dem Eis.txt';
    expect(RomanExcerpt::scoutDocumentId($path))->toBe(RomanExcerpt::scoutDocumentId($path));
});

test('it exposes id and path in the searchable payload', function (): void {
    $excerpt = new RomanExcerpt(['path' => 'romane/maddrax/001 - Der Gott aus dem Eis.txt', 'cycle' => 'maddrax', 'roman_nr' => 1, 'title' => 'Der Gott aus dem Eis', 'body' => 'Matthew Drax reist erneut in die Zukunft.']);
    $payload = $excerpt->toSearchableArray();
    expect($payload['id'])->toBe($excerpt->getScoutKey())
        ->and($payload['path'])->toBe('romane/maddrax/001 - Der Gott aus dem Eis.txt')
        ->and($payload['roman_nr'])->toBe('1')
        ->and($payload)->toHaveKey('body');
});

test('it removes stop words without expanding the body into token arrays', function (): void {
    $excerpt = new RomanExcerpt(['path' => 'romane/maddrax/002 - Test.txt', 'cycle' => 'maddrax', 'roman_nr' => 2, 'title' => 'Test', 'body' => 'Und Matthew reist, weil die Zukunft anders ist.']);
    $payload = $excerpt->toSearchableArray();
    expect($payload['body'])->toBe('Matthew reist Zukunft anders');
});
