<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Services\RpgCharacterSheetService;
use Dompdf\Dompdf;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RpgCharEditorPdfTestFixtures::class);

beforeEach(function (): void {
    Cache::store('rpg_pdf_exports')->flush();
});

afterEach(function (): void {
    Cache::store('rpg_pdf_exports')->flush();
});

test('char sheet formats gender values for pdf view', function (): void {
    $html = view('rpg.char-sheet', ['character' => ['player_name' => 'Spieler Eins', 'character_name' => 'Foo Bar', 'gender' => 'maennlich', 'race' => 'Barbar', 'culture' => 'Landbewohner', 'description' => '', 'equipment' => ''], 'attributes' => [], 'skills' => [], 'advantages' => [], 'disadvantages' => [], 'portrait' => null])->render();
    expect($html)->toContain('Barbar · Landbewohner · männlich')
        ->toContain('Figurenstärke')
        ->toMatch('/Figurenstärke<\/th>\s*<td>3<\/td>/')->not->toContain('maennlich');
});

test('char sheet does not render trailing spaces for plain advantages', function (): void {
    $html = view('rpg.char-sheet', ['character' => ['player_name' => '', 'character_name' => '', 'gender' => 'maennlich', 'race' => 'Barbar', 'culture' => 'Landbewohner', 'description' => '', 'equipment' => ''], 'attributes' => [], 'skills' => [], 'advantages' => ['Zäh'], 'disadvantages' => [], 'advantage_details' => [], 'disadvantage_details' => [], 'advantage_counts' => [], 'portrait' => null])->render();
    expect($html)->toMatch('/class="prepared-line"[^>]*>Zäh<\/div>/');
    $this->assertDoesNotMatchRegularExpression('/class="prepared-line"[^>]*>Zäh\s+<\/div>/', $html);
});

test('failed pdf prepare redirect keeps editor input for recovery', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    $response = $this->from(route('rpg.char-editor'))->actingAs($member)->post(route('rpg.char-editor.pdf'), $this->validPdfPayload(['character_name' => 'Kept PDF Character', 'portrait_data_url' => 'data:image/png;base64,'.base64_encode('not an image')]));
    $response->assertRedirect(route('rpg.char-editor'))->assertSessionHasErrors('portrait_data_url')->assertSessionHasInput('character_name', 'Kept PDF Character')->assertSessionHasInput('race', 'Barbar')->assertSessionHasInput('clothing', 'kleidung-einfach');
});

test('pdf export post redirects to get viewer url', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload());
    $response->assertStatus(Response::HTTP_SEE_OTHER)->assertRedirect();
    $path = parse_url($response->headers->get('Location'), PHP_URL_PATH);
    expect($path)->toMatch('#^/rpg/char-editor/pdf/[0-9a-f-]{36}$#');
});

test('pdf export includes structured equipment and server ammunition', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => ($data['equipment']['clothing']['id'] ?? null) === 'kleidung-wanderer' && collect($data['equipment']['items'])->contains(fn ($item) => $item['id'] === 'bogen' && $item['quantity'] === 2) && collect($data['equipment']['ammunition'])->contains(fn ($ammo) => $ammo['source'] === 'Bogen' && $ammo['quantity'] === 60 && $ammo['unit'] === 'Pfeile') && ($data['equipment']['notes'] ?? null) === 'Bogen gewachst'))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['clothing' => 'kleidung-wanderer', 'equipment_items' => [['id' => 'bogen', 'quantity' => 2], ['id' => 'seil', 'quantity' => 1], ['id' => 'rucksack', 'quantity' => 1], ['id' => 'wasserschlauch', 'quantity' => 1], ['id' => 'wochenration', 'quantity' => 1]], 'equipment' => 'Bogen gewachst']));
    $response->assertOk();
});

test('pdf export requires valid clothing from equipment rules', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['clothing' => 'smoking']));
    $response->assertSessionHasErrors('clothing');
});

test('pdf export requires exactly six counted equipment items', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['equipment_items' => [['id' => 'messer-dolch', 'quantity' => 1], ['id' => 'seil', 'quantity' => 1], ['id' => 'rucksack', 'quantity' => 1], ['id' => 'wasserschlauch', 'quantity' => 1], ['id' => 'wochenration', 'quantity' => 1]]]));
    $response->assertSessionHasErrors(['equipment_items' => 'Zu Beginn müssen genau 6 gezählte Ausrüstungsgegenstände gewählt werden.']);
});

test('pdf export accepts unarmed attack without counting it as starting item', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(function (array $data): bool {
        $items = collect($data['equipment']['items'] ?? []);

        return $items->contains(fn ($item) => $item['id'] === 'faustschlag-tritt' && $item['quantity'] === 1 && $item['counts_toward_limit'] === false) && $items->contains(fn ($item) => $item['id'] === 'schlagring-stein' && $item['counts_toward_limit'] === true);
    }))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['equipment_items' => [['id' => 'faustschlag-tritt', 'quantity' => 1], ['id' => 'schlagring-stein', 'quantity' => 1], ['id' => 'messer-dolch', 'quantity' => 1], ['id' => 'seil', 'quantity' => 1], ['id' => 'rucksack', 'quantity' => 1], ['id' => 'wasserschlauch', 'quantity' => 1], ['id' => 'wochenration', 'quantity' => 1]]]));
    $response->assertOk();
});

test('pdf export does not count unarmed attack toward required item total', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['equipment_items' => [['id' => 'faustschlag-tritt', 'quantity' => 1], ['id' => 'messer-dolch', 'quantity' => 1], ['id' => 'seil', 'quantity' => 1], ['id' => 'rucksack', 'quantity' => 1], ['id' => 'wasserschlauch', 'quantity' => 1], ['id' => 'wochenration', 'quantity' => 1]]]));
    $response->assertSessionHasErrors('equipment_items');
});

test('pdf export rejects unknown equipment ids', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['equipment_items' => [['id' => 'messer-dolch', 'quantity' => 1], ['id' => 'seil', 'quantity' => 1], ['id' => 'rucksack', 'quantity' => 1], ['id' => 'wasserschlauch', 'quantity' => 1], ['id' => 'wochenration', 'quantity' => 1], ['id' => 'laserdrache', 'quantity' => 1]]]));
    $response->assertSessionHasErrors('equipment_items');
});

test('pdf export rejects high tech equipment without advantage', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['equipment_items' => [['id' => 'funkgeraet', 'quantity' => 1], ['id' => 'messer-dolch', 'quantity' => 1], ['id' => 'seil', 'quantity' => 1], ['id' => 'rucksack', 'quantity' => 1], ['id' => 'wasserschlauch', 'quantity' => 1], ['id' => 'wochenration', 'quantity' => 1]]]));
    $response->assertSessionHasErrors('equipment_items');
});

test('pdf export limits high tech equipment to four with advantage', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['advantages' => ['Zäh', 'High-Tech-Ausrüstung'], 'disadvantages' => ['Auffällig'], 'equipment_items' => [['id' => 'funkgeraet', 'quantity' => 5], ['id' => 'seil', 'quantity' => 1]]]));
    $response->assertSessionHasErrors('equipment_items');
});

test('pdf export get route can be opened repeatedly by pdf viewers', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload());
    $path = parse_url($response->headers->get('Location'), PHP_URL_PATH);
    Pdf::shouldReceive('view')->twice()->with('rpg.char-sheet', \Mockery::type('array'))->andReturnUsing(fn () => new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $this->actingAs($member)->get($path)->assertOk();
    $this->actingAs($member)->get($path)->assertOk();
});

