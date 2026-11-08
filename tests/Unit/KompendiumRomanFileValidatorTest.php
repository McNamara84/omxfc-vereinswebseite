<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\KompendiumRoman;
use App\Services\KompendiumRomanFileValidator;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);
covers(KompendiumRomanFileValidator::class);
dataset('KompendiumRomanFileValidatorTest.invalidRomanFiles', static fn (): array => ['unbekannte Serie' => ['unbekannt', '001 - Test.txt', 'romane/unbekannt/001 - Test.txt'], 'leerer Dateiname' => ['maddrax', '', 'romane/maddrax/'], 'falsche Endung' => ['maddrax', '001 - Test.html', 'romane/maddrax/001 - Test.html'], 'Slash' => ['maddrax', 'archiv/001 - Test.txt', 'romane/maddrax/archiv/001 - Test.txt'], 'Backslash' => ['maddrax', 'archiv\001 - Test.txt', 'romane/maddrax/archiv\001 - Test.txt'], 'Steuerzeichen' => ['maddrax', "001 - Test\n.txt", "romane/maddrax/001 - Test\n.txt"], 'abweichender Pfad' => ['maddrax', '001 - Test.txt', 'romane/missionmars/001 - Test.txt'], 'Traversal im Pfad' => ['maddrax', '001 - Test.txt', 'romane/maddrax/../001 - Test.txt']]);
test('accepts expected txt path for known series', function (): void {
    $roman = new KompendiumRoman(['serie' => 'missionmars', 'dateiname' => '001 - Der rote Planet.txt', 'dateipfad' => 'romane/missionmars/001 - Der rote Planet.txt']);
    expect((new KompendiumRomanFileValidator)->hasValidStoragePath($roman))->toBeTrue();
});
test('rejects unsafe or inconsistent file data', function (string $serie, string $dateiname, string $dateipfad): void {
    $roman = new KompendiumRoman(['serie' => $serie, 'dateiname' => $dateiname, 'dateipfad' => $dateipfad]);
    expect((new KompendiumRomanFileValidator)->hasValidStoragePath($roman))->toBeFalse();
})->with('KompendiumRomanFileValidatorTest.invalidRomanFiles');
