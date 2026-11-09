<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\KompendiumRoman;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(KompendiumControllerTestFixtures::class);

test('index hides search when reward not purchased', function (): void {
    $user = $this->actingMemberWithPoints(50);
    $response = $this->get('/kompendium');
    $response->assertOk();
    $response->assertViewHas('showSearch', false);
});

test('index shows search when reward purchased', function (): void {
    $user = $this->actingMemberWithPoints(120);
    $this->purchaseKompendiumForUser($user);
    $response = $this->get('/kompendium');
    $response->assertOk();
    $response->assertViewHas('showSearch', true);
});

test('index shows search for legacy kompendium purchase', function (): void {
    $user = $this->actingMemberWithPoints(120);
    $this->purchaseLegacyKompendiumForUser($user);
    $response = $this->get('/kompendium');
    $response->assertOk();
    $response->assertViewHas('showSearch', true);
});

test('serien endpoint returns only indexed serien', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    // Erstelle indexierte Romane für verschiedene Serien
    KompendiumRoman::create(['dateiname' => '001 - Test.txt', 'dateipfad' => 'romane/maddrax/001 - Test.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Test', 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'indexiert']);
    KompendiumRoman::create(['dateiname' => '001 - Mars.txt', 'dateipfad' => 'romane/missionmars/001 - Mars.txt', 'serie' => 'missionmars', 'roman_nr' => 1, 'titel' => 'Mars', 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'indexiert']);
    // Erstelle einen nicht-indexierten Roman (sollte NICHT erscheinen)
    KompendiumRoman::create(['dateiname' => '001 - Hardcover.txt', 'dateipfad' => 'romane/hardcovers/001 - Hardcover.txt', 'serie' => 'hardcovers', 'roman_nr' => 1, 'titel' => 'Hardcover', 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'hochgeladen']);
    $response = $this->getJson('/kompendium/serien');
    $response->assertOk();
    $serien = $response->json();
    // Nur maddrax und missionmars sollten zurückgegeben werden
    expect($serien)->toHaveKey('maddrax');
    expect($serien)->toHaveKey('missionmars')->not->toHaveKey('hardcovers');
    // Prüfe die Anzeigenamen
    expect($serien['maddrax'])->toEqual('Maddrax - Die dunkle Zukunft der Erde');
    expect($serien['missionmars'])->toEqual('Mission Mars');
});

test('serien endpoint returns empty when no indexed romane', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    // Keine indexierten Romane
    $response = $this->getJson('/kompendium/serien');
    $response->assertOk();
    expect($response->json())->toBeEmpty();
});

test('serien endpoint requires purchased reward', function (): void {
    $user = $this->actingMemberWithPoints(50);
    $this->getJson('/kompendium/serien')->assertStatus(403)->assertJson(['message' => 'Zugang erfordert den Kauf des Kompendium-Rewards oder AG-Maddraxikon-Mitgliedschaft.']);
});

test('ag maddraxikon member sees search without purchased reward', function (): void {
    $user = $this->actingMemberWithPoints(10);
    $this->addUserToAgMaddraxikon($user);
    $response = $this->get('/kompendium');
    $response->assertOk();
    $response->assertViewHas('showSearch', true);
});

test('ag maddraxikon member can use serien endpoint', function (): void {
    $user = $this->actingMemberWithPoints(10);
    // below 100
    $this->addUserToAgMaddraxikon($user);
    $this->getJson('/kompendium/serien')->assertOk();
});

test('non ag member without reward cannot see search', function (): void {
    $user = $this->actingMemberWithPoints(50);
    $response = $this->get('/kompendium');
    $response->assertOk();
    $response->assertViewHas('showSearch', false);
    $response->assertSee('Belohnungen einlösen');
});

test('user with purchased reward but no ag can still search', function (): void {
    $user = $this->actingMemberWithPoints(100);
    $this->purchaseKompendiumForUser($user);
    $response = $this->get('/kompendium');
    $response->assertOk();
    $response->assertViewHas('showSearch', true);
});

test('ag maddraxikon member can use search endpoint', function (): void {
    $user = $this->actingMemberWithPoints(10);
    // below 100
    $this->addUserToAgMaddraxikon($user);
    // q=t hat min:2 → 422 zeigt, dass der Zugangs-Check (403) bestanden wurde
    $this->getJson('/kompendium/suche?q=t')->assertStatus(422);
});

test('user with purchased reward without ag can use search endpoint', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    // q=t hat min:2 → 422 zeigt, dass der Zugangs-Check (403) bestanden wurde
    $this->getJson('/kompendium/suche?q=t')->assertStatus(422);
});

