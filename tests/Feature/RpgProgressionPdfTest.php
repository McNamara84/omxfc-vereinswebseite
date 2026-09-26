<?php

namespace Tests\Feature;

use App\Services\RpgCharacterAdvancementService;
use App\Services\RpgCharacterCombatCalculator;
use App\Services\RpgCharacterSheetPresenter;
use App\Services\RpgProgressionHistory;
use DOMElement;
use Dompdf\Dompdf;
use Dompdf\Frame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RpgProgressionFixtures;
use Tests\TestCase;

class RpgProgressionPdfTest extends TestCase
{
    use RefreshDatabase, RpgProgressionFixtures;

    public static function portraits(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('portraits')]
    public function test_pdf_contains_only_approved_changes_and_complete_long_history_without_clipping(bool $portrait): void
    {
        $this->progressionFixtures();
        $this->credit(500);
        $service = app(RpgCharacterAdvancementService::class);
        $reason = str_repeat('Ausführliche Begründung aus dem Abenteuer. ', 60).'ENDE-DER-BEGRUENDUNG';
        $request = $service->submit($this->player, $this->character->id, $this->advancementInput([
            $this->operation(extra: ['reason' => $reason]),
            $this->operation('advantage', 'Psychische Kraft', extra: ['target' => 'Telepathie']),
            $this->operation('skill', 'Telepathie'),
            $this->operation('advantage', 'Panzerung'),
            $this->operation('advantage', 'Panzerung'),
        ]));
        $service->decide($this->leader, $request->id, 'approved');
        $pending = $service->submit($this->player, $this->character->id, $this->advancementInput([$this->operation(extra: ['reason' => 'NOCH-NICHT-GENEHMIGT'])]));
        $history = app(RpgProgressionHistory::class)->forCharacter($this->character);
        $this->assertCount(2, $history['entries']);
        $this->assertSame(422, $history['balance']);
        $data = $this->character->fresh()->payload + ['experience' => $history];
        if ($portrait) {
            $data['portrait'] = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=';
        }
        $sheet = (new RpgCharacterSheetPresenter)->present($data, (new RpgCharacterCombatCalculator)->calculate($data));
        $this->assertSame(2, $sheet['combat']['defense']['damage_reduction']);
        $this->assertSame(3, $sheet['combat']['defense']['parade']);
        $this->assertSame(1, $sheet['psychic']['pep']);
        $html = view('rpg.char-sheet', ['sheet' => $sheet])->render();
        $this->assertStringContainsString('ENDE-DER-BEGRUENDUNG', $html);
        $this->assertStringNotContainsString('NOCH-NICHT-GENEHMIGT', $html);
        $this->assertStringContainsString('422', $html);
        $this->assertStringContainsString('Telepathie: FW 1', $html);
        $clipped = [];
        $renderedText = [];
        $summaryPage = null;
        $pdf = new Dompdf;
        $pdf->setCallbacks([['event' => 'end_frame', 'f' => function (Frame $frame) use (&$clipped, &$renderedText, &$summaryPage, $pdf): void {
            $node = $frame->get_node();
            if ($node->nodeName !== '#text' || trim(str_replace("\u{00a0}", ' ', $node->nodeValue)) === '') {
                return;
            }
            $renderedText[] = $node->nodeValue;
            if (str_contains($node->nodeValue, 'EP erhalten:')) {
                $summaryPage = $pdf->getCanvas()->get_page_number();
            }
            $box = $frame->get_border_box();
            if ($box['y'] + $box['h'] > 842) {
                $clipped[] = $node->nodeValue;
            }
            for ($parent = $frame->get_parent(); $parent; $parent = $parent->get_parent()) {
                if ($parent->get_node() instanceof DOMElement && $parent->get_style()->overflow === 'hidden') {
                    $clip = $parent->get_padding_box();
                    if ($box['y'] < $clip['y'] - 1 || $box['y'] + $box['h'] > $clip['y'] + $clip['h'] + 1) {
                        $clipped[] = $node->nodeValue;
                    }
                }
            }
        }]]);
        $pdf->setPaper('a4');
        $pdf->loadHtml($html);
        $pdf->render();
        $this->assertGreaterThanOrEqual(3, $pdf->getCanvas()->get_page_count());
        $this->assertSame(1, $summaryPage, 'Die EP-Zusammenfassung muss auf dem Hauptbogen bleiben.');
        $this->assertSame([], $clipped);
        $this->assertStringContainsString('ENDE-DER-BEGRUENDUNG', implode('', $renderedText));
        $this->assertSame('pending', $pending->fresh()->status);
    }
}
