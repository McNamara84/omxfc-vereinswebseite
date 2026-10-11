<?php

namespace App\Support;

/** The published blocks on pages 57–61. K values already include their modifiers. */
final class RpgNpcCatalog
{
    public const VERSION = 'maddrax-2007-npcs-v1';

    public const SKILLS = ['Athletik', 'Beruf', 'Bildung', 'Diebeskunst', 'Fahren', 'Fernkampf', 'Feuerwaffen', 'Handeln', 'Heiler', 'Heimlichkeit', 'Intuition', 'Kunde', 'Nahkampf', 'Pilot', 'Reiten', 'Sprachen', 'Techniker', 'Unterhalten', 'Überleben', 'Wissenschaftler'];

    public static function ranks(): array
    {
        return [
            'Leq' => ['st' => 1, 'ge' => 1, 'ro' => 2, 'wi' => 0, 'in' => 0, 'fp' => 0],
            'Lin' => ['st' => 2, 'ge' => 1, 'ro' => 2, 'wi' => 0, 'in' => 0, 'fp' => 3],
            'Hal' => ['st' => 2, 'ge' => 1, 'ro' => 3, 'wi' => 0, 'in' => 0, 'fp' => 6],
            'Lan' => ['st' => 2, 'ge' => 1, 'ro' => 3, 'wi' => 0, 'in' => 1, 'fp' => 9],
            'Lun' => ['st' => 3, 'ge' => 1, 'ro' => 4, 'wi' => 1, 'in' => 1, 'fp' => 12],
            'Sil' => ['st' => 3, 'ge' => 2, 'ro' => 4, 'wi' => 1, 'in' => 1, 'fp' => 15],
            'Sol' => ['st' => 4, 'ge' => 3, 'ro' => 5, 'wi' => 2, 'in' => 2, 'fp' => 18],
        ];
    }

