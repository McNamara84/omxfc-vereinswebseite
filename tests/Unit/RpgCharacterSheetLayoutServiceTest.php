<?php

namespace Tests\Unit;

use App\Services\RpgCharacterCombatCalculator;
use App\Services\RpgCharacterSheetLayoutService;
use App\Services\RpgCharacterSheetPresenter;
use Dompdf\Dompdf;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RpgCharacterSheetLayoutServiceTest extends TestCase
{
    private RpgCharacterSheetLayoutService $layout;

    protected function setUp(): void
    {
        parent::setUp();
        $this->layout = new RpgCharacterSheetLayoutService;
    }

    public function test_a_description_longer_than_230_characters_can_remain_complete(): void
    {
        $description = str_repeat('Eine weit gereiste Figur. ', 12);
        $sheet = $this->sheet($description, true);
        $before = $sheet;
        $prepared = $this->layout->prepare($sheet);
        $fitted = $prepared['layout']['description'];

        $this->assertGreaterThan(230, mb_strlen($description));
        $this->assertSame(rtrim($description), implode(' ', $fitted['lines']));
        $this->assertSame(7.6, $fitted['font_size']);
        $this->assertFalse($fitted['truncated']);
        $this->assertSame($before, $sheet);
        $this->assertSame($before['description'], $prepared['description']);
    }

    public function test_it_selects_a_smaller_font_before_shortening_text(): void
    {
        $text = str_repeat('W', 42);
        $fitted = $this->layout->fitText($text, 100, 32);

        $this->assertFalse($fitted['truncated']);
        $this->assertLessThan(7.6, $fitted['font_size']);
        $this->assertGreaterThanOrEqual(6.5, $fitted['font_size']);
        $this->assertSame($text, implode('', $fitted['lines']));
        $this->assertFits($fitted, 100, 32);
    }

    public function test_overflow_uses_a_visible_ellipsis_at_the_minimum_font_size(): void
    {
        $fitted = $this->layout->fitText(str_repeat('Lange Wörter mit ÄÖÜ und ß. ', 1000), 100, 32);

        $this->assertTrue($fitted['truncated']);
        $this->assertSame(6.5, $fitted['font_size']);
        $this->assertStringEndsWith('…', end($fitted['lines']));
        $this->assertFits($fitted, 100, 32);
    }

    public function test_it_keeps_an_intermediate_font_when_that_already_fits(): void
    {
        $fitted = $this->layout->fitText(str_repeat('W', 28), 100, 25);

        $this->assertSame(7.2, $fitted['font_size']);
        $this->assertFalse($fitted['truncated']);
        $this->assertSame(str_repeat('W', 28), implode('', $fitted['lines']));
        $this->assertFits($fitted, 100, 25);
    }

    public function test_exact_fit_and_one_character_overflow_at_the_minimum_size(): void
    {
        $metrics = (new Dompdf)->getFontMetrics();
        $font = $metrics->getFont('DejaVu Sans');
        $text = str_repeat('W', 10);
        $width = $metrics->getTextWidth($text, $font, 6.5);
        $height = max(6.5 * 1.35, $metrics->getFontHeight($font, 6.5));
        $exact = $this->layout->fitText($text, $width, $height);
        $overflow = $this->layout->fitText($text.'W', $width, $height);

        $this->assertSame([$text], $exact['lines']);
        $this->assertFalse($exact['truncated']);
        $this->assertTrue($overflow['truncated']);
        $this->assertStringEndsWith('…', $overflow['lines'][0]);
        $this->assertFits($overflow, $width, $height);
    }

    public function test_unicode_and_very_long_unbroken_text_are_safely_wrapped(): void
    {
        $text = str_repeat('ÄÖÜäöüß', 20000);
        $fitted = $this->layout->fitText($text, 100, 100);

        $this->assertTrue($fitted['truncated']);
        $this->assertTrue(mb_check_encoding(implode('', $fitted['lines']), 'UTF-8'));
        $this->assertStringStartsWith(str_replace('…', '', implode('', $fitted['lines'])), $text);
        $this->assertLessThan(1000, mb_strlen(implode('', $fitted['lines'])));
        $this->assertFits($fitted, 100, 100);
    }

    public function test_font_measurement_accounts_for_different_glyph_widths(): void
    {
        $wide = $this->layout->fitText(str_repeat('W', 120), 100, 32);
        $narrow = $this->layout->fitText(str_repeat('i', 120), 100, 32);

        $this->assertTrue($wide['truncated']);
        $this->assertFalse($narrow['truncated']);
        $this->assertFits($narrow, 100, 32);
    }

    public function test_whitespace_and_empty_descriptions_are_handled(): void
    {
        $empty = $this->layout->fitText("\n\t  ", 200, 100);
        $text = $this->layout->fitText("  Äußerst\nweit\tgereist.\u{00a0}  ", 200, 100);

        $this->assertSame([], $empty['lines']);
        $this->assertSame(7.6, $empty['font_size']);
        $this->assertFalse($empty['truncated']);
        $this->assertSame(['Äußerst weit gereist.'], $text['lines']);
    }

    public function test_removing_the_portrait_releases_the_entire_image_area_for_text(): void
    {
        $sheet = $this->sheet(str_repeat('Wandert durch die Welt. ', 90), true);
        $with = $this->layout->prepare($sheet)['layout'];
        $sheet['portrait'] = null;
        $without = $this->layout->prepare($sheet)['layout'];

        $this->assertGreaterThan($with['description_height'], $without['description_height']);
        $this->assertGreaterThanOrEqual($with['description']['font_size'], $without['description']['font_size']);
        $this->assertGreaterThanOrEqual(mb_strlen(implode('', $with['description']['lines'])), mb_strlen(implode('', $without['description']['lines'])));
        $this->assertSame($with['frame_content_height'], $without['frame_content_height']);
    }

    public static function descriptionAreas(): array
    {
        return [
            'default without portrait' => [false, false, false],
            'default with portrait' => [true, false, false],
            'expanded traits without portrait' => [false, true, false],
            'expanded traits with portrait' => [true, true, false],
            'occupied tables without portrait' => [false, true, true],
            'occupied tables with portrait' => [true, true, true],
        ];
    }

    #[DataProvider('descriptionAreas')]
    public function test_description_lines_leave_room_for_padding_and_border(bool $withPortrait, bool $expandedTraits, bool $occupiedTables): void
    {
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

        $this->assertTrue($layout['description']['truncated']);
        $this->assertSame(6.5, $layout['description']['font_size']);
        $this->assertStringEndsWith('…', end($layout['description']['lines']));
        $this->assertFits($layout['description'], 85.1 * 72 / 25.4, $textHeight);
    }

    public function test_the_portrait_separator_can_reduce_description_capacity_by_one_line(): void
    {
        $data = $this->sheet(str_repeat('W', 5000), true);
        $data['advantages'] = str_repeat('W', 100);
        $data['disadvantages'] = str_repeat('W', 100);
        $data['trainings'] = str_repeat('W', 40);
        $data['professions'] = '';
        $layout = $this->layout->prepare($data)['layout'];
        $textHeight = $layout['description_height'] - 12 - 2.25 * 72 / 25.4;

        // A seventeenth line would fit only if the 0.25mm separator were ignored.
        $this->assertGreaterThan($textHeight, 17 * $layout['description']['line_height']);
        $this->assertLessThanOrEqual($textHeight + 0.25 * 72 / 25.4, 17 * $layout['description']['line_height']);
        $this->assertCount(16, $layout['description']['lines']);
        $this->assertTrue($layout['description']['truncated']);
        $this->assertFits($layout['description'], 85.1 * 72 / 25.4, $textHeight);
    }

    public function test_long_traits_can_use_empty_writing_rows_without_further_shortening(): void
    {
        $sheet = $this->sheet('Beschreibung');
        foreach (['advantages' => 310, 'disadvantages' => 310, 'trainings' => 100, 'professions' => 100] as $key => $length) {
            $sheet[$key] = mb_substr(str_repeat('Technisch versiert, wachsam und weit gereist, ', 20), 0, $length);
        }
        $prepared = $this->layout->prepare($sheet)['layout'];

        foreach ($prepared['traits']['rows'] as $key => $row) {
            $this->assertFalse($row['truncated'], $key);
            $this->assertSame($sheet[$key], implode(' ', $row['lines']));
        }
        $this->assertLessThan(5, $prepared['weapon_blank_rows']);
        $this->assertSame(2, count($sheet['weapons']));
        $this->assertSame(2, count($sheet['armor']));
    }

    public static function traitLengths(): array
    {
        $cases = [];
        foreach (['advantages' => 310, 'disadvantages' => 310, 'trainings' => 100, 'professions' => 100] as $field => $oldLimit) {
            $cases[$field.' fits beyond old cap'] = [$field, $oldLimit, false];
            $cases[$field.' overflows available area'] = [$field, $oldLimit, true];
        }
        $cases['structured advantages fit beyond old cap'] = ['advantages', 310, false, true];
        $cases['structured advantages overflow available area'] = ['advantages', 310, true, true];

        return $cases;
    }

    #[DataProvider('traitLengths')]
    public function test_trait_values_are_fitted_after_full_presentation(string $field, int $oldLimit, bool $overflow, bool $structuredAdvantages = false): void
    {
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

        $this->assertGreaterThan($oldLimit, mb_strlen($expected));
        $this->assertSame($expected, $presented[$field], 'Der Presenter muss den vollständigen normalisierten Text an das Layout weitergeben.');

        $prepared = $this->layout->prepare($presented);
        $row = $prepared['layout']['traits']['rows'][$field];
        $rendered = implode(' ', $row['lines']);

        $this->assertSame($overflow, $row['truncated']);
        if ($overflow) {
            $this->assertStringEndsWith('…', $rendered);
            $this->assertStringStartsWith(mb_substr($rendered, 0, -1), $expected);
        } else {
            $this->assertSame($expected, $rendered);
            $this->assertStringContainsString('ENDE-DER-ANGABE', $rendered);
        }
        $this->assertSame($expected, $prepared[$field]);
        $this->assertSame($original, $data);
    }

    public function test_extreme_traits_are_visibly_shortened_when_no_empty_rows_are_available(): void
    {
        $sheet = $this->sheet('Beschreibung');
        $sheet['weapons'] = array_fill(0, 7, $sheet['weapons'][0]);
        $sheet['armor'] = array_fill(0, 6, $sheet['armor'][0]);
        foreach (['advantages', 'disadvantages', 'trainings', 'professions'] as $key) {
            $sheet[$key] = str_repeat('W', 310);
        }
        $prepared = $this->layout->prepare($sheet)['layout'];

        $this->assertSame(0, $prepared['weapon_blank_rows']);
        $this->assertSame(0, $prepared['armor_blank_rows']);
        foreach ($prepared['traits']['rows'] as $row) {
            $this->assertTrue($row['truncated']);
            $this->assertStringEndsWith('…', end($row['lines']));
        }
        $this->assertSame(7, count($sheet['weapons']));
        $this->assertSame(6, count($sheet['armor']));
    }

    public static function invalidAreas(): array
    {
        return [[0, 100], [100, 0], [10, 100], [100, 1]];
    }

    #[DataProvider('invalidAreas')]
    public function test_unusable_areas_are_rejected(float $width, float $height): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->layout->fitText('Beschreibung', $width, $height);
    }

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
            $this->assertLessThanOrEqual($width + 0.001, $metrics->getTextWidth($line, $metrics->getFont('DejaVu Sans'), $fitted['font_size']));
        }
        $this->assertLessThanOrEqual($height + 0.001, count($fitted['lines']) * $fitted['line_height']);
    }
}
