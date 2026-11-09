<?php

declare(strict_types=1);

use App\Models\Fanfiction;
use App\Models\User;

covers(Fanfiction::class);

test('fanfiction author names preserve their legacy and public display contracts', function (?User $author, ?string $externalName, string $legacyName, string $publicName): void {
    $fanfiction = new Fanfiction(['author_name' => $externalName]);
    $fanfiction->setRelation('author', $author);

    expect($fanfiction->getAttribute('author_display_name'))->toBe($legacyName)
        ->and($fanfiction->memberAuthorDisplayName())->toBe($publicName);
})->with([
    'linked member keeps the civil name and public nickname distinct' => [
        fn (): User => new User(['name' => 'Ada Name', 'alias' => 'FictionNick']),
        'External fallback', 'Ada Name', 'FictionNick',
    ],
    'external author' => [null, 'Freie Autorin', 'Freie Autorin', 'Freie Autorin'],
    'missing author' => [null, null, 'Unbekannt', 'Unbekannt'],
    'empty external name remains an empty string' => [null, '', '', ''],
]);
