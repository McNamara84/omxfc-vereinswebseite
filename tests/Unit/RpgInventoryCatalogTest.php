<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\RpgInventoryCatalog;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);

test('prices are exact numbers and ambiguous values are not free', function (): void {
    $catalog = RpgInventoryCatalog::positions();
    expect($catalog['item:gewehr-bolzer']['price'])->toBe(1000)
        ->and($catalog['item:automatikgewehr']['trade_value'])->toBe(1200)
        ->and($catalog['item:ewat']['price'])->toBe(2000000)
        ->and($catalog['item:transportqualle']['price'])->toBeNull()
        ->and($catalog['item:msc']['trade_value'])->toBeNull()
        ->and($catalog['clothing:kleidung-einfach']['price'])->toBe(10)
        ->and($catalog['ammo:revolver']['price'])->toBeNull()
        ->and($catalog)->not->toHaveKey('item:faustschlag-tritt');
    foreach ($catalog as $id => $row) {
        expect($id)->not->toBeEmpty()
            ->and($row['name'])->not->toBeEmpty()
            ->and($row['limit'])->toBeGreaterThan(0);
    }
});

test('starting ammunition obeys projectile and magazine units', function (): void {
    $ammo = RpgInventoryCatalog::startingAmmunition([['id' => 'bogen', 'quantity' => 2], ['id' => 'armbrust', 'quantity' => 1], ['id' => 'revolver', 'quantity' => 2], ['id' => 'schleuder', 'quantity' => 1], ['id' => 'zwille', 'quantity' => 1], ['id' => 'seil', 'quantity' => 1]]);
    expect($ammo)->toBe(['ammo:pfeile' => 60, 'ammo:bolzen' => 30, 'ammo:revolver' => 48, 'ammo:steine' => 60])
        ->and(RpgInventoryCatalog::ammunitionId('seil'))->toBeNull()
        ->and(RpgInventoryCatalog::capacity('seil'))->toBe(0)
        ->and(RpgInventoryCatalog::capacity('energiegewehr'))->toBe(100);
});