test('user without ag and without purchased reward cannot use search', function (): void {
    $user = $this->actingMemberWithPoints(50);
    $this->getJson('/kompendium/suche?q=test')->assertStatus(403)->assertJson(['message' => 'Zugang erfordert den Kauf des Kompendium-Rewards oder AG-Maddraxikon-Mitgliedschaft.']);
});

test('ag maddraxikon member with purchased reward can search', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->addUserToAgMaddraxikon($user);
    $response = $this->get('/kompendium');
    $response->assertOk();
    $response->assertViewHas('showSearch', true);
});

test('index renders data testid on search input', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $response = $this->get('/kompendium');
    $response->assertOk();
    $response->assertSee('data-testid="kompendium-search"', false);
});

test('index renders search script with correct selector', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $response = $this->get('/kompendium');
    $response->assertOk();
    // Prüfe, dass der data-testid Selektor im Markup vorhanden ist
    $response->assertSee('data-testid="kompendium-search"', false);
    // Prüfe, dass die Livewire-Suchkomponente gerendert wird
    $response->assertSeeLivewire('kompendium-suche');
});

test('index renders script when search allowed', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $response = $this->get('/kompendium');
    $response->assertOk();
    // Die Livewire-Suchkomponente muss gerendert werden
    $response->assertSeeLivewire('kompendium-suche');
});

test('index does not render script when search not allowed', function (): void {
    $user = $this->actingMemberWithPoints(50);
    $response = $this->get('/kompendium');
    $response->assertOk();
    // Keine Livewire-Suchkomponente, da Suche nicht erlaubt
    $response->assertDontSeeLivewire('kompendium-suche');
});

test('ag maddraxikon member with low points gets search script', function (): void {
    $user = $this->actingMemberWithPoints(10);
    $this->addUserToAgMaddraxikon($user);
    $response = $this->get('/kompendium');
    $response->assertOk();
    $response->assertViewHas('showSearch', true);
    // Livewire-Suchkomponente muss gerendert werden, da showSearch = true
    $response->assertSeeLivewire('kompendium-suche');
});

test('index shows no romane message when empty', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $response = $this->get('/kompendium');
    $response->assertOk();
    $response->assertSee('Aktuell sind keine Romane für die Suche indexiert.');
});

test('index shows indexed romane summary', function (): void {
    $user = $this->actingMemberWithPoints(150);
    // Erstelle indexierten Roman mit Zyklus
    KompendiumRoman::create(['dateiname' => '001 - Test.txt', 'dateipfad' => 'romane/maddrax/001 - Test.txt', 'serie' => 'maddrax', 'roman_nr' => 1, 'titel' => 'Test', 'zyklus' => 'Euree', 'hochgeladen_am' => now(), 'hochgeladen_von' => $user->id, 'status' => 'indexiert']);
    $response = $this->get('/kompendium');
    $response->assertOk();
    $response->assertSee('Aktuell sind diese Serien für die Suche indexiert:');
    // Zusammengefasste \u00dcbersicht: Serienname im <strong>-Tag und Zyklus in der Beschreibung
    $response->assertSee('<strong>Maddrax</strong>', false);
    $response->assertSee('Euree-Zyklus');
    $response->assertDontSee('Aktuell sind keine Romane für die Suche indexiert.');
});

test('index shows admin button for admin', function (): void {
    $user = $this->actingMember(Role::Admin);
    $user->incrementTeamPoints(150);
    $this->purchaseKompendiumForUser($user);
    $response = $this->get('/kompendium');
    $response->assertOk();
    $response->assertSee('Kompendium verwalten');
});

test('index hides admin button for non admin', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $response = $this->get('/kompendium');
    $response->assertOk();
    $response->assertDontSee('Kompendium verwalten');
});

test('index shows purchase overlay when search not allowed', function (): void {
    $user = $this->actingMemberWithPoints(50);
    $response = $this->get('/kompendium');
    $response->assertOk();
    $response->assertSee('kompendium-purchase-overlay');
});