test('real pdf exports use a writable private font cache for repeated rendering', function (): void {
    $fontPath = storage_path('framework/testing/pdf-fonts-'.Str::uuid());
    config(['laravel-pdf.dompdf.font_dir' => $fontPath, 'laravel-pdf.dompdf.font_cache' => $fontPath]);
    $member = $this->addAgRollenspielMembership($this->createMember());
    try {
        $prepared = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['character_name' => 'Ärztin Groß', 'description' => 'Überleben in der Straße: äöü ÄÖÜ ß, 12.345,67.']));
        $path = parse_url($prepared->headers->get('Location'), PHP_URL_PATH);
        for ($render = 0; $render < 2; $render++) {
            $response = $this->actingAs($member)->get($path)->assertOk();
            $response->assertHeader('content-type', 'application/pdf');
            expect($response->getContent())->toStartWith('%PDF-')
                ->and(strlen($response->getContent()))->toBeGreaterThan(1000);
        }
        expect($fontPath)->toBeDirectory()
            ->and(is_writable($fontPath))->toBeTrue()
            ->and(config('laravel-pdf.dompdf.is_remote_enabled'))->toBeFalse();
    } finally {
        File::deleteDirectory($fontPath);
    }
});

test('editor pdf fits the description without shortening the cached source', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    $description = str_repeat('Eine ausführlich beschriebene Figur. ', 400).'ENDE';
    $prepared = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['description' => $description]));
    $path = parse_url($prepared->headers->get('Location'), PHP_URL_PATH);
    $cacheKey = 'rpg-char-editor-pdf:'.basename($path);
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn (array $data): bool => $data['character']['description'] === $description && $data['sheet']['description'] === $description && $data['sheet']['layout']['description']['truncated'] && $data['sheet']['layout']['description']['font_size'] === 6.5))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $this->get($path)->assertOk();
    expect(Cache::store('rpg_pdf_exports')->get($cacheKey)['data']['character']['description'])->toBe($description);
});

test('pdf export replaces previous cached payload when new export is created', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    $firstResponse = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['character_name' => 'Erster Charakter']));
    $firstToken = basename(parse_url($firstResponse->headers->get('Location'), PHP_URL_PATH));
    $firstCacheKey = 'rpg-char-editor-pdf:'.$firstToken;
    $firstSessionPayloadKey = 'rpg-char-editor-pdf.'.$firstToken;
    $firstResponse->assertRedirect()->assertSessionMissing($firstSessionPayloadKey)->assertSessionHas('rpg-char-editor-pdf.active-token', $firstToken);
    expect(Cache::store('rpg_pdf_exports')->has($firstCacheKey))->toBeTrue();
    $secondResponse = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['character_name' => 'Zweiter Charakter']));
    $secondToken = basename(parse_url($secondResponse->headers->get('Location'), PHP_URL_PATH));
    $secondCacheKey = 'rpg-char-editor-pdf:'.$secondToken;
    $secondSessionPayloadKey = 'rpg-char-editor-pdf.'.$secondToken;
    $secondResponse->assertRedirect()->assertSessionMissing($firstSessionPayloadKey)->assertSessionMissing($secondSessionPayloadKey)->assertSessionHas('rpg-char-editor-pdf.active-token', $secondToken);
    expect(Cache::store('rpg_pdf_exports')->has($firstCacheKey))->toBeFalse()
        ->and(Cache::store('rpg_pdf_exports')->has($secondCacheKey))->toBeTrue();
    Pdf::shouldReceive('view')->never();
    $this->actingAs($member)->get('/rpg/char-editor/pdf/'.$firstToken)->assertNotFound();
});

test('pdf export get route is scoped to exporting user', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    $otherMember = $this->addAgRollenspielMembership($this->createMember());
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload());
    $path = parse_url($response->headers->get('Location'), PHP_URL_PATH);
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::type('array'))->andReturnUsing(fn () => new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $this->actingAs($otherMember)->get($path)->assertNotFound();
    $this->actingAs($member)->get($path)->assertOk();
});

test('pdf export get route rejects unknown tokens', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $this->actingAs($member)->get('/rpg/char-editor/pdf/00000000-0000-4000-8000-000000000000')->assertNotFound();
});

test('pdf export get route rejects expired tokens', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    $token = '00000000-0000-4000-8000-000000000001';
    Pdf::shouldReceive('view')->never();
    $cacheKey = 'rpg-char-editor-pdf:'.$token;
    Cache::store('rpg_pdf_exports')->put($cacheKey, ['user_id' => (string) $member->getAuthIdentifier(), 'expires_at' => now()->subMinute()->getTimestamp(), 'data' => $this->validPdfPayload()], now()->addMinute());
    $this->withSession(['rpg-char-editor-pdf.active-token' => $token])->actingAs($member)->get('/rpg/char-editor/pdf/'.$token)->assertNotFound()->assertSessionMissing('rpg-char-editor-pdf.active-token');
    expect(Cache::store('rpg_pdf_exports')->has($cacheKey))->toBeFalse();
});

test('pdf view receives normalized browser payload for disabled editor fields', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => $data['character'] === ['player_name' => 'Holger', 'character_name' => 'Holli', 'gender' => 'weiblich', 'race' => 'Barbar', 'culture' => 'Landbewohner', 'description' => 'Beschreibung aus dem Editor', 'equipment' => 'Seil und Messer'] && $data['attributes'] === ['st' => '2', 'ge' => '1', 'ro' => '0', 'wi' => '0', 'wa' => '0', 'in' => '0', 'au' => '0'] && $data['skills'] === [['name' => 'Nahkampf', 'value' => '4'], ['name' => 'Überleben', 'value' => '1'], ['name' => 'Intuition', 'value' => '1'], ['name' => 'Beruf: Landwirt', 'value' => '2'], ['name' => 'Kunde: Wetter', 'value' => '1'], ['name' => 'Diebeskunst', 'value' => '4'], ['name' => 'Fernkampf', 'value' => '4'], ['name' => 'Handeln', 'value' => '4'], ['name' => 'Fahren', 'value' => '4'], ['name' => 'Heiler', 'value' => '1']] && $data['advantages'] === ['Zäh', 'Anführer'] && $data['disadvantages'] === ['Abergläubisch'] && $data['portrait'] === null))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', ['_token' => 'ignored by payload whitelist', 'figurenstaerke' => 3, 'player_name' => 'Holger', 'character_name' => 'Holli', 'gender' => 'weiblich', 'race' => 'Barbar', 'culture' => 'Landbewohner', 'description' => 'Beschreibung aus dem Editor', 'attributes' => ['st' => '2', 'ge' => '1', 'unknown' => '99'], 'attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0], 'barbar_attribute_bonus' => 'st', 'skills' => [['value' => '4'], ['name' => 'Nahkampf', 'value' => '4'], ['name' => 'Überleben', 'value' => '1'], ['name' => 'Intuition', 'value' => '1'], ['name' => 'Beruf: Landwirt', 'value' => '2'], ['name' => 'Kunde: Wetter', 'value' => '1'], ['name' => 'Diebeskunst', 'value' => '4'], ['name' => 'Fernkampf', 'value' => '4'], ['name' => 'Handeln', 'value' => '4'], ['name' => 'Fahren', 'value' => '4'], ['name' => 'Heiler', 'value' => '1'], ['name' => '', 'value' => '4']], 'advantages' => ['Zaeh', 'Zaeh', 'Anfuehrer', ''], 'advantage_effects' => [['name' => 'Anführer', 'target' => '', 'justification' => '']], 'disadvantages' => ['Aberglaeubisch', ''], 'disadvantage_details' => ['Aberglaeubisch' => 'Salz, Omen, dreimal klopfen'], 'languages' => [], 'clothing' => 'kleidung-einfach', 'equipment_items' => [['id' => 'messer-dolch', 'quantity' => 1], ['id' => 'seil', 'quantity' => 1], ['id' => 'rucksack', 'quantity' => 1], ['id' => 'wasserschlauch', 'quantity' => 1], ['id' => 'wochenration', 'quantity' => 1], ['id' => 'bogen', 'quantity' => 1]], 'equipment' => 'Seil und Messer', 'unexpected' => 'wird nicht an die PDF-View gereicht']);
    $response->assertSessionHasNoErrors();
    $response->assertOk();
});

