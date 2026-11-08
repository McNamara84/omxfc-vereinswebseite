<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\PreviewText;
use Illuminate\Support\Stringable;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class);

covers(PreviewText::class);

test('trims strips and limits content', function (): void {
    $content = '   <p>Dieses Preview wird ordentlich begrenzt und bereinigt.</p>   ';
    $preview = PreviewText::make($content, 20);
    expect($preview)->toBeInstanceOf(Stringable::class)
        ->and($preview->toString())->toBe('Dieses Preview wird...');
});

test('returns empty stringable for blank content', function (): void {
    $preview = PreviewText::make('<div>   </div>', 50);
    expect($preview->isEmpty())->toBeTrue()
        ->and($preview->toString())->toBe('');
});

test('preserves unicode and squishes whitespace', function (): void {
    $content = "<span>Hallo\n  zusammen   –   viel Spaß! </span>";
    $preview = PreviewText::make($content, 80);
    expect($preview->toString())->toBe('Hallo zusammen – viel Spaß!');
});
