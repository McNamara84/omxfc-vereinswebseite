<?php

namespace App\Services\RpgCombat;

use App\Models\RpgCharacter;
use App\Services\RpgCharacterProgressionAdapter;
use App\Services\RpgCharacterPsychicCalculator;
use App\Support\RpgCharEditorEquipment;
use App\Support\RpgCharEditorRuleCatalog;
use App\Support\RpgCharEditorSpecialRules;
use App\Support\RpgCombatRules;
use Illuminate\Validation\ValidationException;

final class CombatSnapshotFactory
{
    /** SL-10: explicit assignments, not guesses based on display names. */
    public const TWO_HANDED = ['harpoon-speer', 'lanze-hellebarde', 'kampfstab', 'zweihandschwert', 'streitaxt',
        'kettensaege', 'bogen', 'armbrust', 'gewehr-bolzer', 'maschinenpistole', 'automatikgewehr', 'energiegewehr', 'kanone'];

    public function make(RpgCharacter $character): array
    {
        $data = $character->payload;
        $this->ensure(is_array($data['attributes'] ?? null), 'Ungültiges oder fehlendes Attribut: ST');
        foreach (['st', 'ge', 'ro', 'wi', 'wa', 'in', 'au'] as $attribute) {
            $data['attributes'][$attribute] = $this->integerValue($data['attributes'][$attribute] ?? null,
                -20, 20, 'Ungültiges oder fehlendes Attribut: '.strtoupper($attribute));
        }
        $this->ensure(is_array($data['skills'] ?? null), 'Fertigkeiten fehlen.');
        foreach ($data['skills'] as $index => $skill) {
            $this->ensure(is_array($skill) && is_string($skill['name'] ?? null), 'Ungültige Fertigkeitswerte.');
            $data['skills'][$index]['value'] = $this->integerValue($skill['value'] ?? null,
                0, 100, 'Ungültige Fertigkeitswerte.');
        }
        foreach (['advantages', 'disadvantages', 'advantage_effects', 'advantage_counts', 'advantage_details', 'disadvantage_details'] as $key) {
            $this->ensure(is_array($data[$key] ?? []), 'Ungültige Eigenschaften.');
        }
        foreach ($data['advantage_effects'] ?? [] as $effect) {
            $this->ensure(is_array($effect) && is_string($effect['name'] ?? null), 'Ungültige Vorteilszuordnung.');
            $this->ensure(is_string($effect['target'] ?? ''), 'Ungültiges Vorteilsziel.');
        }
        foreach (['advantages', 'disadvantages'] as $key) {
            $this->ensure(array_all($data[$key] ?? [], fn ($value) => is_string($value)), 'Ungültiger Eigenschaftsname.');
        }
        foreach ($data['advantage_counts'] ?? [] as $count) {
            $this->ensure(is_int($count) && $count >= 1 && $count <= 20, 'Ungültige Vorteilsanzahl.');
        }
        $data = (new RpgCharacterProgressionAdapter)->normalize($data);
        foreach (['advantages' => RpgCharEditorSpecialRules::advantages(), 'disadvantages' => RpgCharEditorSpecialRules::disadvantages()] as $key => $catalog) {
            foreach ($data[$key] as $name) {
                $this->ensure(isset($catalog[$name]), 'Unbekannte Eigenschaft: '.$name);
            }
        }
        foreach ($data['advantage_counts'] as $count) {
            $this->ensure(is_int($count) && $count >= 1 && $count <= 20, 'Ungültige Anzahl eines Vorteils.');
        }
        $psychic = (new RpgCharacterPsychicCalculator)->calculate($data);
        foreach ($data['advantage_effects'] as $effect) {
            $this->ensure(in_array($effect['name'], $data['advantages'], true), 'Vorteilswirkung ohne zugehörigen Vorteil.');
            if ($effect['name'] === 'Psychische Kraft') {
                $this->ensure(in_array($effect['target'] ?? '', RpgCharEditorSpecialRules::PSYCHIC_POWER_TARGETS, true), 'Psychische Kraft benötigt eine eindeutige Talentzuordnung.');
            }
        }
        $sources = $data['rule_sources'] ?? [];
        if (! is_array($sources)) {
            $this->ensure(false, 'Ungültige Regelquellen.');
        }
        foreach ($sources as $source) {
            $this->ensure(is_array($source) && is_string($source['id'] ?? null) && isset(RpgCharEditorRuleCatalog::sources()[$source['id']]), 'Unbekannte Regelquelle.');
        }
        $race = $data['character']['race'] ?? '';
        $raceSource = is_string($race) ? (RpgCharEditorRuleCatalog::races()[$race]['source'] ?? RpgCharEditorRuleCatalog::BASE) : '';
        $this->ensure($raceSource === RpgCharEditorRuleCatalog::BASE || in_array($raceSource, array_column($sources, 'id'), true), 'Die Regelquelle dieser Rasse ist nicht freigeschaltet.');
        $equipment = $data['equipment'] ?? [];
        $this->ensure(is_array($equipment) && is_array($equipment['items'] ?? []), 'Ungültiges Inventar.');
        $catalog = RpgCharEditorEquipment::itemMap();
        $weapons = [];
        $items = [];
        foreach ($equipment['items'] ?? [] as $selection) {
            $this->ensure(is_array($selection) && is_string($selection['id'] ?? null) && isset($catalog[$selection['id']])
                && is_int($selection['quantity'] ?? null) && $selection['quantity'] >= 1 && $selection['quantity'] <= 20, 'Unbekannter Gegenstand oder ungültige Anzahl.');
            $id = $selection['id'];
            $items[$id] = ($items[$id] ?? 0) + $selection['quantity'];
            $this->ensure($items[$id] <= 20 && count($items) <= 64, 'Zu viele Gegenstände.');
        }
        $items['faustschlag-tritt'] = 1;
        foreach ($items as $id => $count) {
            if (($catalog[$id]['combat']['kind'] ?? null) !== 'weapon') {
                continue;
            }
            for ($instance = 1; $instance <= $count; $instance++) {
                $key = $id.':'.$instance;
                $weapons[$key] = $this->weapon($catalog[$id], $key);
            }
        }
        if (in_array('Natürliche Waffen', $data['advantages'], true)) {
            $weapons['natural'] = $this->weapon([
                'id' => 'natural', 'name' => 'Natürliche Waffen',
                'combat' => ['modes' => [['kind' => 'melee', 'skill' => 'Nahkampf', 'attributes' => ['st', 'ge'], 'damage' => 1, 'precision' => 0]]],
            ], 'natural');
        }
        $armor = null;
        $shield = null;
        foreach (['armor', 'shield'] as $kind) {
            $id = $equipment['active_'.$kind.'_id'] ?? '';
            $this->ensure(is_string($id), 'Ungültiger aktiver Schutz.');
            if ($id !== '') {
                $this->ensure(isset($items[$id]) && ($catalog[$id]['combat']['kind'] ?? null) === $kind, 'Aktiver Schutz muss im Inventar vorhanden sein.');
                ${$kind} = ['id' => $id, 'name' => $catalog[$id]['name'], 'education' => $catalog[$id]['minimumEducation'] ?? 0] + $catalog[$id]['combat'];
            }
        }

        return [
            'name' => $character->displayName(), 'revision' => $character->revision,
            'attributes' => array_intersect_key($data['attributes'], array_flip(['st', 'ge', 'ro', 'wi', 'wa', 'in', 'au'])), 'skills' => array_column($data['skills'], 'value', 'name'),
            'advantages' => $data['advantages'], 'disadvantages' => $data['disadvantages'],
            'advantage_counts' => $data['advantage_counts'],
            'disadvantage_details' => $data['disadvantage_details'] ?? [],
            'weapons' => $weapons, 'items' => $items, 'armor' => $armor, 'shield' => $shield,
            'psychic' => $psychic, 'sources' => $sources, 'catalog_version' => RpgCombatRules::CATALOG_VERSION,
        ];
    }