test('pdf export rejects missing or invalid gender for all cultures', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $payloadWithoutGender = $this->validPdfPayload();
    unset($payloadWithoutGender['gender']);
    foreach ([$payloadWithoutGender, $this->validPdfPayload(['gender' => '']), $this->validPdfPayload(['gender' => 'unbekannt'])] as $payload) {
        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $payload);
        $response->assertSessionHasErrors('gender');
    }
});

test('pdf export rejects missing or invalid race and culture', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $payloadWithoutRace = $this->validPdfPayload();
    unset($payloadWithoutRace['race']);
    foreach ([$payloadWithoutRace, $this->validPdfPayload(['race' => '']), $this->validPdfPayload(['race' => 'Unbekannte Rasse'])] as $payload) {
        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $payload);
        $response->assertSessionHasErrors('race');
    }
    $payloadWithoutCulture = $this->validPdfPayload();
    unset($payloadWithoutCulture['culture']);
    foreach ([$payloadWithoutCulture, $this->validPdfPayload(['culture' => '']), $this->validPdfPayload(['culture' => 'Atlantisbewohner'])] as $payload) {
        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $payload);
        $response->assertSessionHasErrors('culture');
    }
});

test('pdf export accepts negative race modifier floor from rulebook', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => ($data['attributes']['au'] ?? null) === '-2'))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $payload = $this->validPdfPayload(['race' => 'Guul', 'culture' => 'Stadtbewohner', 'attributes' => ['au' => -2], 'advantages' => ['Zäh', 'Natürliche Waffen'], 'disadvantages' => ['Primitiv', 'Gejagt']]);
    $payload['skills'] = [['name' => 'Heimlichkeit', 'value' => 2], ['name' => 'Intuition', 'value' => 1], ['name' => 'Nahkampf', 'value' => 1], ['name' => 'Beruf', 'value' => 1], ['name' => 'Kunde', 'value' => 1], ['name' => 'Unterhalten', 'value' => 1]];
    $payload['skills'] = $this->completeSkillBudgetForPayload($payload);
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $payload);
    $response->assertOk();
});

test('pdf export rejects barbar attribute above absolute max', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['barbar_attribute_bonus' => 'st', 'attributes' => ['st' => 3, 'ge' => 0]]));
    $response->assertSessionHasErrors('attributes');
});

test('pdf export rejects unmodified attribute above base max', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['barbar_attribute_bonus' => 'st', 'attributes' => ['st' => 2, 'ge' => 2]]));
    $response->assertSessionHasErrors('attributes');
});

test('pdf export rejects attribute point budget overflow', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['barbar_attribute_bonus' => 'st', 'attributes' => ['st' => 2, 'ge' => 1, 'ro' => 1]]));
    $response->assertSessionHasErrors('attributes');
});

test('pdf export derives guul natural weapons without a separate skill', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(function ($data) {
        $skills = collect($data['skills'])->keyBy('name');

        return $data['character']['race'] === 'Guul' && $data['character']['culture'] === 'Stadtbewohner' && ($data['attributes']['au'] ?? null) === '-1' && ! $skills->has('Natürliche Waffen') && (($skills['Nahkampf'] ?? [])['value'] ?? null) === '2' && in_array('Natürliche Waffen', $data['advantages'], true) && in_array('Primitiv', $data['disadvantages'], true) && in_array('Gejagt', $data['disadvantages'], true);
    }))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $payload = $this->validPdfPayload(['race' => 'Guul', 'culture' => 'Stadtbewohner', 'attributes' => ['au' => -1], 'advantages' => ['Zäh', 'Natürliche Waffen'], 'disadvantages' => ['Primitiv', 'Gejagt']]);
    $payload['skills'] = [['name' => 'Heimlichkeit', 'value' => 2], ['name' => 'Intuition', 'value' => 1], ['name' => 'Nahkampf', 'value' => 2], ['name' => 'Beruf', 'value' => 1], ['name' => 'Kunde', 'value' => 1], ['name' => 'Unterhalten', 'value' => 1]];
    $payload['skills'] = $this->completeSkillBudgetForPayload($payload);
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $payload);
    $response->assertOk();
});

test('pdf export adds missing automatic guul advantage server side', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => in_array('Natürliche Waffen', $data['advantages'], true)))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $payload = $this->validPdfPayload(['race' => 'Guul', 'culture' => 'Stadtbewohner', 'attributes' => ['au' => -1], 'advantages' => ['Zäh'], 'disadvantages' => ['Primitiv', 'Gejagt']]);
    $payload['skills'] = [['name' => 'Heimlichkeit', 'value' => 2], ['name' => 'Intuition', 'value' => 1], ['name' => 'Nahkampf', 'value' => 1], ['name' => 'Beruf', 'value' => 1], ['name' => 'Kunde', 'value' => 1], ['name' => 'Unterhalten', 'value' => 1]];
    $payload['skills'] = $this->completeSkillBudgetForPayload($payload);
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $payload);
    $response->assertOk();
});

test('pdf export accepts creation adjustment that cancels guul modifier', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => ($data['attributes']['au'] ?? null) === '0'))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $payload = $this->validPdfPayload(['race' => 'Guul', 'culture' => 'Stadtbewohner', 'attributes' => [], 'advantages' => ['Zäh', 'Natürliche Waffen'], 'disadvantages' => ['Primitiv', 'Gejagt']]);
    $payload['skills'] = [['name' => 'Heimlichkeit', 'value' => 2], ['name' => 'Intuition', 'value' => 1], ['name' => 'Nahkampf', 'value' => 1], ['name' => 'Beruf', 'value' => 1], ['name' => 'Kunde', 'value' => 1], ['name' => 'Unterhalten', 'value' => 1]];
    $payload['skills'] = $this->completeSkillBudgetForPayload($payload);
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $payload);
    $response->assertOk();
});

test('pdf export rejects guul with invalid au modifier label', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $payload = $this->validPdfPayload(['race' => 'Guul', 'culture' => 'Stadtbewohner', 'attributes' => ['au' => 1], 'advantages' => ['Zäh', 'Natürliche Waffen'], 'disadvantages' => ['Primitiv', 'Gejagt']]);
    $payload['skills'] = [['name' => 'Heimlichkeit', 'value' => 2], ['name' => 'Intuition', 'value' => 1], ['name' => 'Nahkampf', 'value' => 1], ['name' => 'Beruf', 'value' => 1], ['name' => 'Kunde', 'value' => 1], ['name' => 'Unterhalten', 'value' => 1]];
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $payload);
    $response->assertSessionHasErrors('attributes');
});

test('pdf export accepts creation adjustment that cancels nosfera ge bonus', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => ($data['attributes']['ge'] ?? null) === '0'))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $payload = $this->validPdfPayload(['race' => 'Nosfera', 'culture' => 'Stadtbewohner', 'attributes' => ['au' => -1], 'advantages' => ['Zäh', 'Nachtsicht'], 'disadvantages' => ['Blutdurst', 'Lichtscheu', 'Gejagt']]);
    $payload['attributes'] = ['st' => 1, 'ge' => 0, 'ro' => 1, 'au' => -1];
    $payload['attribute_adjustments'] = $this->attributeAdjustmentsForPayload($payload);
    $payload['skills'] = [['name' => 'Intuition', 'value' => 2], ['name' => 'Heimlichkeit', 'value' => 2], ['name' => 'Beruf', 'value' => 1], ['name' => 'Kunde', 'value' => 1], ['name' => 'Unterhalten', 'value' => 1]];
    $payload['skills'] = $this->completeSkillBudgetForPayload($payload);
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $payload);
    $response->assertOk();
});

