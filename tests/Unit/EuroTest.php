<?php

namespace Tests\Unit;

use App\Support\Euro;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Euro::class)]
class EuroTest extends TestCase
{
    public function test_to_cents_accepts_plain_and_decimal_amounts(): void
    {
        $this->assertSame(1200, Euro::toCents('12'));
        $this->assertSame(1234, Euro::toCents('12.34'));
        $this->assertSame(1234, Euro::toCents('12,34'));
        $this->assertSame(1200, Euro::toCents(12));
    }

    #[TestWith(['1e3'])]
    #[TestWith(['12.345'])]
    #[TestWith(['12,345'])]
    #[TestWith(['12 34'])]
    public function test_to_cents_rejects_invalid_money_formats(string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);

        Euro::toCents($amount);
    }
}
