<?php

namespace Tests\Unit;

use App\Models\KompendiumRoman;
use App\Services\KompendiumRomanFileValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(KompendiumRomanFileValidator::class)]
class KompendiumRomanFileValidatorTest extends TestCase
{
    public function test_accepts_expected_txt_path_for_known_series(): void
    {
        $roman = new KompendiumRoman([
            'serie' => 'missionmars',
            'dateiname' => '001 - Der rote Planet.txt',
            'dateipfad' => 'romane/missionmars/001 - Der rote Planet.txt',
        ]);

        $this->assertTrue((new KompendiumRomanFileValidator)->hasValidStoragePath($roman));
    }

    #[DataProvider('invalidRomanFiles')]
    public function test_rejects_unsafe_or_inconsistent_file_data(
        string $serie,
        string $dateiname,
        string $dateipfad,
    ): void {
        $roman = new KompendiumRoman([
            'serie' => $serie,
            'dateiname' => $dateiname,
            'dateipfad' => $dateipfad,
        ]);

        $this->assertFalse((new KompendiumRomanFileValidator)->hasValidStoragePath($roman));
    }

    public static function invalidRomanFiles(): array
    {
        return [
            'unbekannte Serie' => ['unbekannt', '001 - Test.txt', 'romane/unbekannt/001 - Test.txt'],
            'leerer Dateiname' => ['maddrax', '', 'romane/maddrax/'],
            'falsche Endung' => ['maddrax', '001 - Test.html', 'romane/maddrax/001 - Test.html'],
            'Slash' => ['maddrax', 'archiv/001 - Test.txt', 'romane/maddrax/archiv/001 - Test.txt'],
            'Backslash' => ['maddrax', 'archiv\\001 - Test.txt', 'romane/maddrax/archiv\\001 - Test.txt'],
            'Steuerzeichen' => ['maddrax', "001 - Test\n.txt", "romane/maddrax/001 - Test\n.txt"],
            'abweichender Pfad' => ['maddrax', '001 - Test.txt', 'romane/missionmars/001 - Test.txt'],
            'Traversal im Pfad' => ['maddrax', '001 - Test.txt', 'romane/maddrax/../001 - Test.txt'],
        ];
    }
}
