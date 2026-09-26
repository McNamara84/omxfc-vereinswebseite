<?php

namespace Tests\Feature;

use App\Services\RpgCharacterCombatCalculator;
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
        return ['without portrait' => [false, false], 'with portrait' => [true, false], 'maximum text lengths' => [false, true], 'maximum text lengths with portrait' => [true, true]];
    }

    #[DataProvider('sheetVariants')]
    public function test_equipment_details_remain_visible_above_source_footer(bool $withPortrait, bool $maximumText): void
    {
        $data = require __DIR__.'/../Fixtures/rpg-sheet-layout.php';
        if ($withPortrait) {
            $data['portrait'] = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=';
        }
        $sheet = (new RpgCharacterSheetPresenter)->present($data, (new RpgCharacterCombatCalculator)->calculate($data));
        if ($maximumText) {
            $sheet['equipment'] = mb_substr(str_repeat('Funkgerät mit Ersatzteilen, Fernglas, Seil und Proviant, ', 10), 0, 420);
            $sheet['ammunition'] = mb_substr(str_repeat('Bogen: 30 Pfeile, Armbrust: 20 Bolzen, ', 7), 0, 220);
            $sheet['specializations'] = mb_substr(str_repeat('Beruf: Soldat 3, Kunde: Geschichte 2, Unterhalten: Geschichten 2, ', 5), 0, 240);
        }
        $this->assertNotEmpty($sheet['ammunition']);
        $this->assertNotEmpty($sheet['notes']);
        $this->assertNotEmpty($sheet['combat']['situational_notes']);

        $boxes = [];
        $clipped = [];
        $pdf = new Dompdf;
        $pdf->setCallbacks([['event' => 'end_frame', 'f' => function (Frame $frame) use (&$boxes, &$clipped): void {
            $node = $frame->get_node();
            if ($node instanceof DOMElement) {
                $class = $node->getAttribute('class');
                if (in_array($class, ['skill-list', 'specializations', 'bottom-box', 'equipment-text', 'equipment-sub', 'notes', 'situational', 'rule-sources'], true)) {
                    $boxes[$class][] = $frame->get_border_box();
                }
            }
            if ($node->nodeName !== '#text' || trim(str_replace("\u{00a0}", ' ', $node->nodeValue)) === '') {
                return;
            }
            $textBox = $frame->get_border_box();
            for ($parent = $frame->get_parent(); $parent; $parent = $parent->get_parent()) {
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

        $this->assertSame(1, $pdf->getCanvas()->get_page_count());
        $this->assertSame([], $clipped, 'Gerenderter Text wird durch einen übergeordneten Bereich abgeschnitten.');
        $footer = $boxes['rule-sources'][0];
        foreach ($boxes['skill-list'] as $box) {
            $this->assertLessThan($boxes['specializations'][0]['y'], $box['y'] + $box['h'], 'Fertigkeiten überschneiden sich mit Spezialisierungen.');
        }
        foreach (['bottom-box', 'equipment-text', 'equipment-sub', 'notes', 'situational'] as $class) {
            $this->assertArrayHasKey($class, $boxes);
            foreach ($boxes[$class] as $box) {
                $this->assertLessThan($footer['y'], $box['y'] + $box['h'], $class.' überschneidet sich mit dem Quellenhinweis.');
            }
        }
        $this->assertLessThanOrEqual($pdf->getCanvas()->get_height(), $footer['y'] + $footer['h']);
    }
}
