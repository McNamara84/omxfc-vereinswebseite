<?php

namespace App\Services;

use App\Support\RpgInventoryCatalog;
use InvalidArgumentException;

/** Pure rules for new equipment, rulebook pp. 39–44 and 49. */
final class RpgItemCreationEvaluator
{
    public const VERSION = 'maddrax-item-creation-2026-11-08';

    public const COMMON = ['name', 'description', 'category', 'size', 'rarity', 'beautiful', 'material', 'high_tech', 'tech_factor', 'utility', 'bucks', 'reference', 'minimum_education'];

    public static function fields(): array
    {
        $ranged = ['weapon_type', 'precision', 'fire_rate', 'range_increment', 'max_range', 'magazine', 'ammunition_id', 'ammunition_name', 'ammunition_unit'];

        return [
            'melee_weapons' => array_merge(['masterwork', 'attributes', 'hands', 'throwable'], $ranged),
            'ranged_weapons' => array_merge(['hands'], $ranged),
            'armor' => ['effectiveness', 'movement_modifier'],
            'shields' => [], 'clothing' => [], 'low_tech' => [], 'high_tech' => [],
            'transport' => ['persons_min', 'persons_max', 'protection', 'land_speed', 'water_speed', 'air_speed', 'hover'],
        ];
    }

