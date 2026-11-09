<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\RpgCharacterAdvancementService;
use App\Services\RpgCharacterCombatCalculator;
use App\Services\RpgCharacterSheetPresenter;
use App\Services\RpgProgressionHistory;
use DOMElement;
use Dompdf\Dompdf;
use Dompdf\Frame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RpgProgressionFixtures;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class, RpgProgressionFixtures::class);
dataset('RpgProgressionPdfTest.portraits', static fn (): array => [[false], [true]]);
test('pdf contains only approved changes and complete long history without clipping', function (bool $portrait): void {
    $this->progressionFixtures();
    $this->credit(500);
    $service = app(RpgCharacterAdvancementService::class);
    $reason = str_repeat('Ausführliche Begründung aus dem Abenteuer. ', 60).'ENDE-DER-BEGRUENDUNG';
    $request = $service->submit($this->player, $this->character->id, $this->advancementInput([$this->operation(extra: ['reason' => $reason]), $this->operation('advantage', 'Psychische Kraft', extra: ['target' => 'Telepathie']), $this->operation('skill', 'Telepathie'), $this->operation('advantage', 'Panzerung'), $this->operation('advantage', 'Panzerung')]));
    $service->decide($this->leader, $request->id, 'approved');
    $pending = $service->submit($this->player, $this->character->id, $this->advancementInput([$this->operation(extra: ['reason' => 'NOCH-NICHT-GENEHMIGT'])]));
    $history = app(RpgProgressionHistory::class)->forCharacter($this->character);
    expect($history['entries'])->toHaveCount(2)
        ->and($history['balance'])->toBe(422);
    $data = $this->character->fresh()->payload + ['experience' => $history];
    if ($portrait) {
        $data['portrait'] = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=';
    }
    $sheet = (new RpgCharacterSheetPresenter)->present($data, (new RpgCharacterCombatCalculator)->calculate($data));
    expect($sheet['combat']['defense']['damage_reduction'])->toBe(2)
        ->and($sheet['combat']['defense']['parade'])->toBe(3)
        ->and($sheet['psychic']['pep'])->toBe(1);
    $html = view('rpg.char-sheet', ['sheet' => $sheet])->render();
    expect($html)->toContain('ENDE-DER-BEGRUENDUNG')->not->toContain('NOCH-NICHT-GENEHMIGT')
        ->toContain('422')
        ->toContain('Telepathie: FW 1');
    $clipped = [];
    $renderedText = [];
    $summaryPage = null;
    $pdf = new Dompdf;
    $pdf->setCallbacks([['event' => 'end_frame', 'f' => function (Frame $frame) use (&$clipped, &$renderedText, &$summaryPage, $pdf): void {
        $node = $frame->get_node();
        if ($node->nodeName !== '#text' || trim(str_replace(' ', ' ', $node->nodeValue)) === '') {
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
    expect($pdf->getCanvas()->get_page_count())->toBeGreaterThanOrEqual(3)
        ->and($summaryPage)->toBe(1)
        ->and($clipped)->toBe([])
        ->and(implode('', $renderedText))->toContain('ENDE-DER-BEGRUENDUNG')
        ->and($pending->fresh()->status)->toBe('pending');
})->with('RpgProgressionPdfTest.portraits');
