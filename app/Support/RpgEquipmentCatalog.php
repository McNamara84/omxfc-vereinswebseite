<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/** Immutable, fully resolved catalogue; no database or request dependencies. */
final class RpgEquipmentCatalog
{
    public function __construct(private array $custom = [], private array $ammo = [], private string $revision = '') {}

    public function version(): string
    {
        // Full SHA-256 in URL-safe base64 fits the existing 60-character journal column.
        return $this->revision === '' ? RpgInventoryCatalog::VERSION
            : 'items-'.rtrim(strtr(base64_encode(hash('sha256', $this->revision, true)), '+/', '-_'), '=');
    }

    public function itemMap(string $context = 'owned'): array
    {
        return RpgCharEditorEquipment::itemMap() + array_filter($this->custom, fn ($item) => $item['category'] !== 'clothing' && $this->allowed($item, $context));
    }

    public function clothingMap(string $context = 'owned'): array
    {
        return RpgCharEditorEquipment::clothingMap() + array_filter($this->custom, fn ($item) => $item['category'] === 'clothing' && $this->allowed($item, $context));
    }

    public function items(string $context = 'owned'): array
    {
        return array_values($this->itemMap($context));
    }

    public function clothing(string $context = 'owned'): array
    {
        return array_values($this->clothingMap($context));
    }

    private function allowed(array $item, string $context): bool
    {
        return $context === 'owned' || ($item['available'] && ($context !== 'creation' || $item['starting_equipment']));
    }

    public function combatProfiles(): array
    {
        return RpgCharEditorEquipment::combatProfiles() + array_map(fn ($item) => $item['combat'], array_filter($this->custom, fn ($item) => isset($item['combat'])));
    }

    public function ruleConfig(): array
    {
        return array_replace(RpgCharEditorEquipment::ruleConfig(), ['items' => $this->items('creation'), 'clothing' => $this->clothing('creation')]);
    }

    public function positions(): array
    {
        $positions = RpgInventoryCatalog::positions();
        foreach ($this->custom as $id => $item) {
            $kind = $item['category'] === 'clothing' ? 'clothing' : 'item';
            $positions[$kind.':'.$id] = $item + ['kind' => $kind, 'limit' => 20, 'price' => (int) $item['bucks'], 'trade_value' => (int) $item['tw']];
        }
        foreach ($this->ammo as $id => $profile) {
            $positions[$id] = $profile + ['id' => $id, 'kind' => 'ammo', 'limit' => 1000000, 'price' => null, 'trade_value' => null];
        }

        return $positions;
    }

    public function ammunition(): array
    {
        return RpgInventoryCatalog::ammunition() + $this->ammo;
    }

    public function ammunitionId(string $weapon): ?string
    {
        return $this->custom[$weapon]['ammunitionProfile']['id'] ?? RpgInventoryCatalog::ammunitionId($weapon);
    }

    public function capacity(string $weapon): int
    {
        if (! isset($this->custom[$weapon])) {
            return RpgInventoryCatalog::capacity($weapon);
        }
        foreach ($this->custom[$weapon]['combat']['modes'] ?? [] as $mode) {
            if ($mode['kind'] === 'ranged') {
                return (int) ($mode['magazine'] ?? 0);
            }
        }

        return 0;
    }

    public function startingAmmunition(array $items): array
    {
        $result = RpgInventoryCatalog::startingAmmunition(array_values(array_filter($items, fn ($item) => ! str_starts_with($item['id'], 'custom-'))));
        foreach ($items as $item) {
            $profile = $this->custom[$item['id']]['ammunitionProfile'] ?? null;
            if ($profile) {
                $result[$profile['id']] = ($result[$profile['id']] ?? 0) + $profile['starting'] * $item['quantity'];
            }
        }

        return $result;
    }

    public function assertAvailable(string $position): void
    {
        $row = $this->positions()[$position] ?? null;
        if (! $row || (isset($row['available']) && ! $row['available'])) {
            throw ValidationException::withMessages(['inventory' => 'Diese Gegenstandsversion ist nicht für eine neue Vergabe verfügbar.']);
        }
    }
}