test('pdf export adds missing automatic nosfera advantage server side', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => in_array('Nachtsicht', $data['advantages'], true)))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $payload = $this->validPdfPayload(['race' => 'Nosfera', 'culture' => 'Stadtbewohner', 'attributes' => ['ge' => 1, 'au' => -1], 'advantages' => ['Zäh'], 'disadvantages' => ['Blutdurst', 'Lichtscheu', 'Gejagt']]);
    $payload['skills'] = [['name' => 'Intuition', 'value' => 2], ['name' => 'Heimlichkeit', 'value' => 2], ['name' => 'Beruf', 'value' => 1], ['name' => 'Kunde', 'value' => 1], ['name' => 'Unterhalten', 'value' => 1]];
    $payload['skills'] = $this->completeSkillBudgetForPayload($payload);
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $payload);
    $response->assertOk();
});

test('pdf export rejects techno without bildung race bonus', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $payload = $this->validPdfPayload(['race' => 'Techno', 'culture' => 'Bunkermensch', 'attributes' => ['st' => -1, 'ro' => -1, 'in' => 1], 'advantages' => ['Zäh', 'High-Tech-Ausrüstung'], 'disadvantages' => ['Tödliche Immunschwäche']]);
    $payload['skills'] = [['name' => 'Bildung', 'value' => 2], ['name' => 'Nahkampf', 'value' => 1], ['name' => 'Fahren', 'value' => 2], ['name' => 'Feuerwaffen', 'value' => 2], ['name' => 'Heiler', 'value' => 2], ['name' => 'Pilot', 'value' => 2], ['name' => 'Techniker', 'value' => 2], ['name' => 'Wissenschaftler', 'value' => 2]];
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $payload);
    $response->assertSessionHasErrors('skills');
});

test('pdf export accepts hydrit with meeresbewohner culture', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => $data['character']['race'] === 'Hydrit' && $data['character']['culture'] === 'Meeresbewohner'))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['race' => 'Hydrit', 'culture' => 'Meeresbewohner', 'skills' => [['name' => 'Athletik', 'value' => 2], ['name' => 'Bildung', 'value' => 1], ['name' => 'Nahkampf', 'value' => 1], ['name' => 'Beruf: Farmer', 'value' => 1], ['name' => 'Wissenschaftler', 'value' => 1]], 'advantages' => ['Zäh', 'Kiemen', 'Natürliche Waffen'], 'disadvantages' => ['Anfälligkeit gegen Wahnsinn']]));
    $response->assertOk();
});

test('pdf export rejects hydrit with other culture', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['race' => 'Hydrit', 'culture' => 'Landbewohner']));
    $response->assertSessionHasErrors('culture');
});

test('pdf export rejects meeresbewohner for non hydrit', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['race' => 'Barbar', 'culture' => 'Meeresbewohner']));
    $response->assertSessionHasErrors('culture');
});

test('pdf export rejects missing culture skill requirements', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $cases = ['Landbewohner ohne Wetterkunde' => $this->validPdfPayload(['skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1], ['name' => 'Beruf: Viehzüchter', 'value' => 2]]]), 'Stadtbewohner ohne Wahlbonus' => $this->validPdfPayload(['culture' => 'Stadtbewohner', 'skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1], ['name' => 'Beruf', 'value' => 1], ['name' => 'Kunde', 'value' => 1]]]), 'Meeresbewohner ohne Berufsbonus' => $this->validPdfPayload(['race' => 'Hydrit', 'culture' => 'Meeresbewohner', 'skills' => [['name' => 'Athletik', 'value' => 2], ['name' => 'Bildung', 'value' => 1], ['name' => 'Wissenschaftler', 'value' => 1]], 'advantages' => ['Zäh', 'Kiemen', 'Natürliche Waffen'], 'disadvantages' => ['Anfälligkeit gegen Wahnsinn']]), 'Bunkermensch ohne Nahkampf' => $this->validPdfPayload(['race' => 'Techno', 'culture' => 'Bunkermensch', 'attributes' => ['st' => -1, 'ro' => -1, 'in' => 1], 'skills' => [['name' => 'Bildung', 'value' => 3], ['name' => 'Fahren', 'value' => 2], ['name' => 'Feuerwaffen', 'value' => 2], ['name' => 'Heiler', 'value' => 2], ['name' => 'Pilot', 'value' => 2], ['name' => 'Techniker', 'value' => 2], ['name' => 'Wissenschaftler', 'value' => 2]], 'advantages' => ['Zäh', 'High-Tech-Ausrüstung'], 'disadvantages' => ['Tödliche Immunschwäche']]), 'Mensch des 21. Jahrhunderts ohne zweiten Wissensbonus' => $this->validPdfPayload(['race' => 'Präkristofluu', 'culture' => 'Mensch des 21. Jahrhunderts', 'skills' => [['name' => 'Beruf', 'value' => 3], ['name' => 'Bildung', 'value' => 1], ['name' => 'Fahren', 'value' => 2], ['name' => 'Feuerwaffen', 'value' => 2]], 'advantages' => ['Zäh', 'High-Tech-Ausrüstung']]), 'Nomade ohne Bewegungsbonus' => $this->validPdfPayload(['culture' => 'Nomade', 'skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1]]]), 'Ruinenbewohner ohne Diebeskunst' => $this->validPdfPayload(['culture' => 'Ruinenbewohner', 'skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1], ['name' => 'Heimlichkeit', 'value' => 1], ['name' => 'Fernkampf', 'value' => 1]]]), 'Untergrundbewohner ohne Bergmann' => $this->validPdfPayload(['culture' => 'Untergrundbewohner', 'skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1], ['name' => 'Athletik', 'value' => 1]]]), 'Volk der 13 Inseln ohne Beruf' => $this->validPdfPayload(['culture' => 'Volk der 13 Inseln', 'skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1], ['name' => 'Athletik', 'value' => 1]]]), 'Disuuslachter ohne Seemann' => $this->validPdfPayload(['culture' => 'Disuuslachter (Nordmann)', 'skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1]]])];
    foreach ($cases as $payload) {
        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $payload);
        $response->assertSessionHasErrors('skills');
    }
});

test('pdf export accepts techno with bunkermensch culture', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => $data['character']['race'] === 'Techno' && $data['character']['culture'] === 'Bunkermensch'))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['race' => 'Techno', 'culture' => 'Bunkermensch', 'attributes' => ['st' => -1, 'ro' => -1, 'in' => 1], 'skills' => [['name' => 'Bildung', 'value' => 3], ['name' => 'Nahkampf', 'value' => 1], ['name' => 'Fahren', 'value' => 2], ['name' => 'Feuerwaffen', 'value' => 2], ['name' => 'Heiler', 'value' => 2], ['name' => 'Pilot', 'value' => 2], ['name' => 'Techniker', 'value' => 2], ['name' => 'Wissenschaftler', 'value' => 2]], 'advantages' => ['Zäh', 'High-Tech-Ausrüstung'], 'disadvantages' => ['Tödliche Immunschwäche']]));
    $response->assertOk();
});

test('pdf export rejects techno with other culture', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['race' => 'Techno', 'culture' => 'Stadtbewohner']));
    $response->assertSessionHasErrors('culture');
});

test('pdf export rejects bunkermensch for non techno', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['race' => 'Barbar', 'culture' => 'Bunkermensch']));
    $response->assertSessionHasErrors('culture');
});

