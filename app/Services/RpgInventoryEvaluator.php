<?php

namespace App\Services;

use App\Support\RpgInventoryCatalog;

final class RpgInventoryEvaluator
{
    public function __construct(private RpgInventoryAdapter $adapter) {}

    public function evaluate(array $payload, array $input, ?array $otherPayload = null): array
    {
        $before = $this->adapter->state($payload);
        $after = $before;
        $kind = $input['kind'];
        $trade = in_array($kind, ['purchase', 'sale', 'swap'], true);
        $initialize = $kind === 'initialize';
        $this->ensure($initialize ? ! $before['confirmed'] : $before['confirmed'], $initialize
            ? 'Der Bestand wurde bereits übernommen.' : 'Bitte zuerst Geld und Munition übernehmen und bestätigen.');
        $amount = $input['amount'];
        $incoming = $this->quantities($input['incoming']);
        $outgoing = $this->quantities($input['outgoing']);
        $set = $this->quantities($input['set'], zero: true);
        $this->ensure($input['other_character_id'] === null || $trade, 'Eine Gegenpartei ist nur beim Handel möglich.');
        $this->ensure($trade || ($input['winner'] === 'none' && $input['success_points'] === 0 && $input['external_party'] === '' && $input['price_reason'] === ''), 'Preisangaben sind nur beim Handel möglich.');
        $this->ensure($kind === 'regenerate' || $input['minutes'] === 0, 'Spielminuten sind nur bei Regeneration möglich.');
        $this->ensure(in_array($kind, ['initialize', 'correct'], true) || $set === [], 'Sollbestände sind nur bei Übernahme oder Korrektur möglich.');
        $this->ensure(in_array($kind, ['grant', 'purchase', 'swap'], true) || $incoming === [], 'Dieser Vorgang erlaubt keinen Wareneingang.');
        $this->ensure(in_array($kind, ['loss', 'sale', 'swap'], true) || $outgoing === [], 'Dieser Vorgang erlaubt keinen Warenausgang.');
        $this->ensure($kind !== 'equip' || $amount === null, 'Ausrüstungswechsel verändern kein Geld.');
        $this->ensure($kind === 'equip' || $input['metadata'] === [], 'Ausrüstungsangaben sind nur beim Ausrüstungswechsel möglich.');
        $this->ensure($kind !== 'regenerate' || ($amount === null && $input['metadata'] === []), 'Regeneration verändert nur die Ladung.');
        foreach ([...array_keys($incoming), ...array_keys($outgoing)] as $id) {
            $this->ensure(isset(RpgInventoryCatalog::positions()[$id]), 'Unbekannte Katalogposition: '.$id);
        }
        // Incoming goods must never fund either participant's outgoing goods.
        $this->ensureOutgoingStock($before['stocks'], $outgoing, 'Charakter');
        $otherBefore = null;
        if ($otherPayload !== null) {
            $otherBefore = $this->adapter->state($otherPayload);
            $this->ensure($otherBefore['confirmed'], 'Bitte zuerst den Bestand der Gegenpartei bestätigen.');
            $this->ensureOutgoingStock($otherBefore['stocks'], $incoming, 'Gegenpartei');
        }
        foreach ($set as $id => $quantity) {
            $this->ensure(isset(RpgInventoryCatalog::positions()[$id]) || (isset($before['stocks'][$id]) && $quantity === 0), 'Unbekannte Katalogposition: '.$id);
            $after['stocks'][$id] = $quantity;
        }
        foreach ($outgoing as $id => $quantity) {
            $after['stocks'][$id] = ($after['stocks'][$id] ?? 0) - $quantity;
        }
        foreach ($incoming as $id => $quantity) {
            $after['stocks'][$id] = ($after['stocks'][$id] ?? 0) + $quantity;
        }
        if ($initialize) {
            $this->ensure($amount !== null, 'Bitte den tatsächlichen Bucks-Bestand bestätigen, auch bei 0 Bucks.');
            $after['confirmed'] = true;
            $after['bucks'] = $amount;
        } elseif ($kind === 'correct' && $amount !== null) {
            $after['bucks'] = $amount;
        } elseif (in_array($kind, ['grant', 'loss', 'money', 'purchase', 'sale', 'swap'], true)) {
            $this->ensure($kind !== 'money' || ($amount ?? 0) > 0, 'Bitte einen positiven Geldbetrag angeben.');
            $sign = in_array($kind, ['loss', 'purchase'], true) || (in_array($kind, ['money', 'swap'], true) && $input['cash_direction'] === 'expense') ? -1 : 1;
            $after['bucks'] += $sign * ($amount ?? 0);
        }
        $price = null;
        if ($trade) {
            $this->ensure($input['other_character_id'] !== null || $input['external_party'] !== '', 'Bitte die externe Gegenpartei benennen.');
            $this->ensure($kind !== 'purchase' || $incoming !== [], 'Bitte die gekauften Positionen wählen.');
            $this->ensure($kind !== 'sale' || $outgoing !== [], 'Bitte die verkauften Positionen wählen.');
            $this->ensure($kind !== 'swap' || ($incoming !== [] && $outgoing !== []), 'Ein Tausch braucht beide Warenseiten.');
            if ($kind !== 'swap') {
                $this->ensure(($amount ?? 0) > 0, 'Ein Kauf oder Verkauf benötigt einen positiven Gesamtpreis.');
                $price = $this->price($kind === 'purchase' ? $incoming : $outgoing, $input);
            } else {
                $this->ensure($input['winner'] === 'none' && $input['success_points'] === 0, 'Beim Tausch den ausgehandelten Bucks-Ausgleich direkt angeben.');
            }
        }
        if ($kind === 'regenerate') {
            $this->ensure($input['minutes'] > 0 && ($after['stocks']['item:energiegewehr'] ?? 0) > 0, 'Regeneration benötigt ein Energiegewehr und vergangene Spielminuten.');
            $count = $after['stocks']['item:energiegewehr'];
            $after['stocks']['ammo:energiegewehr'] = min(100 * $count, ($after['stocks']['ammo:energiegewehr'] ?? 0) + $count * $input['minutes']);
        }
        foreach ($input['metadata'] as $key => $value) {
            $after[$key] = $value;
        }
        foreach (['armor', 'shield', 'clothing'] as $key) {
            if (($input['metadata'][$key] ?? '') !== '') {
                $position = ($key === 'clothing' ? 'clothing:' : 'item:').$input['metadata'][$key];
                $this->ensure(($after['stocks'][$position] ?? 0) > 0, 'Gewählte aktive Ausrüstung ist nicht im Bestand.');
            }
        }
        $after = $this->finish($after);
        $other = null;
        if ($otherBefore !== null) {
            $otherAfter = $otherBefore;
            foreach (array_unique([...array_keys($before['stocks']), ...array_keys($after['stocks'])]) as $id) {
                $delta = ($after['stocks'][$id] ?? 0) - ($before['stocks'][$id] ?? 0);
                $otherAfter['stocks'][$id] = ($otherAfter['stocks'][$id] ?? 0) - $delta;
            }
            $otherAfter['bucks'] -= $after['bucks'] - $before['bucks'];
            $otherAfter = $this->finish($otherAfter);
            $this->adapter->equipment($otherAfter);
            $other = ['before' => $otherBefore, 'after' => $otherAfter, 'changes' => $this->changes($otherBefore, $otherAfter)];
        }
        $equipment = $this->adapter->equipment($after, $payload['equipment'] ?? []);
        $this->ensure($before !== $after, 'Es wurden keine Bestände oder Ausrüstungsangaben geändert.');

        return ['before' => $before, 'after' => $after, 'equipment' => $equipment, 'changes' => $this->changes($before, $after),
            'other' => $other, 'price' => $price, 'trade_values' => ['incoming' => $this->value($incoming, 'trade_value'), 'outgoing' => $this->value($outgoing, 'trade_value')]];
    }