test('search with phrase returns only exact matches', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $this->setupSearchMock(files: ['romane/maddrax/001 - Treffer.txt' => 'Hier taucht Matthew Drax in der Story auf.', 'romane/maddrax/002 - Kein Treffer.txt' => 'Matthew ging zu Drax und sprach.', 'romane/maddrax/003 - Nur Matthew.txt' => 'Matthew war allein unterwegs.'], searchResultPaths: ['romane/maddrax/001 - Treffer.txt', 'romane/maddrax/002 - Kein Treffer.txt', 'romane/maddrax/003 - Nur Matthew.txt']);
    $response = $this->getJson('/kompendium/suche?q=%22Matthew+Drax%22');
    $response->assertOk();
    $data = $response->json('data');
    // Nur der exakte Treffer soll zurückgegeben werden
    expect($data)->toHaveCount(1);
    expect($data[0]['title'])->toEqual('Treffer');
});

test('search with phrase and term combines both', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $this->setupSearchMock(files: ['romane/maddrax/001 - Beides.txt' => 'Matthew Drax erlebte ein Abenteuer.', 'romane/maddrax/002 - Nur Phrase.txt' => 'Matthew Drax war müde.', 'romane/maddrax/003 - Nur Term.txt' => 'Ein großes Abenteuer begann.'], searchResultPaths: ['romane/maddrax/001 - Beides.txt', 'romane/maddrax/002 - Nur Phrase.txt', 'romane/maddrax/003 - Nur Term.txt']);
    $response = $this->getJson('/kompendium/suche?q=%22Matthew+Drax%22+Abenteuer');
    $response->assertOk();
    $data = $response->json('data');
    // Nur das Dokument mit Phrase UND freiem Begriff
    expect($data)->toHaveCount(1);
    expect($data[0]['title'])->toEqual('Beides');
});

test('search without quotes requires all terms', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $this->setupSearchMock(files: ['romane/maddrax/001 - Test.txt' => 'Matthew ging spazieren.', 'romane/maddrax/002 - Test2.txt' => 'Drax war auch da.'], searchResultPaths: ['romane/maddrax/001 - Test.txt', 'romane/maddrax/002 - Test2.txt']);
    // Ohne Anführungszeichen gilt jetzt AND-Semantik
    $response = $this->getJson('/kompendium/suche?q=matthew+drax');
    $response->assertOk();
    $data = $response->json('data');
    // Kein Dokument enthält beide Begriffe gleichzeitig
    expect($data)->toHaveCount(0);
    expect($response->json('isPhraseSearch'))->toBeFalse();
});

test('search response contains phrase metadata', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $this->setupSearchMock(files: ['romane/maddrax/001 - Test.txt' => 'Matthew Drax erlebte ein Abenteuer.'], searchResultPaths: ['romane/maddrax/001 - Test.txt']);
    $response = $this->getJson('/kompendium/suche?q=%22Matthew+Drax%22+Abenteuer');
    $response->assertOk();
    $response->assertJson(['isPhraseSearch' => true, 'searchInfo' => ['phrases' => ['matthew drax'], 'terms' => ['abenteuer'], 'excludedPhrases' => [], 'excludedTerms' => [], 'usesOrOperator' => false, 'usesNotOperator' => false]]);
});

test('search with or returns documents matching either clause', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $this->setupSearchMock(files: ['romane/maddrax/001 - Matthew.txt' => 'Matthew durchsuchte die Anlage.', 'romane/maddrax/002 - Aruula.txt' => 'Aruula sicherte den Rückzug.', 'romane/maddrax/003 - Sonstiges.txt' => 'Xij Hamel plante im Verborgenen.'], searchResultPaths: ['romane/maddrax/001 - Matthew.txt', 'romane/maddrax/002 - Aruula.txt', 'romane/maddrax/003 - Sonstiges.txt']);
    $response = $this->getJson('/kompendium/suche?q=matthew+OR+aruula');
    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(2);
    $response->assertJson(['searchInfo' => ['phrases' => [], 'terms' => ['matthew', 'aruula'], 'excludedPhrases' => [], 'excludedTerms' => [], 'usesOrOperator' => true, 'usesNotOperator' => false]]);
});

test('search with not excludes matching documents', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $this->setupSearchMock(files: ['romane/maddrax/001 - Treffer.txt' => 'Matthew erkundete die Anlage allein.', 'romane/maddrax/002 - Ausgeschlossen.txt' => 'Matthew und Aruula planten gemeinsam.'], searchResultPaths: ['romane/maddrax/001 - Treffer.txt', 'romane/maddrax/002 - Ausgeschlossen.txt']);
    $response = $this->getJson('/kompendium/suche?q=matthew+NOT+aruula');
    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['title'])->toBe('Treffer');
    $response->assertJson(['searchInfo' => ['phrases' => [], 'terms' => ['matthew'], 'excludedPhrases' => [], 'excludedTerms' => ['aruula'], 'usesOrOperator' => false, 'usesNotOperator' => true]]);
});