test('pdf export accepts praekristofluu with mensch des 21 jahrhunderts culture', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => $data['character']['race'] === 'Präkristofluu' && $data['character']['culture'] === 'Mensch des 21. Jahrhunderts'))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['race' => 'Präkristofluu', 'culture' => 'Mensch des 21. Jahrhunderts', 'skills' => [['name' => 'Beruf', 'value' => 3], ['name' => 'Bildung', 'value' => 2], ['name' => 'Fahren', 'value' => 2], ['name' => 'Feuerwaffen', 'value' => 2], ['name' => 'Pilot', 'value' => 2], ['name' => 'Techniker', 'value' => 2], ['name' => 'Wissenschaftler', 'value' => 2]], 'advantages' => ['Zäh', 'High-Tech-Ausrüstung']]));
    $response->assertOk();
});

test('pdf export accepts praekristofluu request pool with mensch 21 bonus choices', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(function ($data) {
        $skills = collect($data['skills'])->keyBy('name');

        return $data['character']['race'] === 'Präkristofluu' && $data['character']['culture'] === 'Mensch des 21. Jahrhunderts' && (int) ($skills->get('Bildung')['value'] ?? 0) === 3 && (int) ($skills->get('Techniker')['value'] ?? 0) === 3 && (int) ($skills->get('Wissenschaftler')['value'] ?? 0) === 3;
    }))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['race' => 'Präkristofluu', 'culture' => 'Mensch des 21. Jahrhunderts', 'skills' => [['name' => 'Beruf', 'value' => 3], ['name' => 'Bildung', 'value' => 3], ['name' => 'Fahren', 'value' => 2], ['name' => 'Feuerwaffen', 'value' => 2], ['name' => 'Pilot', 'value' => 2], ['name' => 'Techniker', 'value' => 3], ['name' => 'Wissenschaftler', 'value' => 3]], 'praekristofluu_skill_points' => ['Bildung' => 2, 'Fahren' => 2, 'Feuerwaffen' => 2, 'Pilot' => 2, 'Techniker' => 2, 'Wissenschaftler' => 2], 'mensch_21_first_bonus_skill' => 'Techniker', 'mensch_21_second_bonus_skill' => 'Wissenschaftler', 'advantages' => ['Zäh', 'High-Tech-Ausrüstung']]));
    $response->assertOk();
});

test('pdf export rejects invalid mensch 21 bonus choice inputs', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $validPraekristofluuPayload = ['race' => 'Präkristofluu', 'culture' => 'Mensch des 21. Jahrhunderts', 'skills' => [['name' => 'Beruf', 'value' => 3], ['name' => 'Bildung', 'value' => 3], ['name' => 'Fahren', 'value' => 2], ['name' => 'Feuerwaffen', 'value' => 2], ['name' => 'Pilot', 'value' => 2], ['name' => 'Techniker', 'value' => 3], ['name' => 'Wissenschaftler', 'value' => 3]], 'praekristofluu_skill_points' => ['Bildung' => 2, 'Fahren' => 2, 'Feuerwaffen' => 2, 'Pilot' => 2, 'Techniker' => 2, 'Wissenschaftler' => 2], 'advantages' => ['Zäh', 'High-Tech-Ausrüstung']];
    foreach (['duplicate bonus choices' => ['mensch_21_first_bonus_skill' => 'Techniker', 'mensch_21_second_bonus_skill' => 'Techniker'], 'unsupported bonus choice' => ['mensch_21_first_bonus_skill' => 'Heiler', 'mensch_21_second_bonus_skill' => 'Wissenschaftler']] as $overrides) {
        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(array_replace($validPraekristofluuPayload, $overrides)));
        $response->assertSessionHasErrors('skills');
    }
});

test('pdf export rejects praekristofluu with other culture', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['race' => 'Präkristofluu', 'culture' => 'Landbewohner']));
    $response->assertSessionHasErrors('culture');
});

test('pdf export rejects mensch des 21 jahrhunderts for non praekristofluu', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['race' => 'Barbar', 'culture' => 'Mensch des 21. Jahrhunderts']));
    $response->assertSessionHasErrors('culture');
});

test('pdf export accepts nosfera with general culture', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(function ($data) {
        $skills = collect($data['skills'])->keyBy('name');

        return $data['character']['race'] === 'Nosfera' && $data['character']['culture'] === 'Stadtbewohner' && ($data['attributes']['ge'] ?? null) === '1' && ($data['attributes']['au'] ?? null) === '-1' && $skills->has('Intuition') && $skills->has('Heimlichkeit') && in_array('Nachtsicht', $data['advantages'], true) && ! in_array('Psychisches Reservoir', $data['advantages'], true) && in_array('Blutdurst', $data['disadvantages'], true) && in_array('Lichtscheu', $data['disadvantages'], true) && in_array('Gejagt', $data['disadvantages'], true);
    }))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['race' => 'Nosfera', 'culture' => 'Stadtbewohner', 'attributes' => ['ge' => 1, 'au' => -1], 'skills' => [['name' => 'Intuition', 'value' => 2], ['name' => 'Heimlichkeit', 'value' => 2], ['name' => 'Beruf', 'value' => 1], ['name' => 'Kunde', 'value' => 1], ['name' => 'Unterhalten', 'value' => 1]], 'advantages' => ['Zäh', 'Nachtsicht'], 'disadvantages' => ['Blutdurst', 'Lichtscheu', 'Gejagt']]));
    $response->assertOk();
});

test('pdf export accepts taratze with general culture', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(function ($data) {
        $skills = collect($data['skills'])->keyBy('name');
        $skillValue = fn (string $skillName) => ($skills[$skillName] ?? [])['value'] ?? null;

        return $data['character']['race'] === 'Taratze' && $data['character']['culture'] === 'Stadtbewohner' && ($data['attributes']['st'] ?? null) === '1' && ($data['attributes']['wa'] ?? null) === '1' && ($data['attributes']['in'] ?? null) === '-1' && ($data['attributes']['au'] ?? null) === '-1' && $skillValue('Intuition') === '2' && $skillValue('Heimlichkeit') === '1' && $skillValue('Überleben') === '1' && in_array('Auffällig', $data['disadvantages'], true) && in_array('Primitiv', $data['disadvantages'], true) && in_array('Gejagt', $data['disadvantages'], true);
    }))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['race' => 'Taratze', 'culture' => 'Stadtbewohner', 'attributes' => ['st' => 1, 'wa' => 1, 'in' => -1, 'au' => -1], 'skills' => [['name' => 'Intuition', 'value' => 2], ['name' => 'Heimlichkeit', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Beruf', 'value' => 1], ['name' => 'Kunde', 'value' => 1], ['name' => 'Unterhalten', 'value' => 1]], 'advantages' => ['Zäh'], 'disadvantages' => ['Auffällig', 'Primitiv', 'Gejagt']]));
    $response->assertOk();
});

test('pdf export accepts wulfane with general culture', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(function ($data) {
        $skills = collect($data['skills'])->keyBy('name');
        $skillValue = fn (string $skillName) => ($skills[$skillName] ?? [])['value'] ?? null;

        return $data['character']['race'] === 'Wulfane' && $data['character']['culture'] === 'Landbewohner' && ($data['attributes']['ro'] ?? null) === '1' && ($data['attributes']['au'] ?? null) === '-1' && $skillValue('Intuition') === '1' && $skillValue('Nahkampf') === '1' && in_array('Ehrenkodex', $data['disadvantages'], true);
    }))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['race' => 'Wulfane', 'culture' => 'Landbewohner', 'attributes' => ['ro' => 1, 'au' => -1], 'skills' => [['name' => 'Intuition', 'value' => 1], ['name' => 'Nahkampf', 'value' => 1], ['name' => 'Beruf: Landwirt', 'value' => 2], ['name' => 'Kunde: Wetter', 'value' => 1]], 'advantages' => ['Zäh'], 'disadvantages' => ['Ehrenkodex']]));
    $response->assertOk();
});