    public function reverse(array $current, array $before, array $after): array
    {
        $result = $current;
        foreach (array_unique([...array_keys($before['stocks']), ...array_keys($after['stocks'])]) as $id) {
            $result['stocks'][$id] = ($result['stocks'][$id] ?? 0) - (($after['stocks'][$id] ?? 0) - ($before['stocks'][$id] ?? 0));
        }
        $result['bucks'] -= $after['bucks'] - $before['bucks'];
        foreach (['clothing', 'armor', 'shield', 'notes'] as $key) {
            if ($before[$key] !== $after[$key]) {
                $this->ensure($current[$key] === $after[$key], 'Später geänderte Ausrüstungsangaben verhindern die Rückbuchung. Bitte die Angaben über einen Ausrüstungswechsel korrigieren.');
                $result[$key] = $before[$key];
            }
        }
        $result = $this->finish($result);
        $this->adapter->equipment($result);

        return ['before' => $current, 'after' => $result, 'changes' => $this->changes($current, $result)];
    }

    public function changes(array $before, array $after): array
    {
        $changes = [];
        foreach (array_unique([...array_keys($before['stocks']), ...array_keys($after['stocks'])]) as $id) {
            $a = $before['stocks'][$id] ?? 0;
            $b = $after['stocks'][$id] ?? 0;
            if ($a !== $b) {
                $changes[] = ['id' => $id, 'name' => RpgInventoryCatalog::positions()[$id]['name'] ?? $id, 'before' => $a, 'after' => $b];
            }
        }
        foreach (['bucks' => 'Bucks', 'confirmed' => 'Bestand bestätigt', 'clothing' => 'Aktive Kleidung', 'armor' => 'Aktive Rüstung', 'shield' => 'Aktiver Schild', 'notes' => 'Notizen'] as $key => $name) {
            if ($before[$key] !== $after[$key]) {
                $changes[] = ['id' => $key, 'name' => $name, 'before' => $before[$key], 'after' => $after[$key]];
            }
        }

        return $changes;
    }