    public function evaluate(array $input): array
    {
        $category = $this->choice($input, 'category', array_keys(self::fields()));
        $this->ensure(array_diff(array_keys($input), array_merge(self::COMMON, self::fields()[$category])) === [], 'Unzulässige Felder für diese Kategorie.');
        $name = $this->text($input, 'name', 120);
        $description = $this->text($input, 'description', 4000);
        $reference = $this->text($input, 'reference', 2000);
        $size = $this->choice($input, 'size', ['small', 'medium', 'large', 'huge']);
        $rarity = $this->choice($input, 'rarity', ['common', 'rare', 'very_rare']);
        $high = $this->boolean($input, 'high_tech');
        $this->ensure($category !== 'high_tech' || $high, 'High-Tech-Gegenstände benötigen die High-Tech-Eigenschaft.');
        $this->ensure($category !== 'low_tech' || ! $high, 'Low-Tech-Gegenstände dürfen nicht als High-Tech bezeichnet werden.');
        $material = $this->integer($input, 'material', 0, 6, 0);
        $this->ensure($material !== 1, 'Wertvolles Material: +2 bis +6, sonst 0.');
        $tech = $this->integer($input, 'tech_factor', $high ? 5 : 1, $high ? 1000 : 1, $high ? 5 : 1);
        $utility = $this->integer($input, 'utility', 1, 10, 1);
        $bucks = $this->integer($input, 'bucks', 0, RpgInventoryCatalog::MONEY_MAX);
        $education = $this->integer($input, 'minimum_education', 0, 100, 0);
        $value = 1;
        $steps = [['label' => 'Grundtauschwert', 'value' => 1]];
        $add = function (string $label, int $amount) use (&$value, &$steps): void {
            $value += $amount;
            $steps[] = ['label' => $label, 'value' => $value];
        };
        $multiply = function (string $label, int $factor) use (&$value, &$steps): void {
            $value *= $factor;
            $steps[] = ['label' => $label, 'value' => $value];
        };
        $add('Größe', ['small' => 0, 'medium' => 1, 'large' => 3, 'huge' => 6][$size]);
        $add('Häufigkeit', ['common' => 0, 'rare' => 3, 'very_rare' => 6][$rarity]);
        $add('Gestaltung', $this->boolean($input, 'beautiful') ? 2 : 0);
        $add('Material', $material);
        $definition = ['name' => $name, 'description' => $description, 'category' => $category, 'size' => $size,
            'requiresHighTechAdvantage' => $high, 'countsTowardLimit' => true, 'bucks' => (string) $bucks,
            'minimumEducation' => $education, 'summary' => $description, 'reference' => $reference];
        $modes = [];
        $damage = 0;
        $masterwork = $category === 'melee_weapons' && $this->boolean($input, 'masterwork');
        if ($category === 'melee_weapons') {
            $this->ensure($size !== 'huge', 'Nahkampfwaffen: klein, mittel oder groß.');
            $attributes = $input['attributes'] ?? ['st'];
            $this->ensure(is_array($attributes) && array_all($attributes, fn ($attribute) => is_string($attribute)) && $attributes !== [] && count($attributes) <= 2 && array_diff($attributes, ['st', 'ge']) === [] && count(array_unique($attributes)) === count($attributes), 'Angriffsattribut: ST und/oder GE.');
            $damage = ['small' => 0, 'medium' => 1, 'large' => 2][$size] + ($masterwork ? 1 : 0);
            $modes[] = ['kind' => 'melee', 'skill' => 'Nahkampf', 'attributes' => $attributes, 'damageAttribute' => 'st', 'precision' => 0, 'damage' => $damage];
        }
        $ranged = $category === 'ranged_weapons' || ($category === 'melee_weapons' && $this->boolean($input, 'throwable'));
        if ($ranged) {
            $type = $this->choice($input, 'weapon_type', ['Wurfgeschoss', 'Projektilwaffe', 'Schießpulverwaffe', 'Energiewaffe']);
            $this->ensure($category !== 'melee_weapons' || $type === 'Wurfgeschoss', 'Zusätzlicher Fernmodus einer Nahkampfwaffe muss ein Wurfgeschoss sein.');
            $precision = $this->integer($input, 'precision', -2, 2);
            $magazine = $this->integer($input, 'magazine', 0, 1000000, 0);
            $increment = $this->integer($input, 'range_increment', 1, 1000000);
            $maximum = $this->integer($input, 'max_range', $increment, 1000000);
            $rate = $this->choice($input, 'fire_rate', ['E', 'H', 'S', 'A']);
            $this->ensure($type !== 'Wurfgeschoss' || ($magazine === 0 && $rate === 'E'), 'Wurfwaffen haben kein Magazin und verwenden Einzelschuss.');
            $rangedDamage = ['Wurfgeschoss' => 0, 'Projektilwaffe' => 1, 'Schießpulverwaffe' => 2, 'Energiewaffe' => 3][$type];
            $required = match ($type) {
                'Schießpulverwaffe' => 2, 'Energiewaffe' => 3, default => $education
            };
            $this->ensure(! isset($input['minimum_education']) || $education === $required, 'Mindestbildung passt nicht zum Waffentyp (S. 40).');
            $definition['minimumEducation'] = $required;
            $modes[] = ['kind' => 'ranged', 'skill' => $rangedDamage >= 2 ? 'Feuerwaffen' : 'Fernkampf', 'attributes' => ['wa'], 'damageAttribute' => 'wa',
                'precision' => $precision, 'damage' => $rangedDamage, 'type' => $type, 'fireRate' => $rate,
                'rangeIncrement' => $increment.'m', 'maxRange' => $maximum.'m', 'magazine' => $magazine ?: null];
            $add('Fernkampf: Präzision und Magazin', 3 + $precision + intdiv($magazine, 5));
            if ($type !== 'Wurfgeschoss') {
                $this->ensure(is_string($input['ammunition_id'] ?? ''), 'Ungültige Munitionskennung.');
                $ammoId = trim($input['ammunition_id'] ?? '');
                $this->ensure(strlen($ammoId) <= 100, 'Ungültige Munitionskennung.');
                $definition['ammunitionProfile'] = ['id' => $ammoId, 'name' => $ammoId === '' ? $this->text($input, 'ammunition_name', 120) : '',
                    'unit' => $ammoId === '' ? $this->text($input, 'ammunition_unit', 40) : '', 'starting' => $type === 'Projektilwaffe' ? 30 : 4 * $magazine];
                $this->ensure($type === 'Projektilwaffe' || $magazine > 0, 'Schusswaffen benötigen eine positive Magazingröße.');
            }
            if ($category === 'ranged_weapons') {
                $damage = $rangedDamage;
            }
        } elseif ($category === 'melee_weapons') {
            $this->ensure(array_intersect(array_keys($input), array_diff(self::fields()['ranged_weapons'], ['hands'])) === [], 'Fernkampfwerte sind nur mit Wurfmodus erlaubt.');
        }
        if ($modes !== []) {
            $definition['hands'] = $this->integer($input, 'hands', 1, 2);
            $definition['combat'] = ['kind' => 'weapon', 'modes' => $modes];
            if ($masterwork) {
                $multiply('Meisterarbeit ×2', 2);
            }
            $multiply('Waffenschaden +1', $damage + 1);
            $definition['summary'] = implode(' · ', array_map(fn ($m) => $m['skill'].' S +'.$m['damage'].' P '.$m['precision']
                .($m['kind'] === 'ranged' ? ' FR '.$m['fireRate'].' RI '.$m['rangeIncrement'].' MR '.$m['maxRange'].' M '.($m['magazine'] ?? '–') : ''), $modes))
                .' · '.$definition['hands'].'H · '.$description;
        }
        if ($category === 'armor') {
            $protection = $this->integer($input, 'effectiveness', 0, 2) + ($high ? 1 : 0);
            $movement = $this->integer($input, 'movement_modifier', -100, 0);
            $definition['combat'] = ['kind' => 'armor', 'protection' => $protection, 'movementModifier' => $movement];
            $multiply('Rüstung: (SF +1) ×3', ($protection + 1) * 3);
            $add('Behinderung', -2 * abs($movement));
            $definition['summary'] = 'SF '.$protection.' · BM '.$movement.' · '.$description;
        }
        if ($category === 'shields') {
            $definition['combat'] = ['kind' => 'shield', 'defenseBonus' => 1];
        }
        if ($category === 'transport') {
            $min = $this->integer($input, 'persons_min', 1, 1000000);
            $definition['transport'] = ['persons_min' => $min, 'persons_max' => $this->integer($input, 'persons_max', $min, 1000000),
                'protection' => $this->integer($input, 'protection', 0, 100), 'land_speed' => $this->integer($input, 'land_speed', 0, 1000000, 0),
                'water_speed' => $this->integer($input, 'water_speed', 0, 1000000, 0), 'air_speed' => $this->integer($input, 'air_speed', 0, 1000000, 0), 'hover' => $this->boolean($input, 'hover')];
            $t = $definition['transport'];
            $definition['summary'] = "P {$min}–{$t['persons_max']} · SW {$t['protection']} · G {$t['land_speed']}/{$t['water_speed']}/{$t['air_speed']} km/h · ".$description;
        }
        if ($high) {
            $multiply('High-Tech', $tech);
        }
        $multiply('Nützlichkeit', $utility);
        $this->ensure($value > 0 && $value <= RpgInventoryCatalog::MONEY_MAX, 'Der Tauschwert muss positiv sein und im technischen Zahlenbereich liegen.');
        $definition['tw'] = (string) $value;
        $definition['summary'] .= ' · MB '.$definition['minimumEducation'];

        return ['definition' => $definition, 'calculation' => $steps, 'rule_version' => self::VERSION];
    }