test('pdf export accepts nomade culture', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => $data['character']['race'] === 'Barbar' && $data['character']['culture'] === 'Nomade'))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['culture' => 'Nomade', 'skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Reiten', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1]]]));
    $response->assertOk();
});

test('pdf export accepts ruinenbewohner culture', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(function ($data) {
        $skills = collect($data['skills'])->keyBy('name');

        return $data['character']['race'] === 'Barbar' && $data['character']['culture'] === 'Ruinenbewohner' && $skills->has('Diebeskunst') && $skills->has('Heimlichkeit') && $skills->has('Fernkampf') && ! $skills->has('Fernwaffen');
    }))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['culture' => 'Ruinenbewohner', 'skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Diebeskunst', 'value' => 1], ['name' => 'Heimlichkeit', 'value' => 1], ['name' => 'Fernkampf', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1]]]));
    $response->assertOk();
});

test('pdf export accepts untergrundbewohner culture', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(function ($data) {
        $skills = collect($data['skills'])->keyBy('name');

        return $data['character']['race'] === 'Barbar' && $data['character']['culture'] === 'Untergrundbewohner' && $skills->has('Athletik') && $skills->has('Beruf: Bergmann') && $skills->has('Überleben');
    }))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['culture' => 'Untergrundbewohner', 'skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Athletik', 'value' => 1], ['name' => 'Beruf: Bergmann', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1]]]));
    $response->assertOk();
});

test('pdf export accepts disuuslachter culture for barbar', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(function ($data) {
        $skills = collect($data['skills'])->keyBy('name');

        return $data['character']['race'] === 'Barbar' && $data['character']['culture'] === 'Disuuslachter (Nordmann)' && $skills->has('Nahkampf') && $skills->has('Überleben') && $skills->has('Beruf: Seemann');
    }))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['culture' => 'Disuuslachter (Nordmann)', 'skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Beruf: Seemann', 'value' => 1], ['name' => 'Intuition', 'value' => 1]]]));
    $response->assertOk();
});

test('pdf export rejects disuuslachter culture for non barbar', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['race' => 'Nosfera', 'culture' => 'Disuuslachter (Nordmann)']));
    $response->assertSessionHasErrors('culture');
});

test('pdf export accepts volk der 13 inseln for barbar with required advantage', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => $data['character']['race'] === 'Barbar' && $data['character']['culture'] === 'Volk der 13 Inseln' && in_array('Psychische Kraft', $data['advantages'], true)))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['gender' => 'weiblich', 'culture' => 'Volk der 13 Inseln', 'skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1], ['name' => 'Athletik', 'value' => 1], ['name' => 'Beruf: Bauer', 'value' => 1]], 'advantages' => ['Zaeh', 'Psychische Kraft']]));
    $response->assertOk();
});

test('pdf export accepts male volk der 13 inseln without required advantage', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => $data['character']['gender'] === 'maennlich' && $data['character']['culture'] === 'Volk der 13 Inseln' && ! in_array('Psychische Kraft', $data['advantages'], true)))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['gender' => 'maennlich', 'culture' => 'Volk der 13 Inseln', 'skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1], ['name' => 'Athletik', 'value' => 1], ['name' => 'Beruf: Fischer', 'value' => 1]], 'advantages' => ['Zaeh']]));
    $response->assertOk();
});

test('pdf export rejects volk der 13 inseln without valid gender', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    foreach (['', 'unbekannt'] as $gender) {
        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['gender' => $gender, 'culture' => 'Volk der 13 Inseln', 'advantages' => ['Zaeh']]));
        $response->assertSessionHasErrors('gender');
    }
});

test('pdf export rejects volk der 13 inseln for non barbar', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['race' => 'Guul', 'culture' => 'Volk der 13 Inseln']));
    $response->assertSessionHasErrors('culture');
});

test('pdf export rejects female volk der 13 inseln without psychische kraft', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['gender' => 'weiblich', 'culture' => 'Volk der 13 Inseln', 'advantages' => ['Zaeh']]));
    $response->assertSessionHasErrors('advantage_effects');
});

test('pdf normalizes skill and special collection payloads to trimmed scalar strings', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => $data['attributes'] === ['st' => '2', 'ge' => '1', 'ro' => '0', 'wi' => '0', 'wa' => '0', 'in' => '0', 'au' => '0'] && $data['skills'] === [['name' => 'Nahkampf', 'value' => '1'], ['name' => 'Überleben', 'value' => '1'], ['name' => 'Intuition', 'value' => '1'], ['name' => 'Beruf: Viehzüchter', 'value' => '2'], ['name' => 'Kunde: Wetter', 'value' => '1'], ['name' => 'Diebeskunst', 'value' => '4'], ['name' => 'Fernkampf', 'value' => '4'], ['name' => 'Handeln', 'value' => '4'], ['name' => 'Fahren', 'value' => '4'], ['name' => 'Heiler', 'value' => '4']] && $data['advantages'] === ['Zäh', 'Anführer'] && $data['disadvantages'] === ['Abergläubisch']))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', ['figurenstaerke' => 3, 'character_name' => 'Collection Payload', 'gender' => 'maennlich', 'race' => 'Barbar', 'culture' => 'Landbewohner', 'attributes' => ['st' => ' 2 ', 'ge' => 1, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0], 'attribute_adjustments' => ['st' => 1, 'ge' => 1, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0], 'barbar_attribute_bonus' => 'st', 'skills' => [['name' => ['manipuliert'], 'value' => '4'], ['name' => ' Nahkampf ', 'value' => 1], ['name' => ' Überleben ', 'value' => 1], ['name' => ' Intuition ', 'value' => 1], ['name' => ' Beruf: Viehzüchter ', 'value' => 2], ['name' => ' Kunde: Wetter ', 'value' => 1], ['name' => ' Diebeskunst ', 'value' => 4], ['name' => ' Fernkampf ', 'value' => 4], ['name' => ' Handeln ', 'value' => 4], ['name' => ' Fahren ', 'value' => 4], ['name' => ' Heiler ', 'value' => 4], ['name' => false, 'value' => '3']], 'advantages' => [' Zaeh ', ['manipuliert'], false, 'Anfuehrer', 'Zaeh'], 'advantage_effects' => [['name' => 'Anfuehrer', 'target' => '', 'justification' => '']], 'disadvantages' => [['manipuliert'], ' Aberglaeubisch ', null, 'Aberglaeubisch'], 'disadvantage_details' => [' Aberglaeubisch ' => ' Salz, Omen, dreimal klopfen '], 'languages' => [], 'clothing' => 'kleidung-einfach', 'equipment_items' => [['id' => 'messer-dolch', 'quantity' => 1], ['id' => 'seil', 'quantity' => 1], ['id' => 'rucksack', 'quantity' => 1], ['id' => 'wasserschlauch', 'quantity' => 1], ['id' => 'wochenration', 'quantity' => 1], ['id' => 'bogen', 'quantity' => 1]]]);
    $response->assertOk();
});

test('pdf export accepts new rulebook disadvantages', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => in_array('Taratzenfutter', $data['disadvantages'], true)))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['disadvantages' => ['Taratzenfutter']]));
    $response->assertOk();
});

test('pdf export rejects unknown advantages and disadvantages', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['advantages' => ['Zäh', 'Laserblick']]))->assertSessionHasErrors('advantages');
    $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['disadvantages' => ['Pechmagnet']]))->assertSessionHasErrors('disadvantages');
});

test('pdf export rejects unknown and restricted skills', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1], ['name' => 'Beruf: Viehzüchter', 'value' => 2], ['name' => 'Kunde: Wetter', 'value' => 1], ['name' => 'Fernwaffen', 'value' => 1]]]))->assertSessionHasErrors('skills');
    $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1], ['name' => 'Beruf: Viehzüchter', 'value' => 2], ['name' => 'Kunde: Wetter', 'value' => 1], ['name' => 'Natürliche Waffen', 'value' => 1]]]))->assertSessionHasErrors('skills');
});

