<?php

namespace Tests\Unit;

use App\Enums\BookType;
use PHPUnit\Framework\TestCase;

class BookTypeTest extends TestCase
{
    public function test_label_returns_custom_text_for_volk_der_tiefe(): void
    {
        $this->assertSame('Das Volk der Tiefe-Heftromane', BookType::DasVolkDerTiefe->label());
    }

    public function test_label_defaults_to_value_for_other_types(): void
    {
        $this->assertSame(BookType::MissionMars->value, BookType::MissionMars->label());
    }
}