    private function text(array $input, string $key, int $max): string
    {
        $this->ensure(is_string($input[$key] ?? null), 'Bitte '.$key.' als Text angeben.');
        $value = trim($input[$key]);
        $this->ensure($value !== '' && mb_strlen($value) <= $max, 'Ungültige Textlänge: '.$key);

        return $value;
    }

    private function integer(array $input, string $key, int $min, int $max, ?int $default = null): int
    {
        $value = $input[$key] ?? $default;
        $this->ensure(is_int($value) || (is_string($value) && preg_match('/\A-?[0-9]+\z/D', $value) === 1), 'Bitte eine ganze Zahl für '.$key.' angeben.');
        $this->ensure($value >= $min && $value <= $max, $key.': erlaubter Bereich '.$min.' bis '.$max.'.');

        return (int) $value;
    }

    private function choice(array $input, string $key, array $choices): string
    {
        $this->ensure(is_string($input[$key] ?? null) && in_array($input[$key], $choices, true), 'Ungültige Auswahl: '.$key);

        return $input[$key];
    }

    private function boolean(array $input, string $key): bool
    {
        $value = $input[$key] ?? false;
        $this->ensure(in_array($value, [true, false, 0, 1, '0', '1'], true), 'Ungültiger Wahrheitswert: '.$key);

        return (bool) $value;
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new InvalidArgumentException($message);
        }
    }
}