test('pdf export rejects invalid skill values and duplicates', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['skills' => [['name' => 'Nahkampf', 'value' => 5], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1], ['name' => 'Beruf: Viehzüchter', 'value' => 2], ['name' => 'Kunde: Wetter', 'value' => 1]]]))->assertSessionHasErrors('skills');
    $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Nahkampf', 'value' => 2], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1], ['name' => 'Beruf: Viehzüchter', 'value' => 2], ['name' => 'Kunde: Wetter', 'value' => 1]]]))->assertSessionHasErrors('skills');
});

test('pdf export rejects skill point budget overflow', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1], ['name' => 'Beruf: Viehzüchter', 'value' => 2], ['name' => 'Kunde: Wetter', 'value' => 1], ['name' => 'Athletik', 'value' => 4], ['name' => 'Diebeskunst', 'value' => 4], ['name' => 'Fahren', 'value' => 4], ['name' => 'Fernkampf', 'value' => 4], ['name' => 'Feuerwaffen', 'value' => 4], ['name' => 'Handeln', 'value' => 1]]]));
    $response->assertSessionHasErrors('skills');
});

test('pdf export applies base skill grant to only one specialization', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['culture' => 'Stadtbewohner', 'skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1], ['name' => 'Kunde', 'value' => 1], ['name' => 'Unterhalten', 'value' => 1], ['name' => 'Beruf: Künstler', 'value' => 4], ['name' => 'Beruf: Seemann', 'value' => 4], ['name' => 'Athletik', 'value' => 4], ['name' => 'Diebeskunst', 'value' => 4], ['name' => 'Fahren', 'value' => 4], ['name' => 'Fernkampf', 'value' => 2]]]));
    $response->assertSessionHasErrors('skills');
});

test('pdf export enforces skill cross rules', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1], ['name' => 'Bildung', 'value' => 1], ['name' => 'Beruf: Viehzüchter', 'value' => 2], ['name' => 'Kunde: Wetter', 'value' => 1]]]))->assertSessionHasErrors('skills');
    $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1], ['name' => 'Beruf: Viehzüchter', 'value' => 2], ['name' => 'Kunde: Wetter', 'value' => 1], ['name' => 'Wissenschaftler', 'value' => 1]]]))->assertSessionHasErrors('skills');
});

test('pdf export accepts skill aliases and specializations', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(function ($data) {
        $skills = collect($data['skills'])->keyBy('name');

        return $skills->has('Überleben') && $skills->has('Beruf: Künstler') && (($skills['Beruf: Künstler'] ?? [])['value'] ?? null) === '2';
    }))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['culture' => 'Stadtbewohner', 'skills' => [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Ueberleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1], ['name' => 'Beruf: Kuenstler', 'value' => 2], ['name' => 'Kunde', 'value' => 1], ['name' => 'Unterhalten', 'value' => 1]]]));
    $response->assertOk();
});

test('pdf export applies advantage costs and repeatable counts', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => ($data['advantage_counts']['Panzerung'] ?? null) === 2))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['advantages' => ['Zäh', 'Panzerung'], 'advantage_counts' => ['Panzerung' => 2], 'advantage_details' => ['Panzerung' => 'Mutierte Hornplatten'], 'disadvantages' => ['Auffällig', 'Taratzenfutter']]));
    $response->assertOk();
});

test('pdf export accepts required special details with form normalized keys', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => $data['advantages'] === ['Zäh', 'Gesteigertes Attribut'] && $data['disadvantages'] === ['Auffällig'] && $data['advantage_details'] === ['Gesteigertes Attribut' => 'st – ST +1'] && $data['advantage_counts'] === ['Gesteigertes Attribut' => 1] && collect($data['advantage_effects'])->contains(fn ($effect) => $effect === ['name' => 'Gesteigertes Attribut', 'target' => 'st', 'justification' => 'ST +1']) && $data['disadvantage_details'] === []))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['advantages' => ['Zäh', 'Gesteigertes Attribut'], 'advantage_details' => ['Gesteigertes_Attribut' => 'ST +1', 'Tiergefaehrte' => 'Rabe'], 'disadvantages' => ['Auffaellig'], 'disadvantage_details' => ['Feind' => 'Rivalin']]));
    $response->assertOk();
});

test('pdf export accepts three unit advantage with two compensations', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => $data['creation']['advantage_budget']['used'] === 3 && $data['creation']['advantage_budget']['required_compensations'] === 2))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['advantages' => ['Zäh', 'Gestaltwandler'], 'disadvantages' => ['Auffällig', 'Taratzenfutter', 'Blutdurst']]));
    $response->assertOk();
});

test('pdf export rejects missing required special details', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['advantages' => ['Zäh', 'Anführer'], 'disadvantages' => ['Aberglaeubisch']]));
    $response->assertSessionHasErrors('disadvantage_details');
});

test('pdf export validates special detail and count payload limits', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['advantage_details' => ['Gesteigertes_Attribut' => str_repeat('x', 256)], 'disadvantage_details' => ['Feind' => str_repeat('x', 256)], 'advantage_counts' => ['Panzerung' => 21]]));
    $response->assertSessionHasErrors(['advantage_details.Gesteigertes_Attribut', 'disadvantage_details.Feind', 'advantage_counts.Panzerung']);
});

test('pdf export ignores legacy counts for non repeatable advantages', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => $data['advantage_counts'] === []))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['advantages' => ['Zäh', 'Anführer'], 'advantage_counts' => ['Anführer' => 2], 'disadvantages' => ['Auffällig']]));
    $response->assertOk();
});

test('portrait data url payload ignores non scalar values', function (): void {
    $sheetService = app(RpgCharacterSheetService::class);
    $method = new \ReflectionMethod($sheetService, 'portraitDataUrlPayload');
    expect($method->invoke($sheetService, ['manipuliert']))->toBeNull()
        ->and($method->invoke($sheetService, (object) ['manipuliert' => true]))->toBeNull();
});

test('pdf export rejects character name above database column length', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['character_name' => str_repeat('A', 256)]))->assertSessionHasErrors('character_name');
});

test('pdf normalizes character fields to trimmed scalar strings', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => $data['character'] === ['player_name' => 'Holger', 'character_name' => '', 'gender' => 'weiblich', 'race' => 'Barbar', 'culture' => 'Landbewohner', 'description' => '', 'equipment' => '']))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['player_name' => ' Holger ', 'character_name' => ['manipuliert'], 'gender' => ' weiblich ', 'race' => ' Barbar ', 'culture' => ' Landbewohner ', 'description' => ['manipuliert'], 'equipment' => null]));
    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('charakter.pdf');
});

test('pdf includes base64 portrait from editor preview when file input is disabled', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    $image = UploadedFile::fake()->image('avatar.png', 1, 1);
    $dataUrl = 'data:image/png;base64,'.base64_encode($image->get());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => $data['portrait'] === $dataUrl))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', [...$this->validPdfPayload(['character_name' => 'Preview Portrait']), 'portrait_data_url' => $dataUrl]);
    $response->assertOk();
});

test('pdf rejects editor preview portrait data url above character limit', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    $maxChars = (new \ReflectionClass(RpgCharacterSheetService::class))->getReflectionConstant('PORTRAIT_DATA_URL_MAX_CHARS')->getValue();
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', [...$this->validPdfPayload(), 'portrait_data_url' => str_repeat('A', $maxChars + 1)]);
    $response->assertSessionHasErrors('portrait_data_url');
});

