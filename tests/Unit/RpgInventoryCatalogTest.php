<?php

namespace Tests\Unit;

use App\Support\RpgInventoryCatalog;
use PHPUnit\Framework\TestCase;

class RpgInventoryCatalogTest extends TestCase
{
    public function test_prices_are_exact_numbers_and_ambiguous_values_are_not_free(): void
    {
        $catalog = RpgInventoryCatalog::positions();
        $this->assertSame(1000, $catalog['item:gewehr-bolzer']['price']);
        $this->assertSame(1200, $catalog['item:automatikgewehr']['trade_value']);
        $this->assertSame(2000000, $catalog['item:ewat']['price']);
        $this->assertNull($catalog['item:transportqualle']['price']);
        $this->assertNull($catalog['item:msc']['trade_value']);
        $this->assertSame(10, $catalog['clothing:kleidung-einfach']['price']);
        $this->assertNull($catalog['ammo:revolver']['price']);
        $this->assertArrayNotHasKey('item:faustschlag-tritt', $catalog);
        foreach ($catalog as $id => $row) {
            $this->assertNotEmpty($id);
            $this->assertNotEmpty($row['name']);
            $this->assertGreaterThan(0, $row['limit']);
        }
    }

    public function test_starting_ammunition_obeys_projectile_and_magazine_units(): void
    {
        $ammo = RpgInventoryCatalog::startingAmmunition([
            ['id' => 'bogen', 'quantity' => 2], ['id' => 'armbrust', 'quantity' => 1], ['id' => 'revolver', 'quantity' => 2],
            ['id' => 'schleuder', 'quantity' => 1], ['id' => 'zwille', 'quantity' => 1], ['id' => 'seil', 'quantity' => 1],
        ]);
        $this->assertSame(['ammo:pfeile' => 60, 'ammo:bolzen' => 30, 'ammo:revolver' => 48, 'ammo:steine' => 60], $ammo);
        $this->assertNull(RpgInventoryCatalog::ammunitionId('seil'));
        $this->assertSame(0, RpgInventoryCatalog::capacity('seil'));
        $this->assertSame(100, RpgInventoryCatalog::capacity('energiegewehr'));
    }
}
