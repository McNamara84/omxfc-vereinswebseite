<?php

namespace Database\Seeders;

use App\Models\KompendiumRoman;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Seeder für Playwright E2E-Tests des Kompendium-Admin-Dashboards.
 * Erstellt Testdaten für Upload, Indexierung und Filterung.
 */
class KompendiumPlaywrightSeeder extends Seeder
{
    public function run(): void
    {
        $team = Team::membersTeam();

        // Stelle sicher, dass der Admin-User existiert
        $admin = User::firstWhere('email', 'info@maddraxikon.com');

        if (! $admin) {
            $admin = User::withoutEvents(function () {
                $user = User::create([
                    'name' => 'Holger Ehrmann',
                    'email' => 'info@maddraxikon.com',
                    'email_verified_at' => now(),
                    'password' => Hash::make('password'),
                    'vorname' => 'Holger',
                    'nachname' => 'Ehrmann',
                    'strasse' => 'Musterstraße',
                    'hausnummer' => '123',
                    'plz' => '12345',
                    'stadt' => 'Musterstadt',
                    'land' => 'Deutschland',
                    'lat' => 48.137154,
                    'lon' => 11.576124,
                    'telefon' => '0123456789',
                    'verein_gefunden' => 'Sonstiges',
                    'mitgliedsbeitrag' => 36.00,
                ]);

                return $user;
            });

            if ($team) {
                $team->users()->attach($admin, ['role' => 'Admin']);
                $admin->forceFill(['current_team_id' => $team->id])->save();
            }
        }

        // Erstelle Test-Verzeichnisse für Romane auf dem private Disk
        Storage::disk('private')->makeDirectory('romane/maddrax');
        Storage::disk('private')->makeDirectory('romane/missionmars');
        Storage::disk('private')->makeDirectory('romane/hardcovers');

        // Erstelle Testdaten für verschiedene Status
        $testRomane = [
            // Hochgeladene, nicht indexierte Romane
            [
                'dateiname' => '001 - Der Gott aus dem Eis.txt',
                'dateipfad' => 'romane/maddrax/001 - Der Gott aus dem Eis.txt',
                'serie' => 'maddrax',
                'roman_nr' => 1,
                'titel' => 'Der Gott aus dem Eis',
                'zyklus' => 'Erde in Scherben',
                'status' => 'hochgeladen',
            ],
            [
                'dateiname' => '002 - Dämonen der Vergangenheit.txt',
                'dateipfad' => 'romane/maddrax/002 - Dämonen der Vergangenheit.txt',
                'serie' => 'maddrax',
                'roman_nr' => 2,
                'titel' => 'Dämonen der Vergangenheit',
                'zyklus' => 'Erde in Scherben',
                'status' => 'hochgeladen',
            ],
            // Indexierte Romane
            [
                'dateiname' => '003 - Stadt ohne Hoffnung.txt',
                'dateipfad' => 'romane/maddrax/003 - Stadt ohne Hoffnung.txt',
                'serie' => 'maddrax',
                'roman_nr' => 3,
                'titel' => 'Stadt ohne Hoffnung',
                'zyklus' => 'Erde in Scherben',
                'status' => 'indexiert',
                'indexiert_am' => now(),
            ],
            // Mission Mars Roman
            [
                'dateiname' => '001 - Expedition zum roten Planeten.txt',
                'dateipfad' => 'romane/missionmars/001 - Expedition zum roten Planeten.txt',
                'serie' => 'missionmars',
                'roman_nr' => 1,
                'titel' => 'Expedition zum roten Planeten',
                'zyklus' => null,
                'status' => 'indexiert',
                'indexiert_am' => now(),
            ],
            // Hardcover Roman
            [
                'dateiname' => '001 - Die Schwarze Zukunft.txt',
                'dateipfad' => 'romane/hardcovers/001 - Die Schwarze Zukunft.txt',
                'serie' => 'hardcovers',
                'roman_nr' => 1,
                'titel' => 'Die Schwarze Zukunft',
                'zyklus' => null,
                'status' => 'hochgeladen',
            ],
            // Roman mit Fehler
            [
                'dateiname' => '999 - Fehlerhafter Roman.txt',
                'dateipfad' => 'romane/maddrax/999 - Fehlerhafter Roman.txt',
                'serie' => 'maddrax',
                'roman_nr' => 999,
                'titel' => 'Fehlerhafter Roman',
                'zyklus' => null,
                'status' => 'fehler',
                'fehler_nachricht' => 'Datei konnte nicht gelesen werden',
            ],
        ];

        foreach ($testRomane as $romanData) {
            // Erstelle Dummy-Dateien für die Tests auf dem private Disk
            $content = "Dies ist ein Testinhalt für den Roman: {$romanData['titel']}\n\n";
            $content .= "Lorem ipsum dolor sit amet, consectetur adipiscing elit.\n";
            $content .= "Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.\n";
            Storage::disk('private')->put($romanData['dateipfad'], $content);

            KompendiumRoman::firstOrCreate(
                ['dateipfad' => $romanData['dateipfad']],
                array_merge($romanData, [
                    'hochgeladen_am' => now(),
                    'hochgeladen_von' => $admin->id,
                ])
            );
        }
    }
}
