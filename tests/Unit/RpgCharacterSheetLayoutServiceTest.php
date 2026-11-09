<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RpgCharacterCombatCalculator;
use App\Services\RpgCharacterSheetLayoutService;
use App\Services\RpgCharacterSheetPresenter;
use Dompdf\Dompdf;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class)->use(RpgCharacterSheetLayoutServiceTestFixtures::class);
dataset('RpgCharacterSheetLayoutServiceTest.descriptionAreas', static fn (): array => ['default without portrait' => [false, false, false], 'default with portrait' => [true, false, false], 'expanded traits without portrait' => [false, true, false], 'expanded traits with portrait' => [true, true, false], 'occupied tables without portrait' => [false, true, true], 'occupied tables with portrait' => [true, true, true]]);
dataset('RpgCharacterSheetLayoutServiceTest.traitLengths', static function (): array {
    $cases = [];
    foreach (['advantages' => 310, 'disadvantages' => 310, 'trainings' => 100, 'professions' => 100] as $field => $oldLimit) {
        $cases[$field.' fits beyond old cap'] = [$field, $oldLimit, false];
        $cases[$field.' overflows available area'] = [$field, $oldLimit, true];
    }
    $cases['structured advantages fit beyond old cap'] = ['advantages', 310, false, true];
    $cases['structured advantages overflow available area'] = ['advantages', 310, true, true];

    return $cases;
});
dataset('RpgCharacterSheetLayoutServiceTest.invalidAreas', static fn (): array => [[0, 100], [100, 0], [10, 100], [100, 1]]);
beforeEach(function (): void {
    $this->layout = new RpgCharacterSheetLayoutService;
});
test('a description longer than 230 characters can remain complete', function (): void {
    $description = str_repeat('Eine weit gereiste Figur. ', 12);
    $sheet = $this->sheet($description, true);
    $before = $sheet;
    $prepared = $this->layout->prepare($sheet);
    $fitted = $prepared['layout']['description'];
    expect(mb_strlen($description))->toBeGreaterThan(230)
        ->and(implode(' ', $fitted['lines']))->toBe(rtrim($description))
        ->and($fitted['font_size'])->toBe(7.6)
        ->and($fitted['truncated'])->toBeFalse()
        ->and($sheet)->toBe($before)
        ->and($prepared['description'])->toBe($before['description']);
});
test('it selects a smaller font before shortening text', function (): void {
    $text = str_repeat('W', 42);
    $fitted = $this->layout->fitText($text, 100, 32);
    expect($fitted['truncated'])->toBeFalse()
        ->and($fitted['font_size'])->toBeLessThan(7.6)
        ->toBeGreaterThanOrEqual(6.5)
        ->and(implode('', $fitted['lines']))->toBe($text);
    $this->assertFits($fitted, 100, 32);
});
test('overflow uses a visible ellipsis at the minimum font size', function (): void {
    $fitted = $this->layout->fitText(str_repeat('Lange Wörter mit ÄÖÜ und ß. ', 1000), 100, 32);
    expect($fitted['truncated'])->toBeTrue()
        ->and($fitted['font_size'])->toBe(6.5)
        ->and(end($fitted['lines']))->toEndWith('…');
    $this->assertFits($fitted, 100, 32);
});
test('it keeps an intermediate font when that already fits', function (): void {
    $fitted = $this->layout->fitText(str_repeat('W', 28), 100, 25);
    expect($fitted['font_size'])->toBe(7.2)
        ->and($fitted['truncated'])->toBeFalse()
        ->and(implode('', $fitted['lines']))->toBe(str_repeat('W', 28));
    $this->assertFits($fitted, 100, 25);
});
test('exact fit and one character overflow at the minimum size', function (): void {
    $metrics = (new Dompdf)->getFontMetrics();
    $font = $metrics->getFont('DejaVu Sans');
    $text = str_repeat('W', 10);
    $width = $metrics->getTextWidth($text, $font, 6.5);
    $height = max(6.5 * 1.35, $metrics->getFontHeight($font, 6.5));
    $exact = $this->layout->fitText($text, $width, $height);
    $overflow = $this->layout->fitText($text.'W', $width, $height);
    expect($exact['lines'])->toBe([$text])
        ->and($exact['truncated'])->toBeFalse()
        ->and($overflow['truncated'])->toBeTrue()
        ->and($overflow['lines'][0])->toEndWith('…');
    $this->assertFits($overflow, $width, $height);
});
test('unicode and very long unbroken text are safely wrapped', function (): void {
    $text = str_repeat('ÄÖÜäöüß', 20000);
    $fitted = $this->layout->fitText($text, 100, 100);
    expect($fitted['truncated'])->toBeTrue()
        ->and(mb_check_encoding(implode('', $fitted['lines']), 'UTF-8'))->toBeTrue()
        ->and($text)->toStartWith(str_replace('…', '', implode('', $fitted['lines'])))
        ->and(mb_strlen(implode('', $fitted['lines'])))->toBeLessThan(1000);
    $this->assertFits($fitted, 100, 100);
});
test('font measurement accounts for different glyph widths', function (): void {
    $wide = $this->layout->fitText(str_repeat('W', 120), 100, 32);
    $narrow = $this->layout->fitText(str_repeat('i', 120), 100, 32);
    expect($wide['truncated'])->toBeTrue()
        ->and($narrow['truncated'])->toBeFalse();
    $this->assertFits($narrow, 100, 32);
});
test('whitespace and empty descriptions are handled', function (): void {
    $empty = $this->layout->fitText("\n\t  ", 200, 100);
    $text = $this->layout->fitText("  Äußerst\nweit\tgereist.   ", 200, 100);
    expect($empty['lines'])->toBe([])
        ->and($empty['font_size'])->toBe(7.6)
        ->and($empty['truncated'])->toBeFalse()
        ->and($text['lines'])->toBe(['Äußerst weit gereist.']);
});
test('removing the portrait releases the entire image area for text', function (): void {
    $sheet = $this->sheet(str_repeat('Wandert durch die Welt. ', 90), true);
    $with = $this->layout->prepare($sheet)['layout'];
    $sheet['portrait'] = null;
    $without = $this->layout->prepare($sheet)['layout'];
    expect($without['description_height'])->toBeGreaterThan($with['description_height'])
        ->and($without['description']['font_size'])->toBeGreaterThanOrEqual($with['description']['font_size'])
        ->and(mb_strlen(implode('', $without['description']['lines'])))->toBeGreaterThanOrEqual(mb_strlen(implode('', $with['description']['lines'])))
        ->and($without['frame_content_height'])->toBe($with['frame_content_height']);
});
test('description lines leave room for padding and border', function (bool $withPortrait, bool $expandedTraits, bool $occupiedTables): void {
    $data = $this->sheet(str_repeat('W', 5000), $withPortrait);
    if ($expandedTraits) {
        foreach (['advantages', 'disadvantages', 'trainings', 'professions'] as $field) {
            $data[$field] = str_repeat('W', $occupiedTables ? 310 : 40);
        }
    }
    if ($occupiedTables) {
        $data['weapons'] = array_fill(0, 7, $data['weapons'][0]);
        $data['armor'] = array_fill(0, 6, $data['armor'][0]);
    }
    $layout = $this->layout->prepare($data)['layout'];
    $textHeight = $layout['description_height'] - 12 - (2 + ($withPortrait ? 0.25 : 0)) * 72 / 25.4;
    expect($layout['description']['truncated'])->toBeTrue()
        ->and($layout['description']['font_size'])->toBe(6.5)
        ->and(end($layout['description']['lines']))->toEndWith('…');
    $this->assertFits($layout['description'], 85.09999999999999 * 72 / 25.4, $textHeight);
})->with('RpgCharacterSheetLayoutServiceTest.descriptionAreas');
test('the portrait separator can reduce description capacity by one line', function (): void {
    $data = $this->sheet(str_repeat('W', 5000), true);
    $data['advantages'] = str_repeat('W', 100);
    $data['disadvantages'] = str_repeat('W', 100);
    $data['trainings'] = str_repeat('W', 40);
    $data['professions'] = '';
    $layout = $this->layout->prepare($data)['layout'];
    $textHeight = $layout['description_height'] - 12 - 2.25 * 72 / 25.4;
    // A seventeenth line would fit only if the 0.25mm separator were ignored.
    expect(17 * $layout['description']['line_height'])->toBeGreaterThan($textHeight);
    expect(17 * $layout['description']['line_height'])->toBeLessThanOrEqual($textHeight + 0.25 * 72 / 25.4)
        ->and($layout['description']['lines'])->toHaveCount(16)
        ->and($layout['description']['truncated'])->toBeTrue();
    $this->assertFits($layout['description'], 85.09999999999999 * 72 / 25.4, $textHeight);
});
test('long traits can use empty writing rows without further shortening', function (): void {
    $sheet = $this->sheet('Beschreibung');
    foreach (['advantages' => 310, 'disadvantages' => 310, 'trainings' => 100, 'professions' => 100] as $key => $length) {
        $sheet[$key] = mb_substr(str_repeat('Technisch versiert, wachsam und weit gereist, ', 20), 0, $length);
    }
    $prepared = $this->layout->prepare($sheet)['layout'];
    foreach ($prepared['traits']['rows'] as $key => $row) {
        expect($row['truncated'])->toBeFalse()
            ->and(implode(' ', $row['lines']))->toBe($sheet[$key]);
    }
    expect($prepared['weapon_blank_rows'])->toBeLessThan(5)
        ->and(count($sheet['weapons']))->toBe(2)
        ->and(count($sheet['armor']))->toBe(2);
});
test('trait values are fitted after full presentation', function (string $field, int $oldLimit, bool $overflow, bool $structuredAdvantages = false): void {
    $data = require __DIR__.'/../Fixtures/rpg-sheet-layout.php';
    $repetitions = $overflow ? 200 : 13;
    $rawText = str_repeat("Alte\tSprachen\nund Kulturen. ", $repetitions).'ENDE-DER-ANGABE';
    $normalized = str_repeat('Alte Sprachen und Kulturen. ', $repetitions).'ENDE-DER-ANGABE';
    $expected = $normalized;
    switch ($field) {
        case 'advantages':
            $data['advantages'] = ['Gesteigerter Sinn'];
            $data['advantage_details'] = ['Gesteigerter Sinn' => $rawText];
            $expected = 'Gesteigerter Sinn: '.$normalized;
            if ($structuredAdvantages) {
                $data['advantage_effects'] = [['name' => 'Gesteigerter Sinn', 'target' => 'Sehen', 'justification' => $rawText]];
                $expected = 'Gesteigerter Sinn: Sehen – '.$normalized;
            }
            break;
        case 'disadvantages':
            $data['disadvantages'] = ['Verwundbarkeit'];
            $data['disadvantage_details'] = ['Verwundbarkeit' => $rawText];
            $expected = 'Verwundbarkeit: '.$normalized;
            break;
        case 'trainings':
            $data['trainings'] = [$rawText];
            break;
        case 'professions':
            $data['skills'][] = ['name' => 'Beruf: '.$rawText, 'value' => 2];
            $expected = $normalized.' 2';
            break;
    }
    $original = $data;
    $presented = (new RpgCharacterSheetPresenter)->present($data, (new RpgCharacterCombatCalculator)->calculate($data));
    expect(mb_strlen($expected))->toBeGreaterThan($oldLimit)
        ->and($presented[$field])->toBe($expected);
    $prepared = $this->layout->prepare($presented);
    $row = $prepared['layout']['traits']['rows'][$field];
    $rendered = implode(' ', $row['lines']);
    expect($row['truncated'])->toBe($overflow);
    if ($overflow) {
        expect($rendered)->toEndWith('…')
            ->and($expected)->toStartWith(mb_substr($rendered, 0, -1));
    } else {
        expect($rendered)->toBe($expected)
            ->toContain('ENDE-DER-ANGABE');
    }
    expect($prepared[$field])->toBe($expected)
        ->and($data)->toBe($original);
})->with('RpgCharacterSheetLayoutServiceTest.traitLengths');
test('extreme traits are visibly shortened when no empty rows are available', function (): void {
    $sheet = $this->sheet('Beschreibung');
    $sheet['weapons'] = array_fill(0, 7, $sheet['weapons'][0]);
    $sheet['armor'] = array_fill(0, 6, $sheet['armor'][0]);
    foreach (['advantages', 'disadvantages', 'trainings', 'professions'] as $key) {
        $sheet[$key] = str_repeat('W', 310);
    }
    $prepared = $this->layout->prepare($sheet)['layout'];
    expect($prepared['weapon_blank_rows'])->toBe(0)
        ->and($prepared['armor_blank_rows'])->toBe(0);
    foreach ($prepared['traits']['rows'] as $row) {
        expect($row['truncated'])->toBeTrue()
            ->and(end($row['lines']))->toEndWith('…');
    }
    expect($sheet['weapons'])->toHaveCount(7)
        ->and($sheet['armor'])->toHaveCount(6);
});
test('unusable areas are rejected', function (float $width, float $height): void {
    expect(fn () => $this->layout->fitText('Beschreibung', $width, $height))->toThrow(InvalidArgumentException::class);
})->with('RpgCharacterSheetLayoutServiceTest.invalidAreas');

afterEach(function (): void {
    // Release Dompdf document cycles between dataset rows.
    gc_collect_cycles();
});
