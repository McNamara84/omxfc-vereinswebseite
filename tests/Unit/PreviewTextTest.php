<?php

namespace Tests\Unit;

use App\Support\PreviewText;
use Illuminate\Support\Stringable;
use PHPUnit\Framework\TestCase;

class PreviewTextTest extends TestCase
{
    public function test_trims_strips_and_limits_content(): void
    {
        $content = '   <p>Dieses Preview wird ordentlich begrenzt und bereinigt.</p>   ';

        $preview = PreviewText::make($content, 20);

        $this->assertInstanceOf(Stringable::class, $preview);
        $this->assertSame('Dieses Preview wird...', $preview->toString());
    }

    public function test_returns_empty_stringable_for_blank_content(): void
    {
        $preview = PreviewText::make('<div>   </div>', 50);

        $this->assertTrue($preview->isEmpty());
        $this->assertSame('', $preview->toString());
    }

    public function test_preserves_unicode_and_squishes_whitespace(): void
    {
        $content = "<span>Hallo\n  zusammen   –   viel Spaß! </span>";

        $preview = PreviewText::make($content, 80);

        $this->assertSame('Hallo zusammen – viel Spaß!', $preview->toString());
    }
}