test('pdf rejects editor preview portrait data url with line breaks', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    $image = UploadedFile::fake()->image('avatar.png', 1, 1);
    $base64 = base64_encode($image->get());
    $dataUrl = 'data:image/png;base64,'.substr($base64, 0, 8)."\n".substr($base64, 8);
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', [...$this->validPdfPayload(), 'portrait_data_url' => $dataUrl]);
    $response->assertSessionHasErrors(['portrait_data_url' => 'Das Porträt konnte nicht für den PDF-Export verarbeitet werden.']);
});

test('pdf rejects editor preview portrait data url with mismatched mime type', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    $image = UploadedFile::fake()->image('avatar.jpg', 1, 1);
    $dataUrl = 'data:image/png;base64,'.base64_encode($image->get());
    Pdf::shouldReceive('view')->never();
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', [...$this->validPdfPayload(), 'portrait_data_url' => $dataUrl]);
    $response->assertSessionHasErrors(['portrait_data_url' => 'Das Porträt konnte nicht für den PDF-Export verarbeitet werden.']);
});

test('pdf rejects invalid editor preview portrait data', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', [...$this->validPdfPayload(), 'portrait_data_url' => 'data:image/png;base64,'.base64_encode('not an image')]);
    $response->assertSessionHasErrors(['portrait_data_url' => 'Das Porträt konnte nicht für den PDF-Export verarbeitet werden.']);
});

test('pdf downloads with sanitized filename for ag rollenspiel member', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::type('array'))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['character_name' => 'Foo/Bar', 'gender' => 'maennlich', 'portrait' => UploadedFile::fake()->image('avatar.jpg')]));
    expect($response->headers->get('content-disposition'))->toContain('foobar.pdf');
});

test('rejects non image portrait for ag rollenspiel member', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', ['portrait' => UploadedFile::fake()->create('bad.exe', 10, 'application/octet-stream')]);
    $response->assertSessionHasErrors('portrait');
});

test('pdf endpoint renders real pdf for ag rollenspiel member', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload());
    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('foo-bar.pdf')
        ->and($response->getContent())->toStartWith('%PDF');
});

test('pdf generates without portrait for global admin with different current team', function (): void {
    $admin = $this->createManagementUserWithDifferentCurrentTeam(Role::Admin);
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => array_key_exists('portrait', $data) && is_null($data['portrait'])))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($admin)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['character_name' => 'Foo', 'gender' => 'maennlich']));
    $response->assertOk();
});

test('pdf endpoint renders real pdf with uploaded portrait', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', [...$this->validPdfPayload(['character_name' => 'Mit Portrait']), 'portrait' => UploadedFile::fake()->image('avatar.png', 120, 120)]);
    $response->assertOk();
    expect($response->headers->get('content-disposition'))->toContain('mit-portrait.pdf')
        ->and($response->getContent())->toStartWith('%PDF');
});

test('pdf export accepts training allocations and calculates active combat values', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    $skills = [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1], ['name' => 'Beruf: Viehzüchter', 'value' => 2], ['name' => 'Kunde: Wetter', 'value' => 1], ['name' => 'Fahren', 'value' => 4], ['name' => 'Unterhalten: Geschichten', 'value' => 1]];
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn (array $data): bool => $data['trainings'] === ['Arbeiter'] && collect($data['training_allocations'])->contains(fn (array $allocation): bool => $allocation === ['training' => 'Arbeiter', 'skill' => 'Fahren', 'points' => 4]) && ($data['equipment']['active_armor_id'] ?? null) === 'verstaerktes-leder' && ($data['equipment']['active_shield_id'] ?? null) === 'holzschild' && ($data['combat']['active_armor']['id'] ?? null) === 'verstaerktes-leder' && ($data['combat']['active_shield']['id'] ?? null) === 'holzschild' && ($data['combat']['defense']['parade'] ?? null) === 3 && ($data['combat']['defense']['damage_reduction'] ?? null) === 2 && ($data['sheet']['trainings'] ?? null) === 'Arbeiter'))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['skills' => $skills, 'trainings' => ['Arbeiter'], 'training_allocations' => [['training' => 'Arbeiter', 'skill' => 'Fahren', 'points' => 4], ['training' => 'Arbeiter', 'skill' => 'Unterhalten: Geschichten', 'points' => 1]], 'equipment_items' => [['id' => 'schwert', 'quantity' => 1], ['id' => 'bogen', 'quantity' => 1], ['id' => 'verstaerktes-leder', 'quantity' => 1], ['id' => 'holzschild', 'quantity' => 1], ['id' => 'seil', 'quantity' => 1], ['id' => 'rucksack', 'quantity' => 1]], 'active_armor_id' => 'verstaerktes-leder', 'active_shield_id' => 'holzschild']));
    $response->assertOk();
});

test('pdf export rejects invalid training definitions allocations and prerequisites', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['trainings' => ['Raumpilot']]))->assertSessionHasErrors('trainings');
    $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['trainings' => ['Krieger'], 'training_allocations' => [['training' => 'Krieger', 'skill' => 'Heiler', 'points' => 5]]]))->assertSessionHasErrors('training_allocations');
    $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['trainings' => ['Arbeiter'], 'training_allocations' => [['training' => 'Arbeiter', 'skill' => 'Fahren', 'points' => 4]]]))->assertSessionHasErrors('training_allocations');
    $seherSkills = [['name' => 'Nahkampf', 'value' => 1], ['name' => 'Überleben', 'value' => 1], ['name' => 'Intuition', 'value' => 1], ['name' => 'Beruf: Viehzüchter', 'value' => 2], ['name' => 'Kunde: Wetter', 'value' => 1], ['name' => 'Kunde', 'value' => 4], ['name' => 'Sprachen', 'value' => 1]];
    $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['skills' => $seherSkills, 'trainings' => ['Seher'], 'training_allocations' => [['training' => 'Seher', 'skill' => 'Kunde', 'points' => 4], ['training' => 'Seher', 'skill' => 'Sprachen', 'points' => 1]]]))->assertSessionHasErrors('trainings');
    $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['trainings' => ["Rev'rend", 'Krieger', 'Arbeiter']]))->assertSessionHasErrors('trainings');
});

test('pdf export rejects active armor or shield outside the selected equipment', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->never();
    $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['active_armor_id' => 'verstaerktes-leder']))->assertSessionHasErrors('active_armor_id');
    $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['active_shield_id' => 'bogen']))->assertSessionHasErrors('active_shield_id');
});

test('real pdf stays on exactly one a4 page', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['description' => str_repeat('Lange Charakterbeschreibung für den Ausdruck. ', 20), 'equipment' => str_repeat('Ausführliche Ausrüstungsnotiz. ', 20)]));
    $response->assertOk();
    $content = $response->getContent();
    expect($content)->toStartWith('%PDF')
        ->and(preg_match_all('/\/Type\s*\/Page\b/', $content))->toBe(1)
        ->and($content)->toMatch('/\/MediaBox\s*\[\s*0(?:\.0+)?\s+0(?:\.0+)?\s+595(?:\.\d+)?\s+841(?:\.\d+)?\s*\]/');
});

test('pdf includes base64 portrait when uploaded for ag rollenspiel member', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn ($data) => str_starts_with($data['portrait'] ?? '', 'data:image')))->andReturn(new class extends PdfBuilder
    {
        public function toResponse($request): Response
        {
            return response('PDF', 200, $this->responseHeaders);
        }
    });
    $response = $this->followingRedirects()->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload(['character_name' => 'Foo', 'gender' => 'maennlich', 'portrait' => UploadedFile::fake()->image('avatar.png')]));
    $response->assertOk();
});

test('member without ag rollenspiel is forbidden from pdf endpoint', function (): void {
    $member = $this->createMember();
    $this->actingAs($member)->post('/rpg/char-editor/pdf', ['character_name' => 'Foo'])->assertForbidden();
});

test('dompdf dependency is installed', function (): void {
    expect(class_exists(Dompdf::class))->toBeTrue();
});