    private function weapon(array $item, string $instance): array
    {
        $id = $item['id'];
        $modes = [];
        foreach ($item['combat']['modes'] as $mode) {
            $modes[] = $mode + ['type' => '', 'fireRate' => 'E', 'rangeIncrement' => '', 'maxRange' => '', 'magazine' => null];
        }
        $ranged = collect($modes)->firstWhere('kind', 'ranged');
        $capacity = $ranged ? (int) preg_replace('/\D/', '', (string) ($ranged['magazine'] ?? '')) : 0;
        $ammunition = $ranged ? ($capacity > 0 ? 4 * $capacity : (($ranged['type'] === 'Wurfgeschoss' && $id !== 'geworfener-stein') ? 1 : 30)) : 0;
        if ($id === 'energiegewehr') {
            $ammunition = $capacity;
        }

        return [
            'instance' => $instance, 'id' => $id, 'name' => $item['name'], 'modes' => $modes,
            'hands' => in_array($id, self::TWO_HANDED, true) ? 2 : 1,
            'size' => in_array($id, self::TWO_HANDED, true) ? 'large' : 'medium',
            'education' => (int) ($item['minimumEducation'] ?? ($ranged && $ranged['type'] === 'Schießpulverwaffe' ? 2 : 0)),
            'capacity' => $capacity, 'loaded' => $capacity, 'reserve' => max(0, $ammunition - $capacity),
            'thrown' => $ranged && $ranged['type'] === 'Wurfgeschoss' && $id !== 'geworfener-stein',
            'natural' => in_array($id, ['natural', 'faustschlag-tritt'], true),
            'jammed' => false, 'broken' => false, 'position' => null, 'fuel' => null, 'progress' => 0,
        ];
    }

    private function integerValue(mixed $value, int $minimum, int $maximum, string $message): int
    {
        // The editor persists decimal integer strings; validate before casting them.
        $this->ensure(is_int($value) || (is_string($value) && preg_match('/\A-?[0-9]+\z/', $value) === 1), $message);
        $value = (int) $value;
        $this->ensure($value >= $minimum && $value <= $maximum, $message);

        return $value;
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['character' => $message]);
        }
    }
}
