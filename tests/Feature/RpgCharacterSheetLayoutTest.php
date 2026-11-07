<?php

namespace Tests\Feature;

use App\Services\RpgCharacterCombatCalculator;
use App\Services\RpgCharacterSheetLayoutService;
use App\Services\RpgCharacterSheetPresenter;
use DOMElement;
use Dompdf\Dompdf;
use Dompdf\Frame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RpgCharacterSheetLayoutTest extends TestCase
{
    use RefreshDatabase;

    public static function sheetVariants(): array
    {
        return [
            'without portrait' => [false, false],
            'with portrait' => [true, false],
            'maximum text lengths' => [false, true],
            'maximum text lengths with portrait' => [true, true],
            'saved character with experience summary' => [true, true, true],
            'saved inventory with money and full descriptions' => [true, true, true, 'inventory'],
            'empty description' => [false, false, false, 'empty'],
            'empty description with portrait' => [true, false, false, 'empty'],
            'short description' => [false, false, false, 'short'],
            'short description with portrait' => [true, false, false, 'short'],
            'long description' => [false, false, false, 'long'],
            'long description with portrait' => [true, false, false, 'long'],
            'unbroken text' => [false, true, false, 'unbroken'],
            'unbroken text with portrait' => [true, true, false, 'unbroken'],
            'wide traits' => [true, true, true, 'wide'],
            'wide traits without portrait' => [false, true, false, 'wide'],
            'fully occupied tables' => [true, true, true, 'occupied'],
            'fully occupied tables with long description' => [true, true, false, 'occupied-long'],
            'fully occupied tables with long description without portrait' => [false, true, false, 'occupied-long'],
            'empty equipment tables' => [false, true, false, 'no-equipment'],
            'fits after font reduction' => [true, false, false, 'smaller'],
            'escaped description' => [true, false, false, 'html'],
            'landscape portrait' => [true, true, false, 'long', 160, 80],
            'portrait orientation' => [true, true, false, 'long', 80, 160],
        ];
    }

    #[DataProvider('sheetVariants')]
    public function test_sheet_layout_remains_aligned_and_visible(bool $withPortrait, bool $maximumText, bool $withExperience = false, string $description = 'normal', int $imageWidth = 1, int $imageHeight = 1): void
    {
        $data = require __DIR__.'/../Fixtures/rpg-sheet-layout.php';
        $data['character']['description'] = match ($description) {
            'empty' => '',
            'short' => 'Eine weit gereiste Figur.',
            'long', 'wide', 'occupied-long' => str_repeat('Technisch versiert und weit gereist. ', 500),
            'unbroken' => str_repeat('ÄÖÜWWWWß', 1000),
            'smaller' => str_repeat('W', 340),
            'html' => '<script>alert("Figur")</script> & <b>Beschreibung</b>',
            default => $data['character']['description'],
        };
        if ($withExperience) {
            $data['experience'] = ['received' => 80, 'spent' => 50, 'balance' => 30, 'entries' => []];
        }
        if ($description === 'inventory') {
            $data['equipment']['schema_version'] = 1;
            $data['equipment']['stock_confirmed_at'] = '2026-11-07T12:00:00+01:00';
            $data['equipment']['bucks_balance'] = 1000000000;
        }
        if ($withPortrait) {
            $image = imagecreatetruecolor($imageWidth, $imageHeight);
            ob_start();
            imagepng($image);
            $data['portrait'] = 'data:image/png;base64,'.base64_encode(ob_get_clean());
        }
        $sheet = (new RpgCharacterSheetPresenter)->present($data, (new RpgCharacterCombatCalculator)->calculate($data));
        if (in_array($description, ['occupied', 'occupied-long'], true)) {
            $sheet['weapons'] = array_fill(0, 7, $sheet['weapons'][1]);
            $sheet['armor'] = array_fill(0, 6, $sheet['armor'][0]);
        } elseif ($description === 'no-equipment') {
            $sheet['weapons'] = [];
            $sheet['armor'] = [];
        }
        if ($maximumText) {
            foreach (['advantages' => 310, 'disadvantages' => 310, 'trainings' => 100, 'professions' => 100] as $key => $length) {
                $sheet[$key] = $description === 'wide' ? str_repeat('W', $length) : mb_substr(str_repeat('Technisch versiert, wachsam und weit gereist, ', 20), 0, $length);
            }
            $sheet['equipment'] = mb_substr(str_repeat('Funkgerät mit Ersatzteilen, Fernglas, Seil und Proviant, ', 10), 0, 420);
            $sheet['ammunition'] = mb_substr(str_repeat('Bogen: 30 Pfeile, Armbrust: 20 Bolzen, ', 7), 0, 220);
            $sheet['specializations'] = mb_substr(str_repeat('Beruf: Soldat 3, Kunde: Geschichte 2, Unterhalten: Geschichten 2, ', 5), 0, 240);
        }
        $sheet = (new RpgCharacterSheetLayoutService)->prepare($sheet);
        if ($maximumText && ! in_array($description, ['wide', 'occupied', 'occupied-long'], true)) {
            foreach ($sheet['layout']['traits']['rows'] as $key => $row) {
                $this->assertFalse($row['truncated'], $key.' wurde trotz verfügbarer Leerzeilen gekürzt.');
            }
        }
        if ($description === 'smaller') {
            $this->assertLessThan(7.6, $sheet['layout']['description']['font_size']);
            $this->assertFalse($sheet['layout']['description']['truncated']);
        }
        $this->assertNotEmpty($sheet['ammunition']);
        $this->assertNotEmpty($sheet['notes']);
        $this->assertNotEmpty($sheet['combat']['situational_notes']);

        $boxes = [];
        $frameContentHeight = 0.0;
        $clipped = [];
        $renderedDescription = [];
        $pdf = new Dompdf;
        $pdf->setCallbacks([['event' => 'end_frame', 'f' => function (Frame $frame) use (&$boxes, &$frameContentHeight, &$clipped, &$renderedDescription): void {
            $node = $frame->get_node();
            if ($node instanceof DOMElement) {
                foreach (explode(' ', $node->getAttribute('class')) as $class) {
                    if (in_array($class, ['traits', 'trait-row', 'trait-text', 'attributes', 'value-list', 'skills', 'skill-list', 'portrait-frame', 'portrait-image', 'description', 'specializations', 'weapon-section', 'weapon-table', 'armor-section', 'armor-table', 'bottom-box', 'equipment-text', 'equipment-sub', 'notes', 'situational', 'rule-sources'], true)) {
                        $boxes[$class][] = $frame->get_border_box();
                    }
                    if ($class === 'portrait-frame') {
                        $frameContentHeight = $frame->get_content_box()['h'];
                    }
                    if ($class === 'prepared-line') {
                        $parent = $frame->get_parent();
                        $parentNode = $parent?->get_node();
                        if ($parentNode instanceof DOMElement && in_array('description', explode(' ', $parentNode->getAttribute('class')), true)) {
                            $lineBox = $frame->get_border_box();
                            $contentBox = $parent->get_content_box();
                            if ($lineBox['y'] + $lineBox['h'] > $contentBox['y'] + $contentBox['h'] + 0.01) {
                                $clipped[] = 'Beschreibung überschreitet den Inhaltsbereich: '.$node->nodeValue;
                            }
                        }
                    }
                }
            }
            if ($node->nodeName !== '#text' || trim(str_replace("\u{00a0}", ' ', $node->nodeValue)) === '') {
                return;
            }
            $textBox = $frame->get_border_box();
            for ($parent = $frame->get_parent(); $parent; $parent = $parent->get_parent()) {
                $parentNode = $parent->get_node();
                $classes = $parentNode instanceof DOMElement ? explode(' ', $parentNode->getAttribute('class')) : [];
                if (in_array('description', $classes, true)) {
                    $renderedDescription[] = $node->nodeValue;
                }
                if (array_intersect($classes, ['description', 'trait-text'])) {
                    $contentBox = $parent->get_content_box();
                    if ($textBox['x'] < $contentBox['x'] - 1 || $textBox['x'] + $textBox['w'] > $contentBox['x'] + $contentBox['w'] + 1) {
                        $clipped[] = 'Horizontal: '.trim($node->nodeValue);
                    }
                }
                if ($parent->get_style()->overflow !== 'hidden') {
                    continue;
                }
                $clipBox = $parent->get_padding_box();
                if ($textBox['y'] < $clipBox['y'] - 1 || $textBox['y'] + $textBox['h'] > $clipBox['y'] + $clipBox['h'] + 1) {
                    $clipped[] = trim($node->nodeValue);
                }
            }
        }]]);
        $pdf->setPaper('a4');
        $pdf->loadHtml(view('rpg.char-sheet', ['sheet' => $sheet])->render());
        $pdf->render();

        $this->assertSame($description === 'inventory' ? 2 : 1, $pdf->getCanvas()->get_page_count());
        $this->assertSame([], $clipped, 'Gerenderter Text wird durch einen übergeordneten Bereich abgeschnitten.');
        $footer = $boxes['rule-sources'][0];
        $frame = $boxes['portrait-frame'][0];
        $this->assertEqualsWithDelta($sheet['layout']['frame_content_height'], $frameContentHeight, 0.01, 'Dompdf muss die CSS-Höhe als Inhaltshöhe verwenden.');
        $this->assertEqualsWithDelta(2.9 * 72 / 25.4, $frame['h'] - $frameContentHeight, 0.01, 'Rahmen und Innenabstände müssen genau einmal zur Außenhöhe hinzukommen.');
        $this->assertEqualsWithDelta($boxes['traits'][0]['y'], $frame['y'], 0.05 * 72 / 25.4);
        foreach ($boxes['skill-list'] as $box) {
            $this->assertEqualsWithDelta($box['y'] + $box['h'], $frame['y'] + $frame['h'], 0.05 * 72 / 25.4, 'Rahmen und letzte Fertigkeitszeile sind nicht bündig.');
            $this->assertLessThan($boxes['specializations'][0]['y'], $box['y'] + $box['h'], 'Fertigkeiten überschneiden sich mit Spezialisierungen.');
        }
        foreach ($boxes['value-list'] as $box) {
            $this->assertLessThan($boxes['skills'][0]['y'], $box['y'] + $box['h'], 'Attribute überschneiden sich mit Fertigkeiten.');
        }
        foreach ($boxes['trait-text'] as $index => $box) {
            $row = $boxes['trait-row'][$index];
            $this->assertLessThanOrEqual($row['y'] + $row['h'] + 1, $box['y'] + $box['h']);
        }
        foreach (['weapon', 'armor'] as $section) {
            $table = $boxes[$section.'-table'][0];
            $area = $boxes[$section.'-section'][0];
            $this->assertLessThanOrEqual($area['y'] + $area['h'] + 1, $table['y'] + $table['h'], 'Auch leere Schreibzeilen müssen vollständig in den Tabellenbereich passen.');
        }
        if ($withPortrait) {
            $this->assertEqualsWithDelta(52, $boxes['portrait-image'][0]['h'] * 25.4 / 72, 0.1);
        } else {
            $this->assertArrayNotHasKey('portrait-image', $boxes);
            $this->assertLessThan(10, $boxes['description'][0]['y'] - $frame['y']);
        }
        if ($sheet['layout']['description']['truncated']) {
            $this->assertStringEndsWith('…', rtrim(implode('', $renderedDescription)));
        }
        if ($description === 'html') {
            $this->assertStringContainsString($data['character']['description'], implode('', $renderedDescription));
        }
        foreach (['bottom-box', 'equipment-text', 'equipment-sub', 'notes', 'situational'] as $class) {
            $this->assertArrayHasKey($class, $boxes);
            foreach ($boxes[$class] as $box) {
                $this->assertLessThan($footer['y'], $box['y'] + $box['h'], $class.' überschneidet sich mit dem Quellenhinweis.');
            }
        }
        $this->assertGreaterThanOrEqual(4 * 72 / 25.4 - 1, $frame['x']);
        $this->assertLessThanOrEqual($pdf->getCanvas()->get_width() - 4 * 72 / 25.4 + 1, $frame['x'] + $frame['w']);
        $this->assertLessThanOrEqual($pdf->getCanvas()->get_height() - 4 * 72 / 25.4 + 1, $footer['y'] + $footer['h']);
    }
}