test('search with only negative terms returns helpful message', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $response = $this->getJson('/kompendium/suche?q=NOT+aruula');
    $response->assertOk()->assertJson(['data' => [], 'message' => 'Bitte gib mindestens einen positiven Suchbegriff ein.', 'searchInfo' => ['phrases' => [], 'terms' => [], 'excludedPhrases' => [], 'excludedTerms' => ['aruula'], 'usesOrOperator' => false, 'usesNotOperator' => true]]);
});

test('search with phrase highlights entire phrase', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $this->setupSearchMock(files: ['romane/maddrax/001 - Test.txt' => 'Hier taucht Matthew Drax in der Story auf.'], searchResultPaths: ['romane/maddrax/001 - Test.txt']);
    $response = $this->getJson('/kompendium/suche?q=%22Matthew+Drax%22');
    $response->assertOk();
    $data = $response->json('data');
    $snippets = $data[0]['snippets'];
    expect($snippets)->not->toBeEmpty();
    // Prüfe, dass die gesamte Phrase als ein Block markiert wird
    expect($snippets[0])->toContain('<mark>Matthew Drax</mark>');
});

test('search with empty quotes falls back to normal', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $this->setupSearchMock(files: ['romane/maddrax/001 - Test.txt' => 'Ein großes Abenteuer begann.'], searchResultPaths: ['romane/maddrax/001 - Test.txt']);
    $response = $this->getJson('/kompendium/suche?q=%22%22+Abenteuer');
    $response->assertOk();
    expect($response->json('isPhraseSearch'))->toBeFalse();
    // Snippets müssen "Abenteuer" highlighten, nicht den Raw-Query mit Quotes
    $data = $response->json('data');
    expect($data)->not->toBeEmpty();
    $snippets = $data[0]['snippets'];
    expect($snippets)->not->toBeEmpty()
        ->and($snippets[0])->toContain('<mark>Abenteuer</mark>')->not->toContain('&quot;');
});

test('search with short quoted term highlights remaining terms', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $this->setupSearchMock(files: ['romane/maddrax/001 - Test.txt' => 'Ein großes Abenteuer begann hier.'], searchResultPaths: ['romane/maddrax/001 - Test.txt']);
    // "A" ist zu kurz für eine Phrase, "Abenteuer" ist ein freier Term
    $response = $this->getJson('/kompendium/suche?q=%22A%22+Abenteuer');
    $response->assertOk();
    expect($response->json('isPhraseSearch'))->toBeFalse();
    $data = $response->json('data');
    expect($data)->not->toBeEmpty();
    $snippets = $data[0]['snippets'];
    expect($snippets)->not->toBeEmpty()
        ->and($snippets[0])->toContain('<mark>Abenteuer</mark>');
});

test('search with multiple phrases', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $this->setupSearchMock(files: ['romane/maddrax/001 - Beides.txt' => 'Matthew Drax und das Volk der Tiefe trafen sich.', 'romane/maddrax/002 - Nur Erste.txt' => 'Matthew Drax war allein.', 'romane/maddrax/003 - Nur Zweite.txt' => 'Das Volk der Tiefe lebte im Untergrund.'], searchResultPaths: ['romane/maddrax/001 - Beides.txt', 'romane/maddrax/002 - Nur Erste.txt', 'romane/maddrax/003 - Nur Zweite.txt']);
    $response = $this->getJson('/kompendium/suche?q=%22Matthew+Drax%22+%22Volk+der+Tiefe%22');
    $response->assertOk();
    $data = $response->json('data');
    // Nur das Dokument mit beiden Phrasen
    expect($data)->toHaveCount(1);
    expect($data[0]['title'])->toEqual('Beides');
});

test('search phrase is case insensitive', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $this->setupSearchMock(files: ['romane/maddrax/001 - Test.txt' => 'Hier taucht MATTHEW DRAX in der Story auf.'], searchResultPaths: ['romane/maddrax/001 - Test.txt']);
    $response = $this->getJson('/kompendium/suche?q=%22matthew+drax%22');
    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['snippets'])->not->toBeEmpty();
});

