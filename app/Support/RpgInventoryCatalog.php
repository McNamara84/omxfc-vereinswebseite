<?php

namespace App\Support;

final class RpgInventoryCatalog
{
    public const VERSION = 'inventory-2026-11-07';

    public const MONEY_MAX = 1000000000;

    public static function positions(): array
    {
        $rows = [];
        foreach (RpgCharEditorEquipment::items() as $item) {
            if ($item['id'] === 'faustschlag-tritt') {
                continue;
            }
            $rows['item:'.$item['id']] = $item + ['kind' => 'item', 'limit' => 20,
                'price' => self::number($item['bucks'] ?? ''), 'trade_value' => self::number($item['tw'] ?? '')];
        }
        foreach (RpgCharEditorEquipment::clothing() as $item) {
            $rows['clothing:'.$item['id']] = $item + ['kind' => 'clothing', 'limit' => 20,
                'price' => self::number($item['bucks']), 'trade_value' => self::number($item['tw'])];
        }
        foreach (self::ammunition() as $id => $item) {
            $rows[$id] = $item + ['id' => $id, 'kind' => 'ammo', 'limit' => 1000000, 'price' => null, 'trade_value' => null];
        }

        return $rows;
    }

    public static function ammunition(): array
    {
        $rows = [
            'ammo:pfeile' => ['name' => 'Pfeile', 'unit' => 'Pfeile'],
            'ammo:bolzen' => ['name' => 'Bolzen', 'unit' => 'Bolzen'],
            'ammo:steine' => ['name' => 'Steine', 'unit' => 'Steine'],
        ];
        foreach (['revolver', 'automatikpistole', 'gewehr-bolzer', 'maschinenpistole', 'automatikgewehr', 'driller', 'energiegewehr', 'kanone'] as $id) {
            $item = RpgCharEditorEquipment::itemMap()[$id];
            $rows['ammo:'.$id] = ['name' => $item['name'].' – '.($id === 'energiegewehr' ? 'Ladung' : 'Munition'), 'unit' => 'Schuss'];
        }

        return $rows;
    }

    public static function ammunitionId(string $weapon): ?string
    {
        return match ($weapon) {
            'bogen' => 'ammo:pfeile', 'armbrust' => 'ammo:bolzen',
            'geworfener-stein', 'schleuder', 'zwille' => 'ammo:steine',
            default => isset(self::ammunition()['ammo:'.$weapon]) ? 'ammo:'.$weapon : null,
        };
    }

    public static function capacity(string $weapon): int
    {
        $modes = RpgCharEditorEquipment::combatProfiles()[$weapon]['modes'] ?? [];
        foreach ($modes as $mode) {
            if ($mode['kind'] === 'ranged') {
                return $weapon === 'energiegewehr' ? 100 : (int) ($mode['magazine'] ?? 0);
            }
        }

        return 0;
    }

    public static function startingAmmunition(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $weapon = $item['id'];
            $key = self::ammunitionId($weapon);
            if ($key === null) {
                continue;
            }
            $amount = match ($key) {
                'ammo:pfeile', 'ammo:bolzen', 'ammo:steine' => 30,
                'ammo:energiegewehr' => 100,
                default => 4 * self::capacity($weapon),
            };
            $result[$key] = ($result[$key] ?? 0) + $amount * $item['quantity'];
        }

        return $result;
    }

    private static function number(string $text): ?int
    {
        // Only the fixed catalogue's exact numeric notation is accepted. Ranges
        // and missing prices require an explicit ruling by the game master.
        if (! preg_match('/^(\d+)(?:,(\d))?(K)?$/D', $text, $match)) {
            return null;
        }

        return (int) $match[1] * (isset($match[3]) ? 1000 : 1)
            + (isset($match[2]) && $match[2] !== '' ? (int) $match[2] * 100 : 0);
    }
}