    private function finish(array $state): array
    {
        $state['stocks'] = array_filter($state['stocks'], fn ($quantity) => $quantity !== 0);
        ksort($state['stocks']);
        foreach (['armor', 'shield'] as $key) {
            if (($state['stocks']['item:'.$state[$key]] ?? 0) <= 0) {
                $state[$key] = '';
            }
        }
        if (($state['stocks']['clothing:'.$state['clothing']] ?? 0) <= 0) {
            $state['clothing'] = '';
        }

        return $state;
    }

    private function ensureOutgoingStock(array $stocks, array $outgoing, string $party): void
    {
        foreach ($outgoing as $id => $quantity) {
            $this->ensure(($stocks[$id] ?? 0) >= $quantity, 'Nicht genügend Bestand für Warenausgang ('.$party.'): '.RpgInventoryCatalog::positions()[$id]['name']);
        }
    }

    private function quantities(array $rows, bool $zero = false): array
    {
        $result = [];
        foreach ($rows as $row) {
            $this->ensure(! isset($result[$row['id']]), 'Positionen müssen innerhalb einer Warenseite eindeutig sein.');
            $this->ensure($row['quantity'] >= ($zero ? 0 : 1), 'Bitte eine gültige Menge angeben.');
            $result[$row['id']] = $row['quantity'];
        }

        return $result;
    }

    private function value(array $quantities, string $field): ?int
    {
        $sum = 0;
        foreach ($quantities as $id => $quantity) {
            $value = RpgInventoryCatalog::positions()[$id][$field] ?? null;
            if ($value === null) {
                return null;
            }
            $sum += $value * $quantity;
        }

        return $sum;
    }

    private function price(array $positions, array $input): array
    {
        $base = $this->value($positions, 'price');
        $factor = match ($input['winner']) {
            'buyer' => 100 - 5 * $input['success_points'], 'seller' => 100 + 5 * $input['success_points'], default => 100,
        };
        $this->ensure($input['winner'] !== 'none' || $input['success_points'] === 0, 'Erfolgspunkte benötigen einen Verhandlungsgewinner.');
        $suggested = $base === null ? null : intdiv(max(0, $base * $factor) + 50, 100);
        $this->ensure($suggested === $input['amount'] || $input['price_reason'] !== '', 'Bitte den abweichenden oder frei festgelegten Preis begründen.');

        return ['base' => $base, 'percent' => $factor, 'suggested' => $suggested, 'agreed' => $input['amount']];
    }

    private function ensure(bool $condition, string $message): void
    {
        RpgInventoryAdapter::ensure($condition, $message);
    }
}