test('search without quotes highlights individual words', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $this->setupSearchMock(files: [
        // Text enthält "matthew" und "drax" getrennt, nicht als exakte Phrase
        'romane/maddrax/001 - Test.txt' => 'Matthew ging einen langen Weg. Drax wartete am Ende.',
    ], searchResultPaths: ['romane/maddrax/001 - Test.txt']);
    // Normale Suche ohne Quotes: "matthew drax"
    $response = $this->getJson('/kompendium/suche?q=matthew+drax');
    $response->assertOk();
    $data = $response->json('data');
    expect($data)->not->toBeEmpty();
    $snippets = $data[0]['snippets'];
    expect($snippets)->not->toBeEmpty();
    // Beide Wörter sollten individuell highlighted werden
    expect($snippets[0])->toContain('<mark>Matthew</mark>');
});

test('phrase search truncation flag present when candidates exceed limit', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    Config::set('kompendium.post_filter.initial_batch_size', 200);
    Config::set('kompendium.post_filter.max_candidates_per_request', 200);
    // Erzeuge 201 Dateien, um das konfigurierte Limit von 200 zu überschreiten.
    $files = [];
    $searchResultPaths = [];
    for ($i = 1; $i <= 201; $i++) {
        $path = sprintf('romane/maddrax/%03d - Roman%d.txt', $i, $i);
        $files[$path] = "Matthew Drax war hier im Roman {$i}.";
        $searchResultPaths[] = $path;
    }
    $this->setupSearchMock(files: $files, searchResultPaths: $searchResultPaths);
    $response = $this->getJson('/kompendium/suche?q=%22Matthew+Drax%22');
    $response->assertOk();
    expect($response->json('candidatesTruncated'))->toBeTrue();
});

test('phrase search no truncation flag when candidates within limit', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    $this->setupSearchMock(files: ['romane/maddrax/001 - Test.txt' => 'Matthew Drax war hier.'], searchResultPaths: ['romane/maddrax/001 - Test.txt']);
    $response = $this->getJson('/kompendium/suche?q=%22Matthew+Drax%22');
    $response->assertOk();
    expect($response->json())->not->toHaveKey('candidatesTruncated');
});

test('search with serien filter scans until genug treffer in der ausgewaehlten serie vorliegen', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    Config::set('kompendium.post_filter.initial_batch_size', 5);
    Config::set('kompendium.post_filter.max_candidates_per_request', 20);
    $files = [];
    $searchResultPaths = [];
    foreach (range(1, 6) as $number) {
        $path = sprintf('romane/missionmars/%03d - Mission Treffer %d.txt', $number, $number);
        $files[$path] = 'Aruula fand einen wichtigen Hinweis.';
        $searchResultPaths[] = $path;
    }
    foreach (range(1, 5) as $number) {
        $path = sprintf('romane/maddrax/%03d - Maddrax Treffer %d.txt', $number, $number);
        $files[$path] = 'Aruula fand einen wichtigen Hinweis.';
        $searchResultPaths[] = $path;
    }
    $this->setupSearchMock(files: $files, searchResultPaths: $searchResultPaths);
    $response = $this->getJson('/kompendium/suche?q=%22Aruula%22&serien[]=maddrax');
    $response->assertOk();
    $data = $response->json('data');
    expect($data)->toHaveCount(5)
        ->and($data[0]['title'])->toBe('Maddrax Treffer 1')
        ->and($response->json('serienCounts.missionmars'))->toBe(6)
        ->and($response->json('serienCounts.maddrax'))->toBe(5);
});

test('search response keeps last page at least current page when truncated after serien filter', function (): void {
    $user = $this->actingMemberWithPoints(150);
    $this->purchaseKompendiumForUser($user);
    Config::set('kompendium.post_filter.initial_batch_size', 5);
    Config::set('kompendium.post_filter.max_candidates_per_request', 5);
    $files = [];
    $searchResultPaths = [];
    foreach (range(1, 5) as $number) {
        $path = sprintf('romane/missionmars/%03d - Mission Treffer %d.txt', $number, $number);
        $files[$path] = 'Aruula fand einen wichtigen Hinweis.';
        $searchResultPaths[] = $path;
    }
    foreach (range(1, 2) as $number) {
        $path = sprintf('romane/maddrax/%03d - Maddrax Treffer %d.txt', $number, $number);
        $files[$path] = 'Aruula fand einen wichtigen Hinweis.';
        $searchResultPaths[] = $path;
    }
    $this->setupSearchMock(files: $files, searchResultPaths: $searchResultPaths);
    $response = $this->getJson('/kompendium/suche?q=%22Aruula%22&page=2&serien[]=maddrax');
    $response->assertOk();
    expect($response->json('candidatesTruncated'))->toBeTrue()
        ->and($response->json('currentPage'))->toBe(2)
        ->and($response->json('lastPage'))->toBe(2);
});