    public static function all(): array
    {
        $rows = [
            'androne' => self::creature('Androne', 57, ['st' => 3, 'ro' => 3, 'wi' => 1, 'in' => -3], ['Athletik' => 3, 'Nahkampf' => 2], 3, [['Nahkampf', 2, 2]]),
            'avtar' => self::creature('Avtar', 57, ['st' => 5, 'ge' => 1, 'ro' => 5, 'in' => -2, 'wa' => 3], ['Athletik' => 4, 'Nahkampf' => 3], 5, [['Klauen', 4, 4, 2], ['Schnabel', 4, 5]], ['Schrei', 'Flug']),
            'daamure' => self::creature('Daa’mure', 57, ['st' => 1, 'ro' => 2, 'ge' => 1], ['Athletik' => 3, 'Bildung' => 3, 'Heimlichkeit' => 2, 'Nahkampf' => 3], 4, [['Nahkampf', 4, 2]], ['Gestaltwandel']),
            'eluu' => self::creature('Eluu', 58, ['st' => 3, 'ro' => 5, 'in' => -2, 'wa' => 4], ['Athletik' => 3, 'Nahkampf' => 2], 3, [['Klauen', 2, 2, 2], ['Schnabel', 2, 3]], ['Flug']),
            'frekkeuscher' => self::creature('Frekkeuscher', 58, ['st' => 2, 'ge' => 1, 'ro' => 2, 'in' => -4, 'wa' => 1], ['Athletik' => 2, 'Nahkampf' => 1], 3, [['Nahkampf', 2, 1]], ['Sprung (20–30 m)', 'Geradliniger Flug']),
            'gejagudoo' => self::creature('Gejagudoo', 58, ['st' => 3, 'ro' => 5, 'in' => -4, 'wa' => 1], ['Athletik' => 2, 'Nahkampf' => 2, 'Heimlichkeit' => 2], 2, [['Biss', 2, 3]], ['Tunnelbewegung', 'Verschlingen und Magensäfte (+1S)']),
            'izekeepir' => self::creature('Izekeepir', 59, ['st' => 4, 'ro' => 6, 'in' => -2, 'wa' => 1], ['Athletik' => 4, 'Nahkampf' => 4, 'Heimlichkeit' => 1], 4, [['Klauen', 4, 3, 2], ['Biss', 4, 4]]),
            'leshiye' => self::creature('Lesh’iye (Todesrochen)', 59, ['st' => 3, 'ge' => 2, 'ro' => 3, 'in' => -1, 'wa' => 1], ['Athletik' => 4, 'Nahkampf' => 4, 'Heimlichkeit' => 2], 6, [['Nahkampf', 6, 2]], ['Flug', 'Kiemenatmung', 'Psionische Orientierung']),
            'lupa' => self::creature('Lupa', 59, ['st' => 1, 'ge' => 1, 'in' => -2, 'wa' => 2], ['Athletik' => 3, 'Heimlichkeit' => 3, 'Nahkampf' => 3, 'Überleben' => 3], 4, [['Biss', 4, 0]]),
            'siragippe' => self::creature('Siragippe (Riesenspinne)', 60, ['st' => 1, 'ge' => 1, 'ro' => 3, 'in' => -3], ['Athletik' => 3, 'Nahkampf' => 2, 'Heimlichkeit' => 4, 'Intuition' => 3], 4, [['Biss', 3, 0]], ['Säuresekret', 'Fühlerorientierung']),
            'snaekke' => self::creature('Snäkke', 60, ['st' => 4, 'ge' => -1, 'ro' => 6, 'in' => -4], ['Athletik' => 1, 'Nahkampf' => 2, 'Fernkampf' => 2], 0, [['Biss', 1, 3]], ['Besonderer Schutz gegen gewöhnliche Waffen', 'Verschlingen und Magensäure (+2S/Runde)']),
            'wisaau' => self::creature('Wisaau', 60, ['st' => 1, 'in' => -3, 'wa' => -1], ['Athletik' => 3, 'Heimlichkeit' => 1, 'Nahkampf' => 3], 3, [['Hauer', 3, 0, 1, 600], ['Biss', 3, -1]]),
            'bandit' => self::person('Bandit', [], ['Athletik' => 2, 'Nahkampf' => 2, 'Fernkampf' => 2], [], 2, 2, [], [], ['Taratzenfutter']),
            'barbarenkrieger' => self::person('Barbarenkrieger', ['st' => 1], ['Athletik' => 2, 'Nahkampf' => 3, 'Heimlichkeit' => 1], ['axt', 'verstaerktes-leder', 'holzschild'], 4, 3, ['axt' => [4, 2]], [], ['Taratzenfutter']),
            'bluttempler' => self::person('Bluttempler', ['ge' => 1, 'wa' => 1], ['Athletik' => 4, 'Nahkampf' => 4, 'Heimlichkeit' => 4], ['degen'], 5, 5, ['degen' => [5, 1]], [], ['Taratzenfutter']),
            'disuuslachter' => self::person('Disuuslachter', ['st' => 1, 'ro' => 1, 'in' => -1], ['Athletik' => 3, 'Nahkampf' => 3], ['verstaerktes-leder', 'holzschild', 'axt'], 4, 4, ['axt' => [4, 2]], [], ['Gejagt', 'Taratzenfutter']),
            'guul' => self::person('Guul', ['au' => -1], ['Athletik' => 3, 'Heimlichkeit' => 3, 'Nahkampf' => 3, 'Intuition' => 2], ['keule'], 3, 3, ['keule' => [3, 1], 'natural' => [3, 1]], ['Natürliche Waffen'], ['Primitiv', 'Gejagt', 'Taratzenfutter']),
            'taratze' => self::person('Taratze', ['st' => 1, 'wa' => 1, 'in' => -1, 'au' => -1], ['Athletik' => 2, 'Intuition' => 2, 'Heimlichkeit' => 2, 'Nahkampf' => 3, 'Überleben' => 2], [], null, 2, [], [], ['Auffällig', 'Primitiv', 'Gejagt', 'Taratzenfutter']),
            'technoforscher' => self::person('Technoforscher', ['st' => -1, 'ro' => -1, 'in' => 1], ['Athletik' => 2, 'Bildung' => 4, 'Feuerwaffen' => 1, 'Wissenschaftler' => 4], ['schutzanzug', 'energiegewehr'], null, 2, ['energiegewehr' => [2, 3]], [], ['Taratzenfutter', 'Tödliche Immunschwäche']),
            'technosoldat' => self::person('Technosoldat', [], ['Athletik' => 3, 'Bildung' => 3, 'Heimlichkeit' => 2, 'Feuerwaffen' => 3, 'Nahkampf' => 2], ['schutzanzug', 'energiegewehr'], null, 3, ['energiegewehr' => [3, 3]], [], ['Taratzenfutter', 'Tödliche Immunschwäche']),
            'aruula' => self::person('Aruula', ['st' => 1, 'ge' => 1, 'ro' => 1, 'wa' => 1, 'au' => 1], ['Athletik' => 5, 'Heimlichkeit' => 5, 'Intuition' => 4, 'Nahkampf' => 5, 'Fernkampf' => 2, 'Reiten' => 4, 'Sprachen' => 2, 'Überleben' => 4, 'Lauschen' => 2], ['zweihandschwert'], 7, 9, ['zweihandschwert' => [6, 3]], ['Kaltblütig', 'Kampfreflexe', 'Lauschen', 'Zäh'], ['Abergläubisch'], true),
            'maddrax' => self::person('Matthew Drax', ['ge' => 1, 'ro' => 1, 'wi' => 1], ['Athletik' => 4, 'Heimlichkeit' => 3, 'Bildung' => 3, 'Nahkampf' => 4, 'Feuerwaffen' => 4, 'Fahren' => 4, 'Reiten' => 2, 'Pilot' => 5, 'Sprachen' => 2, 'Überleben' => 2, 'Techniker' => 2, 'Beruf: Pilot' => 4], ['driller', 'schwert'], 5, 7, ['schwert' => [5, 1], 'driller' => [5, 3]], ['Kampfreflexe', 'Zäh'], ['Feinde'], true),
            'jacob-smythe' => self::person('Jacob Smythe', ['ge' => 1, 'ro' => 1, 'in' => 1, 'wi' => 2], ['Athletik' => 4, 'Heimlichkeit' => 2, 'Bildung' => 5, 'Nahkampf' => 3, 'Feuerwaffen' => 3, 'Reiten' => 2, 'Pilot' => 3, 'Fahren' => 3, 'Techniker' => 6, 'Wissenschaftler' => 6], ['schockstab-hydrit'], null, 5, ['schockstab-hydrit' => [4, 1]], ['Zäh'], [], true),
        ];
        $rows['daamure']['advantages'] = ['Gestaltwandler', 'Regeneration'];
        $rows['daamure']['advantage_counts'] = ['Regeneration' => 2];
        $rows['daamure']['disadvantages'] = ['Gejagt'];
        $rows['daamure']['body'][0]['attributes'] = ['st', 'ge'];
        $rows['snaekke']['disadvantages'] = ['Verwundbarkeit'];
        $rows['taratze']['body'] = [self::body('Krallen', 3, 0)];
        $rows['aruula']['notes'] = 'Lauschen (FW 2, S. 61) entspricht Telepathie (S. 24); 2 PEP gemäß S. 36. Keine erneute Spielererschaffung.';
        $rows['maddrax']['notes'] = 'Vorlage „Maddrax“; Feinde: Smythe und Daa’muren.';
        $rows['technoforscher']['notes'] = 'KO −1 der Vorlage wird als RO −1 geführt.';
        $rows['siragippe']['notes'] = '„Inuition“ der Vorlage wird als Intuition geführt.';
        foreach ($rows as $key => &$row) {
            $row += ['key' => $key, 'advantages' => [], 'advantage_counts' => [], 'disadvantages' => [], 'body' => [], 'abilities' => [], 'notes' => ''];
            $row['attributes'] += array_fill_keys(['st', 'ge', 'ro', 'wi', 'wa', 'in', 'au'], 0);
        }

        return $rows;
    }

    private static function creature(string $name, int $page, array $attributes, array $skills, int $dodge, array $attacks, array $abilities = []): array
    {
        return ['name' => $name, 'group' => 'Kreaturen', 'special' => false, 'page' => $page, 'attributes' => $attributes, 'skills' => $skills,
            'items' => [], 'parry' => null, 'dodge' => $dodge, 'combat' => [], 'abilities' => $abilities,
            'body' => array_map(fn ($a) => self::body(...$a), $attacks)];
    }

    private static function person(string $name, array $attributes, array $skills, array $items, ?int $parry, int $dodge, array $combat, array $advantages, array $disadvantages, bool $special = false): array
    {
        return compact('name', 'attributes', 'skills', 'items', 'parry', 'dodge', 'combat', 'advantages', 'disadvantages', 'special')
            + ['page' => 61, 'group' => $special ? 'Besondere Charaktere' : 'Allgemeine NSCs'];
    }

    private static function body(string $name, int $attack, int $damage, int $count = 1, int $runup = 0): array
    {
        return compact('name', 'attack', 'damage', 'count', 'runup') + ['attributes' => ['ge']];
    }
}
