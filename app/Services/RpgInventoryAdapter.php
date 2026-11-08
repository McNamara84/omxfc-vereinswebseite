<?php

namespace App\Services;

use App\Support\RpgEquipmentCatalog;
use App\Support\RpgInventoryCatalog;
use Illuminate\Validation\ValidationException;

final class RpgInventoryAdapter
{
    public function __construct(private RpgEquipmentCatalog $catalog = new RpgEquipmentCatalog) {}

    public function useCatalog(RpgEquipmentCatalog $catalog): void
    {
        $this->catalog = $catalog;
    }

    public function state(array $payload): array
    {
        $equipment = $payload['equipment'] ?? [];
        $stocks = [];
        foreach ($equipment['items'] ?? [] as $item) {
            if (($item['id'] ?? '') !== 'faustschlag-tritt') {
                $key = 'item:'.$item['id'];
                $stocks[$key] = ($stocks[$key] ?? 0) + (int) ($item['quantity'] ?? 1);
            }
        }
        $clothing = $equipment['clothing']['id'] ?? '';
        foreach ($equipment['clothing_stock'] ?? ($clothing !== '' ? [$clothing => 1] : []) as $id => $quantity) {
            $stocks['clothing:'.$id] = (int) $quantity;
        }
        if (($equipment['schema_version'] ?? 0) === 1) {
            foreach ($equipment['ammunition'] ?? [] as $row) {
                $stocks[$row['id']] = (int) $row['quantity'];
            }
        } else {
            $legacy = $equipment['ammunition'] ?? [];
            if ($legacy === []) {
                $stocks += $this->catalog->startingAmmunition($equipment['items'] ?? []);
            } else {
                foreach ($legacy as $row) {
                    $weapon = collect($this->catalog->items())->firstWhere('name', $row['source'] ?? '')['id'] ?? null;
                    $key = $weapon ? $this->catalog->ammunitionId($weapon) : null;
                    if ($key === null) {
                        $stocks['unknown:'.($row['source'] ?? 'Munition')] = (int) ($row['quantity'] ?? 0);

                        continue;
                    }
                    $quantity = (int) $row['quantity'];
                    if ($weapon === 'energiegewehr') {
                        $quantity = 100 * (int) (collect($equipment['items'] ?? [])->firstWhere('id', $weapon)['quantity'] ?? 1);
                    } elseif (($row['unit'] ?? '') === 'Magazine') {
                        $quantity *= $this->catalog->capacity($weapon);
                    }
                    $stocks[$key] = ($stocks[$key] ?? 0) + $quantity;
                }
            }
        }
        $stocks = array_filter($stocks, fn ($quantity) => $quantity !== 0);
        ksort($stocks);

        return ['stocks' => $stocks, 'bucks' => $equipment['bucks_balance'] ?? null,
            'confirmed' => ! empty($equipment['stock_confirmed_at']), 'clothing' => $clothing,
            'armor' => $equipment['active_armor_id'] ?? '', 'shield' => $equipment['active_shield_id'] ?? '',
            'notes' => $equipment['notes'] ?? ($payload['character']['equipment'] ?? '')];
    }

    public function equipment(array $state, array $existing = []): array
    {
        $catalog = $this->catalog->positions();
        $items = $ammo = $clothes = [];
        foreach ($state['stocks'] as $id => $quantity) {
            if ($quantity === 0) {
                continue;
            }
            $row = $catalog[$id] ?? null;
            self::ensure($row !== null, 'Unbekannten Altbestand zuerst mit Sollmenge 0 korrigieren: '.$id);
            self::ensure(is_int($quantity) && $quantity > 0 && $quantity <= $row['limit'], 'Ungültiger Bestand: '.$row['name']);
            if ($row['kind'] === 'item') {
                $item = $this->catalog->itemMap()[$row['id']];
                $items[] = $item + ['quantity' => $quantity];
            } elseif ($row['kind'] === 'clothing') {
                $clothes[$row['id']] = $quantity;
            } else {
                $ammo[] = ['id' => $id, 'source' => $row['name'], 'unit' => $row['unit'], 'quantity' => $quantity];
            }
        }
        // This selected attack is virtual: keep its PDF data without turning it
        // into stock that can be granted, lost or traded.
        foreach ($existing['items'] ?? [] as $item) {
            if (($item['id'] ?? '') === 'faustschlag-tritt') {
                $items[] = $item;
            }
        }
        self::ensure(count($items) <= 64, 'Höchstens 64 Gegenstandsarten sind technisch unterstützt.');
        self::ensure(is_int($state['bucks']) && $state['bucks'] >= 0 && $state['bucks'] <= RpgInventoryCatalog::MONEY_MAX, 'Ungültiger Bucks-Bestand.');
        self::ensure(($state['stocks']['ammo:energiegewehr'] ?? 0) <= 100 * ($state['stocks']['item:energiegewehr'] ?? 0), 'Das Energiegewehr fasst maximal 100 Schuss je Exemplar.');
        foreach (['armor', 'shield'] as $kind) {
            $id = $state[$kind];
            self::ensure($id === '' || (($state['stocks']['item:'.$id] ?? 0) > 0
                && ($this->catalog->combatProfiles()[$id]['kind'] ?? '') === $kind), 'Aktiver Schutz muss im Bestand vorhanden sein.');
        }
        self::ensure($state['clothing'] === '' || isset($clothes[$state['clothing']]), 'Aktive Kleidung muss im Bestand vorhanden sein.');

        return array_replace($existing, ['schema_version' => 1, 'items' => $items, 'clothing_stock' => $clothes,
            'clothing' => $this->catalog->clothingMap()[$state['clothing']] ?? null,
            'ammunition' => $ammo, 'bucks_balance' => $state['bucks'], 'notes' => $state['notes'],
            'active_armor_id' => $state['armor'] ?: null, 'active_shield_id' => $state['shield'] ?: null]);
    }

    public function initializeNew(array $payload): array
    {
        $state = $this->state($payload);
        $state['bucks'] = 0;
        $state['stocks'] = array_filter($state['stocks'], fn ($quantity, $key) => ! str_starts_with($key, 'ammo:'), ARRAY_FILTER_USE_BOTH)
            + $this->catalog->startingAmmunition($payload['equipment']['items'] ?? []);
        $payload['equipment'] = $this->equipment($state, $payload['equipment'] ?? []);
        $payload['equipment']['stock_confirmed_at'] = now()->toIso8601String();

        return $payload;
    }

    public static function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['inventory' => $message]);
        }
    }
}
