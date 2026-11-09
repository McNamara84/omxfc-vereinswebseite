<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RpgCharacterCombatCalculator;
use App\Services\RpgCharacterSheetLayoutService;
use App\Services\RpgCharacterSheetPresenter;
use Dompdf\Dompdf;

trait RpgCharacterSheetLayoutServiceTestFixtures
{
    private RpgCharacterSheetLayoutService $layout;

    private function sheet(string $description, bool $withPortrait = false): array
    {
        $data = require __DIR__.'/../Fixtures/rpg-sheet-layout.php';
        $data['character']['description'] = $description;
        if ($withPortrait) {
            $data['portrait'] = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=';
        }

        return (new RpgCharacterSheetPresenter)->present($data, (new RpgCharacterCombatCalculator)->calculate($data));
    }

    private function assertFits(array $fitted, float $width, float $height): void
    {
        $metrics = (new Dompdf)->getFontMetrics();
        foreach ($fitted['lines'] as $line) {
            expect($metrics->getTextWidth($line, $metrics->getFont('DejaVu Sans'), $fitted['font_size']))->toBeLessThanOrEqual($width + 0.001);
        }
        expect(count($fitted['lines']) * $fitted['line_height'])->toBeLessThanOrEqual($height + 0.001);
    }
}
