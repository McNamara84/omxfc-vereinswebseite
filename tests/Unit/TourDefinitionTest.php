<?php

namespace Tests\Unit;

use App\Support\Tours\TourDefinition;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(TourDefinition::class)]
class TourDefinitionTest extends TestCase
{
    public function test_start_mode_defaults_to_automatic_when_omitted(): void
    {
        $this->assertSame('automatic', TourDefinition::fromArray('hauptmenue', [])->startMode);
    }

    #[TestWith(['automatic'])]
    #[TestWith(['invitation'])]
    public function test_supported_start_modes_are_preserved(string $startMode): void
    {
        $definition = TourDefinition::fromArray('profilpflege', ['start_mode' => $startMode]);

        $this->assertSame($startMode, $definition->startMode);
    }

    #[TestWith(['invitaton'])]
    #[TestWith([''])]
    #[TestWith(['Invitation'])]
    #[TestWith([' automatic'])]
    #[TestWith([null])]
    #[TestWith([false])]
    #[TestWith([true])]
    #[TestWith([1])]
    #[TestWith([[]])]
    public function test_invalid_start_modes_are_rejected(mixed $startMode): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Ungültiger start_mode für Tour [profilpflege]. Erlaubt sind automatic und invitation.');

        TourDefinition::fromArray('profilpflege', ['start_mode' => $startMode]);
    }
}
