<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelPdf\Facades\Pdf;

class RpgCharEditorController extends Controller
{
    private const ATTRIBUTE_CREATION_POINTS = 2;

    private const ATTRIBUTE_BASE_MIN = -1;

    private const ATTRIBUTE_BASE_MAX = 1;

    private const ATTRIBUTE_ABSOLUTE_MIN = -2;

    private const ATTRIBUTE_ABSOLUTE_MAX = 2;

    private const ATTRIBUTE_KEYS = ['st', 'ge', 'ro', 'wi', 'wa', 'in', 'au'];

    private const ATTRIBUTE_LABELS = [
        'st' => 'Stärke (ST)',
        'ge' => 'Geschicklichkeit (GE)',
        'ro' => 'Robustheit (RO)',
        'wi' => 'Willenskraft (WI)',
        'wa' => 'Wahrnehmung (WA)',
        'in' => 'Intelligenz (IN)',
        'au' => 'Auftreten (AU)',
    ];

    private const ATTRIBUTE_RULES = [
        'st' => [
            'label' => 'Stärke (ST)',
            'short' => 'ST',
            'name' => 'Stärke',
            'description' => 'Stärke beschreibt die rohe physische Muskelkraft einer Figur.',
            'valueLabels' => [
                -2 => 'greisenhaft',
                -1 => 'kindlich',
                0 => 'durchschnittlich',
                1 => 'mächtig',
                2 => 'titanisch',
            ],
        ],
        'ge' => [
            'label' => 'Geschicklichkeit (GE)',
            'short' => 'GE',
            'name' => 'Geschicklichkeit',
            'description' => 'Geschicklichkeit beschreibt Schnellkraft, Beweglichkeit und Reflexe eines Charakters.',
            'valueLabels' => [
                -2 => 'verkrüppelt',
                -1 => 'tollpatschig',
                0 => 'durchschnittlich',
                1 => 'katzenhaft',
                2 => 'blitzschnell',
            ],
        ],
        'ro' => [
            'label' => 'Robustheit (RO)',
            'short' => 'RO',
            'name' => 'Robustheit',
            'description' => 'Robustheit steht für Gesundheit, Widerstandskraft, Ausdauer und Resistenz gegen Gifte und Krankheiten.',
            'valueLabels' => [
                -2 => 'desolat',
                -1 => 'krank',
                0 => 'gesund',
                1 => 'zäh',
                2 => 'stahlhart',
            ],
        ],
        'wi' => [
            'label' => 'Willenskraft (WI)',
            'short' => 'WI',
            'name' => 'Willenskraft',
            'description' => 'Willenskraft steht für mentale Stärke, geistige Belastbarkeit und Konzentrationsfähigkeit.',
            'valueLabels' => [
                -2 => 'gebrochen',
                -1 => 'beeinflussbar',
                0 => 'durchschnittlich',
                1 => 'stur',
                2 => 'unbeugsam',
            ],
        ],
        'wa' => [
            'label' => 'Wahrnehmung (WA)',
            'short' => 'WA',
            'name' => 'Wahrnehmung',
            'description' => 'Wahrnehmung repräsentiert die fünf Sinne sowie die allgemeine Aufgewecktheit.',
            'valueLabels' => [
                -2 => 'blind',
                -1 => 'unvorsichtig',
                0 => 'durchschnittlich',
                1 => 'wachsam',
                2 => 'Adlerauge',
            ],
        ],
        'in' => [
            'label' => 'Intelligenz (IN)',
            'short' => 'IN',
            'name' => 'Intelligenz',
            'description' => 'Intelligenz umfasst das geistige Potenzial einer Figur, nicht ihre erlernte Bildung.',
            'valueLabels' => [
                -2 => 'idiotisch',
                -1 => 'dumm',
                0 => 'normal',
                1 => 'klug',
                2 => 'brilliant',
            ],
        ],
        'au' => [
            'label' => 'Auftreten (AU)',
            'short' => 'AU',
            'name' => 'Auftreten',
            'description' => 'Auftreten repräsentiert Ausstrahlung, Charisma und Aussehen einer Figur.',
            'valueLabels' => [
                -2 => 'grauenvoll',
                -1 => 'hässlich',
                0 => 'durchschnittlich',
                1 => 'aufregend',
                2 => 'atemberaubend',
            ],
        ],
    ];

    private const RACE_ATTRIBUTE_MODIFIERS = [
        'Guul' => ['au' => -1],
        'Nosfera' => ['ge' => 1, 'au' => -1],
        'Taratze' => ['st' => 1, 'wa' => 1, 'in' => -1, 'au' => -1],
        'Wulfane' => ['ro' => 1, 'au' => -1],
        'Techno' => ['st' => -1, 'ro' => -1, 'in' => 1],
    ];

    private const CHARACTER_KEYS = [
        'player_name',
        'character_name',
        'gender',
        'race',
        'culture',
        'description',
        'equipment',
    ];

    private const GENDER_VALUES = ['weiblich', 'maennlich', 'divers'];

    private const RACE_VALUES = ['Barbar', 'Guul', 'Hydrit', 'Nosfera', 'Taratze', 'Wulfane', 'Techno', 'Präkristofluu'];

    private const CULTURE_VALUES = [
        'Landbewohner',
        'Stadtbewohner',
        'Meeresbewohner',
        'Bunkermensch',
        'Mensch des 21. Jahrhunderts',
        'Nomade',
        'Ruinenbewohner',
        'Untergrundbewohner',
        'Volk der 13 Inseln',
        'Disuuslachter (Nordmann)',
    ];

    private const BASE_SKILL_POINTS = 20;

    private const SKILL_BASE_MIN = 0;

    private const SKILL_BASE_MAX = 4;

    private const SKILL_VALUES = [
        'Athletik',
        'Beruf',
        'Bildung',
        'Diebeskunst',
        'Fahren',
        'Fernkampf',
        'Feuerwaffen',
        'Handeln',
        'Heiler',
        'Heimlichkeit',
        'Intuition',
        'Kunde',
        'Nahkampf',
        'Pilot',
        'Reiten',
        'Sprachen',
        'Techniker',
        'Unterhalten',
        'Überleben',
        'Wissenschaftler',
    ];

    private const SPECIAL_SKILL_VALUES = ['Natürliche Waffen'];

    private const SKILL_SUGGESTIONS = [
        'Athletik',
        'Beruf',
        'Beruf: Bauer',
        'Beruf: Bergmann',
        'Beruf: Farmer',
        'Beruf: Fischer',
        'Beruf: Künstler',
        'Beruf: Landwirt',
        'Beruf: Seemann',
        'Beruf: Viehzüchter',
        'Bildung',
        'Diebeskunst',
        'Fahren',
        'Fernkampf',
        'Feuerwaffen',
        'Handeln',
        'Heiler',
        'Heimlichkeit',
        'Intuition',
        'Kunde',
        'Kunde: Wetter',
        'Nahkampf',
        'Pilot',
        'Reiten',
        'Sprachen',
        'Techniker',
        'Unterhalten',
        'Überleben',
        'Wissenschaftler',
    ];

    private const SKILL_RULES = [
        'Athletik' => [
            'attributes' => ['ST', 'GE', 'RO'],
            'description' => 'Athletik umfasst Klettern, Schwimmen, Laufen und allgemeine körperliche Fitness; ein hoher Wert hilft auch, Angriffen auszuweichen.',
        ],
        'Beruf' => [
            'attributes' => ['GE', 'IN', 'AU'],
            'description' => 'Pro Fertigkeitspunkt beherrscht der Charakter einen Beruf; Erstberuf nutzt den vollen FW, weitere Berufe abgestuft.',
            'specializable' => true,
            'specializationLabel' => 'Beruf notieren',
        ],
        'Bildung' => [
            'attributes' => ['IN', 'WA'],
            'description' => 'Bildung beschreibt zivilisierte Ausbildung und den Umgang mit technischen Gegenständen mit Mindestbildungswert.',
            'exclusiveWith' => 'Intuition',
        ],
        'Diebeskunst' => [
            'attributes' => ['GE', 'WA'],
            'description' => 'Diebeskunst umfasst Taschendiebstahl, Schlösser, das Schätzen von Diebesgut und ähnliche Talente.',
        ],
        'Fahren' => [
            'attributes' => ['GE', 'WA'],
            'description' => 'Fahren gilt für tierisch oder technisch betriebene Fahrzeuge; technische Fahrzeuge sind bildungsabhängig.',
        ],
        'Fernkampf' => [
            'attributes' => ['GE', 'WA'],
            'description' => 'Fernkampf deckt Waffen ab, die durch Muskelkraft wirken, etwa Speere, Steine, Schleudern, Bögen oder Armbrüste.',
        ],
        'Feuerwaffen' => [
            'attributes' => ['GE', 'WA'],
            'description' => 'Feuerwaffen gilt für Schießpulverwaffen und Energiewaffen aller Art, abhängig vom Bildungswert.',
        ],
        'Handeln' => [
            'attributes' => ['AU', 'IN'],
            'description' => 'Handeln umfasst Feilschen, Warenwerte, Geldwerte, Handelsrouten und ähnliche Kenntnisse.',
        ],
        'Heiler' => [
            'attributes' => ['IN'],
            'description' => 'Heiler behandeln Verletzungen und können Lebewesen vor dem Tod bewahren.',
        ],
        'Heimlichkeit' => [
            'attributes' => ['GE'],
            'description' => 'Heimlichkeit beschreibt Schleichen und Sich-Verbergen.',
        ],
        'Intuition' => [
            'attributes' => ['WA'],
            'description' => 'Intuition ist der sechste Sinn der barbarischen Bewohner des 26. Jahrhunderts und hilft, Gefahren zu erspüren.',
            'exclusiveWith' => 'Bildung',
        ],
        'Kunde' => [
            'attributes' => ['IN', 'WA'],
            'description' => 'Pro Fertigkeitspunkt besitzt der Charakter nichtwissenschaftliche Fachkenntnisse in einem Gebiet.',
            'specializable' => true,
            'specializationLabel' => 'Gebiet notieren',
        ],
        'Nahkampf' => [
            'attributes' => ['ST', 'GE'],
            'description' => 'Nahkampf umfasst unbewaffneten Kampf und Nahkampfwaffen.',
        ],
        'Pilot' => [
            'attributes' => ['GE', 'WA'],
            'description' => 'Pilot umfasst den Umgang mit Fluggeräten, je nach Bildung vom Segelflieger bis zum Kampfjet.',
        ],
        'Reiten' => [
            'attributes' => ['GE'],
            'description' => 'Reiten beschreibt das Steuern gezähmter Reittiere und das Zureiten wilder Reittiere.',
        ],
        'Sprachen' => [
            'attributes' => ['IN'],
            'description' => 'Pro Fertigkeitspunkt spricht der Charakter eine Sprache oder einen Dialekt.',
            'specializable' => true,
            'specializationLabel' => 'Sprache oder Dialekt notieren',
        ],
        'Techniker' => [
            'attributes' => ['IN', 'GE'],
            'description' => 'Techniker umfasst das Bedienen, Warten und Reparieren technischer Geräte.',
        ],
        'Unterhalten' => [
            'attributes' => ['AU', 'IN', 'GE'],
            'description' => 'Unterhalten umfasst Erzählen, Tanzen, Singen, Musizieren, Gaukeln und ähnliche Gebiete.',
            'specializable' => true,
            'specializationLabel' => 'Unterhaltungsgebiet notieren',
        ],
        'Überleben' => [
            'attributes' => ['RO', 'WA'],
            'description' => 'Überleben beschreibt Orientierung und Versorgung in der Wildnis.',
        ],
        'Wissenschaftler' => [
            'attributes' => ['IN'],
            'description' => 'Pro Fertigkeitspunkt beherrscht der Charakter eine Wissenschaft; der FW darf den Bildungswert nicht übersteigen.',
            'specializable' => true,
            'specializationLabel' => 'Wissenschaft notieren',
        ],
    ];

    private const SPECIAL_SKILL_RULES = [
        'Natürliche Waffen' => [
            'attributes' => ['ST', 'GE'],
            'description' => 'Rassenbedingte Sonderregel für natürliche Angriffe; nicht frei als normale Fertigkeit wählbar.',
            'restricted' => true,
        ],
    ];

    private const SKILL_NAME_ALIASES = [
        'Ueberleben' => 'Überleben',
        'Natuerliche Waffen' => 'Natürliche Waffen',
        'Beruf: Kuenstler' => 'Beruf: Künstler',
        'Beruf: Viehzuechter' => 'Beruf: Viehzüchter',
        'Kunde: Kraeuter' => 'Kunde: Kräuter',
    ];

    private const BASE_FREE_ADVANTAGES = 2;

    private const ADVANTAGE_VALUES = [
        'Anführer',
        'Gestaltwandler',
        'Gesteigertes Attribut',
        'Gesteigerter Sinn',
        'High-Tech-Ausrüstung',
        'Kampfreflexe',
        'Kaltblütig',
        'Kiemen',
        'Kind zweier Welten',
        'Nachtsicht',
        'Natürliche Waffen',
        'Panzerung',
        'Psychische Kraft',
        'Psychisches Reservoir',
        'Regeneration',
        'Scharfschütze',
        'Schnell',
        'Sprachbegabt',
        'Tiergefährte',
        'Zäh',
    ];

    private const DISADVANTAGE_VALUES = [
        'Abergläubisch',
        'Abhängige',
        'Anfälligkeit gegen Wahnsinn',
        'Auffällig',
        'Blutdurst',
        'Ehrenkodex',
        'Feind',
        'Gejagt',
        'Lichtscheu',
        'Primitiv',
        'Taratzenfutter',
        'Tödliche Immunschwäche',
        'Verpflichtung',
        'Verwundbarkeit',
    ];

    private const SPECIAL_NAME_ALIASES = [
        'Anfuehrer' => 'Anführer',
        'High-Tech-Ausruestung' => 'High-Tech-Ausrüstung',
        'Kaltbluetig' => 'Kaltblütig',
        'Natuerliche Waffen' => 'Natürliche Waffen',
        'Scharfschuetze' => 'Scharfschütze',
        'Tiergefaehrte' => 'Tiergefährte',
        'Zaeh' => 'Zäh',
        'Aberglaeubisch' => 'Abergläubisch',
        'Abhaengige' => 'Abhängige',
        'Anfaelligkeit gegen Wahnsinn' => 'Anfälligkeit gegen Wahnsinn',
        'Auffaellig' => 'Auffällig',
        'Toedliche Immunschwaeche' => 'Tödliche Immunschwäche',
    ];

    private const ADVANTAGE_COSTS = [
        'Gestaltwandler' => 3,
        'Zäh' => 0,
    ];

    private const REPEATABLE_ADVANTAGES = ['Panzerung'];

    private const ADVANTAGE_DETAIL_REQUIRED = [
        'Gesteigertes Attribut',
        'Gesteigerter Sinn',
        'Tiergefährte',
    ];

    private const DISADVANTAGE_DETAIL_REQUIRED = [
        'Abergläubisch',
        'Abhängige',
        'Ehrenkodex',
        'Feind',
        'Gejagt',
        'Verpflichtung',
        'Verwundbarkeit',
    ];

    private const SPECIAL_DETAIL_MAX_ITEMS = 32;

    private const SPECIAL_DETAIL_MAX_CHARS = 255;

    private const ADVANTAGE_COUNT_MAX_ITEMS = 20;

    private const ADVANTAGE_COUNT_MAX_VALUE = 20;

    private const PORTRAIT_MAX_BYTES = 2_097_152;

    private const PORTRAIT_MAX_BASE64_CHARS = 2_796_204;

    private const PORTRAIT_DATA_URL_PREFIX_MAX_CHARS = 23;

    private const PORTRAIT_DATA_URL_MAX_CHARS = self::PORTRAIT_DATA_URL_PREFIX_MAX_CHARS + self::PORTRAIT_MAX_BASE64_CHARS;

    private const PDF_EXPORT_SESSION_MINUTES = 10;

    private const PDF_EXPORT_CACHE_STORE = 'rpg_pdf_exports';

    private const PDF_EXPORT_CACHE_KEY_PREFIX = 'rpg-char-editor-pdf:';

    private const PDF_EXPORT_SESSION_ACTIVE_TOKEN_KEY = 'rpg-char-editor-pdf.active-token';

    public static function attributeRuleConfig(): array
    {
        return [
            'baseMin' => self::ATTRIBUTE_BASE_MIN,
            'baseMax' => self::ATTRIBUTE_BASE_MAX,
            'absoluteMin' => self::ATTRIBUTE_ABSOLUTE_MIN,
            'absoluteMax' => self::ATTRIBUTE_ABSOLUTE_MAX,
            'creationPoints' => self::ATTRIBUTE_CREATION_POINTS,
            'rollFormula' => '2W6 + Attributswert x 3',
            'attributes' => array_map(
                fn (string $key): array => ['id' => $key] + self::ATTRIBUTE_RULES[$key],
                self::ATTRIBUTE_KEYS,
            ),
        ];
    }

    public static function skillRuleConfig(): array
    {
        return [
            'baseMin' => self::SKILL_BASE_MIN,
            'baseMax' => self::SKILL_BASE_MAX,
            'creationPoints' => self::BASE_SKILL_POINTS,
            'skills' => array_map(
                fn (string $name): array => ['name' => $name] + self::SKILL_RULES[$name],
                self::SKILL_VALUES,
            ),
            'suggestions' => self::SKILL_SUGGESTIONS,
            'specialSkills' => array_map(
                fn (string $name): array => ['name' => $name] + self::SPECIAL_SKILL_RULES[$name],
                self::SPECIAL_SKILL_VALUES,
            ),
        ];
    }

    public static function specialRuleConfig(): array
    {
        return [
            'attributeRules' => self::attributeRuleConfig(),
            'skillRules' => self::skillRuleConfig(),
            'advantages' => self::ADVANTAGE_VALUES,
            'disadvantages' => self::DISADVANTAGE_VALUES,
            'advantageCosts' => self::ADVANTAGE_COSTS,
            'repeatableAdvantages' => self::REPEATABLE_ADVANTAGES,
            'advantageDetailRequired' => self::ADVANTAGE_DETAIL_REQUIRED,
            'disadvantageDetailRequired' => self::DISADVANTAGE_DETAIL_REQUIRED,
        ];
    }

    /**
     * Show the character editor form.
     */
    public function index()
    {
        $specialRules = self::specialRuleConfig();

        return view('rpg.char-editor', [
            'specialRules' => $specialRules,
            'advantages' => $specialRules['advantages'],
            'disadvantages' => $specialRules['disadvantages'],
        ]);
    }

    /**
     * Prepare a character sheet PDF and redirect to a GET viewer URL.
     */
    public function storePdfExport(Request $request)
    {
        $data = $this->pdfPayload($request);
        $token = (string) Str::uuid();

        $this->forgetPreviousPdfExport($request);
        $this->putPdfExport($token, [
            'user_id' => (string) $request->user()->getAuthIdentifier(),
            'expires_at' => now()->addMinutes(self::PDF_EXPORT_SESSION_MINUTES)->getTimestamp(),
            'data' => $data,
        ]);

        $request->session()->put(self::PDF_EXPORT_SESSION_ACTIVE_TOKEN_KEY, $token);

        return redirect()->route('rpg.char-editor.pdf.show', ['token' => $token], Response::HTTP_SEE_OTHER);
    }

    /**
     * Generate a character sheet PDF from a prepared export payload.
     */
    public function showPdf(Request $request, string $token)
    {
        $export = $this->getPdfExport($token);

        if (! $this->isValidPdfExport($request, $export)) {
            if (! $this->isFreshPdfExportOwnedByAnotherUser($request, $export)) {
                $this->forgetPdfExport($token);
            }

            $this->forgetActivePdfExport($request, $token);
            abort(404);
        }

        return $this->pdfResponse($export['data']);
    }

    private function pdfPayload(Request $request): array
    {
        $request->validate([
            'portrait' => 'nullable|image|max:2048',
            'portrait_data_url' => 'nullable|string|max:'.self::PORTRAIT_DATA_URL_MAX_CHARS,
            'advantage_details' => 'nullable|array|max:'.self::SPECIAL_DETAIL_MAX_ITEMS,
            'advantage_details.*' => 'nullable|string|max:'.self::SPECIAL_DETAIL_MAX_CHARS,
            'disadvantage_details' => 'nullable|array|max:'.self::SPECIAL_DETAIL_MAX_ITEMS,
            'disadvantage_details.*' => 'nullable|string|max:'.self::SPECIAL_DETAIL_MAX_CHARS,
            'advantage_counts' => 'nullable|array|max:'.self::ADVANTAGE_COUNT_MAX_ITEMS,
            'advantage_counts.*' => 'nullable|integer|min:1|max:'.self::ADVANTAGE_COUNT_MAX_VALUE,
        ]);

        $character = $this->characterPayload($request);
        $attributes = $this->attributesPayload($request->input('attributes', []));
        $skills = $this->skillsPayload($request->input('skills', []));
        $advantages = $this->canonicalSpecialList($this->listPayload($request->input('advantages', [])));
        $disadvantages = $this->canonicalSpecialList($this->listPayload($request->input('disadvantages', [])));
        $advantageDetails = $this->filterSpecialMapByNames($this->specialDetailsPayload($request->input('advantage_details', [])), $advantages);
        $disadvantageDetails = $this->filterSpecialMapByNames($this->specialDetailsPayload($request->input('disadvantage_details', [])), $disadvantages);
        $advantageCounts = $this->advantageCountsPayload($request->input('advantage_counts', []));
        $barbarAttributeBonus = $this->stringPayload($request->input('barbar_attribute_bonus', ''));

        $this->validateCharacterRules(
            $character,
            $attributes,
            $skills,
            $advantages,
            $disadvantages,
            $advantageDetails,
            $disadvantageDetails,
            $advantageCounts,
            $barbarAttributeBonus,
        );

        return [
            'character' => $character,
            'attributes' => $attributes,
            'skills' => $skills,
            'advantages' => $advantages,
            'disadvantages' => $disadvantages,
            'advantage_details' => $advantageDetails,
            'disadvantage_details' => $disadvantageDetails,
            'advantage_counts' => $advantageCounts,
            'portrait' => $this->portraitPayload($request),
        ];
    }

    private function pdfResponse(array $data)
    {
        $name = Str::slug($data['character']['character_name'] ?: 'charakter') ?: 'charakter';

        return Pdf::view('rpg.char-sheet', $data)
            ->driver('dompdf')
            ->format('a4')
            ->margins(10, 10, 10, 10)
            ->inline($name.'.pdf');
    }

    private function pdfExportCache(): CacheRepository
    {
        return Cache::store(self::PDF_EXPORT_CACHE_STORE);
    }

    private function pdfExportCacheKey(string $token): string
    {
        return self::PDF_EXPORT_CACHE_KEY_PREFIX.$token;
    }

    private function putPdfExport(string $token, array $export): void
    {
        $this->pdfExportCache()->put(
            $this->pdfExportCacheKey($token),
            $export,
            now()->addMinutes(self::PDF_EXPORT_SESSION_MINUTES),
        );
    }

    private function getPdfExport(string $token): mixed
    {
        return $this->pdfExportCache()->get($this->pdfExportCacheKey($token));
    }

    private function forgetPdfExport(string $token): void
    {
        $this->pdfExportCache()->forget($this->pdfExportCacheKey($token));
    }

    private function forgetPreviousPdfExport(Request $request): void
    {
        $previousToken = $request->session()->pull(self::PDF_EXPORT_SESSION_ACTIVE_TOKEN_KEY);

        if (is_string($previousToken)) {
            $this->forgetPdfExport($previousToken);
        }
    }

    private function forgetActivePdfExport(Request $request, string $token): void
    {
        if ($request->session()->get(self::PDF_EXPORT_SESSION_ACTIVE_TOKEN_KEY) !== $token) {
            return;
        }

        $request->session()->forget(self::PDF_EXPORT_SESSION_ACTIVE_TOKEN_KEY);
    }

    private function isValidPdfExport(Request $request, mixed $export): bool
    {
        return is_array($export)
            && ($export['user_id'] ?? null) === (string) $request->user()->getAuthIdentifier()
            && is_numeric($export['expires_at'] ?? null)
            && (int) $export['expires_at'] >= now()->getTimestamp()
            && is_array($export['data'] ?? null);
    }

    private function isFreshPdfExportOwnedByAnotherUser(Request $request, mixed $export): bool
    {
        return is_array($export)
            && is_string($export['user_id'] ?? null)
            && ($export['user_id'] ?? null) !== (string) $request->user()->getAuthIdentifier()
            && is_numeric($export['expires_at'] ?? null)
            && (int) $export['expires_at'] >= now()->getTimestamp()
            && is_array($export['data'] ?? null);
    }

    private function characterPayload(Request $request): array
    {
        $character = [];

        foreach (self::CHARACTER_KEYS as $key) {
            $character[$key] = $this->stringPayload($request->input($key, ''));
        }

        return $character;
    }

    private function validateCharacterRules(array $character, array $attributes, array $skills, array $advantages, array $disadvantages, array $advantageDetails = [], array $disadvantageDetails = [], array $advantageCounts = [], string $barbarAttributeBonus = ''): void
    {
        $race = $character['race'] ?? '';
        $culture = $character['culture'] ?? '';
        $gender = $character['gender'] ?? '';
        $canonicalAdvantages = $this->canonicalSpecialList($advantages);
        $canonicalDisadvantages = $this->canonicalSpecialList($disadvantages);
        $canonicalAdvantageDetails = $this->canonicalSpecialMap($advantageDetails);
        $canonicalDisadvantageDetails = $this->canonicalSpecialMap($disadvantageDetails);
        $canonicalAdvantageCounts = $this->canonicalSpecialMap($advantageCounts);

        $this->validateSpecialLists($canonicalAdvantages, $canonicalDisadvantages);
        $this->validateAdvantageCounts($canonicalAdvantages, $canonicalAdvantageCounts);

        if (! in_array($gender, self::GENDER_VALUES, true)) {
            throw ValidationException::withMessages([
                'gender' => 'Das Geschlecht muss gewählt werden und einem erlaubten Wert entsprechen.',
            ]);
        }

        if (! in_array($race, self::RACE_VALUES, true)) {
            throw ValidationException::withMessages([
                'race' => 'Die Rasse muss gewählt werden und einem erlaubten Wert entsprechen.',
            ]);
        }

        if (! in_array($culture, self::CULTURE_VALUES, true)) {
            throw ValidationException::withMessages([
                'culture' => 'Die Kultur muss gewählt werden und einem erlaubten Wert entsprechen.',
            ]);
        }

        if ($race === 'Hydrit' && $culture !== 'Meeresbewohner') {
            throw ValidationException::withMessages([
                'culture' => 'Hydriten können laut Regelwerk nur die Kultur Meeresbewohner wählen.',
            ]);
        }

        if ($culture === 'Meeresbewohner' && $race !== 'Hydrit') {
            throw ValidationException::withMessages([
                'culture' => 'Die Kultur Meeresbewohner ist laut Regelwerk nur für Hydriten zugelassen.',
            ]);
        }

        if ($race === 'Techno' && $culture !== 'Bunkermensch') {
            throw ValidationException::withMessages([
                'culture' => 'Technos können laut Regelwerk nur die Kultur Bunkermensch wählen.',
            ]);
        }

        if ($race === 'Präkristofluu' && $culture !== 'Mensch des 21. Jahrhunderts') {
            throw ValidationException::withMessages([
                'culture' => 'Präkristofluu können laut Regelwerk nur die Kultur Mensch des 21. Jahrhunderts wählen.',
            ]);
        }

        if ($culture === 'Bunkermensch' && $race !== 'Techno') {
            throw ValidationException::withMessages([
                'culture' => 'Die Kultur Bunkermensch ist laut Regelwerk nur für Technos zugelassen.',
            ]);
        }

        if ($culture === 'Mensch des 21. Jahrhunderts' && $race !== 'Präkristofluu') {
            throw ValidationException::withMessages([
                'culture' => 'Die Kultur Mensch des 21. Jahrhunderts ist laut Regelwerk nur für Präkristofluu zugelassen.',
            ]);
        }

        if ($culture === 'Volk der 13 Inseln' && $race !== 'Barbar') {
            throw ValidationException::withMessages([
                'culture' => 'Die Kultur Volk der 13 Inseln ist laut Regelwerk nur für Barbaren zugelassen.',
            ]);
        }

        if ($culture === 'Disuuslachter (Nordmann)' && $race !== 'Barbar') {
            throw ValidationException::withMessages([
                'culture' => 'Die Kultur Disuuslachter (Nordmann) ist laut Regelwerk nur für Barbaren zugelassen.',
            ]);
        }

        if ($culture === 'Volk der 13 Inseln'
            && $gender === 'weiblich'
            && ! in_array('Psychische Kraft', $canonicalAdvantages, true)) {
            throw ValidationException::withMessages([
                'advantages' => 'Weibliche Charaktere aus dem Volk der 13 Inseln müssen Psychische Kraft wählen.',
            ]);
        }

        $this->validateAttributes($race, $attributes, $barbarAttributeBonus);
        $this->validateSkillRules($race, $culture, $skills, $canonicalAdvantages);
        $this->validateRaceRequirements($race, $attributes, $skills, $canonicalAdvantages, $canonicalDisadvantages);
        $this->validateCultureRequirements($culture, $skills);
        $this->validateSpecialBudgetAndDetails(
            $race,
            $culture,
            $gender,
            $canonicalAdvantages,
            $canonicalDisadvantages,
            $canonicalAdvantageDetails,
            $canonicalDisadvantageDetails,
            $canonicalAdvantageCounts,
        );
    }

    private function validateSkillRules(string $race, string $culture, array $skills, array $advantages): void
    {
        $seen = [];
        $grants = $this->freeSkillGrants($race, $culture, $skills);

        foreach ($skills as $skill) {
            $name = (string) ($skill['name'] ?? '');
            $value = (string) ($skill['value'] ?? '');

            if (! $this->isAllowedSkillName($name, $race)) {
                throw ValidationException::withMessages([
                    'skills' => "Die Fertigkeit {$name} ist laut Regelwerk nicht erlaubt.",
                ]);
            }

            if (isset($seen[$name])) {
                throw ValidationException::withMessages([
                    'skills' => "Die Fertigkeit {$name} wurde mehrfach eingetragen.",
                ]);
            }

            $seen[$name] = true;

            if (! preg_match('/^-?\d+$/', $value)) {
                throw ValidationException::withMessages([
                    'skills' => "Der Fertigkeitswert für {$name} muss als ganze Zahl übermittelt werden.",
                ]);
            }

            $skillValue = (int) $value;

            if ($skillValue < self::SKILL_BASE_MIN || $skillValue > self::SKILL_BASE_MAX) {
                throw ValidationException::withMessages([
                    'skills' => "Der Fertigkeitswert für {$name} muss im Bereich von ".self::SKILL_BASE_MIN.' bis '.self::SKILL_BASE_MAX.' liegen.',
                ]);
            }

            if (in_array($name, self::SPECIAL_SKILL_VALUES, true)) {
                $grantValue = $this->skillGrantValue($grants, $name);

                if ($grantValue === null || $skillValue !== $grantValue) {
                    throw ValidationException::withMessages([
                        'skills' => "Die Fertigkeit {$name} ist nur als rassenbedingte Sonderregel erlaubt.",
                    ]);
                }
            }
        }

        if (! in_array('Kind zweier Welten', $advantages, true)
            && $this->skillValue($skills, 'Bildung') > 0
            && $this->skillValue($skills, 'Intuition') > 0) {
            throw ValidationException::withMessages([
                'skills' => 'Ohne den Vorteil Kind zweier Welten darf nur Bildung oder Intuition größer 0 sein.',
            ]);
        }

        $wissenschaftler = $this->skillValue($skills, 'Wissenschaftler');
        $bildung = max(0, $this->skillValue($skills, 'Bildung'));

        if ($wissenschaftler > $bildung) {
            throw ValidationException::withMessages([
                'skills' => 'Der Fertigkeitswert Wissenschaftler darf den Bildungswert nicht übersteigen.',
            ]);
        }

        if ($this->skillPointsUsed($skills, $grants) > self::BASE_SKILL_POINTS) {
            throw ValidationException::withMessages([
                'skills' => 'Die gewählten Fertigkeiten überschreiten die verfügbaren Fertigkeitspunkte.',
            ]);
        }
    }

    private function isAllowedSkillName(string $name, string $race): bool
    {
        if (in_array($name, self::SKILL_VALUES, true)) {
            return true;
        }

        if (in_array($name, self::SPECIAL_SKILL_VALUES, true)) {
            return array_key_exists($name, $this->raceRequirements($race)['skills'] ?? []);
        }

        $baseName = $this->skillBaseName($name);
        $detail = $this->skillSpecialization($name);

        return $detail !== null
            && $detail !== ''
            && in_array($baseName, self::SKILL_VALUES, true)
            && (bool) (self::SKILL_RULES[$baseName]['specializable'] ?? false);
    }

    private function freeSkillGrants(string $race, string $culture, array $skills): array
    {
        $grants = [];
        $this->addRequirementSkillGrants($grants, $this->raceRequirements($race), $skills);
        $this->addRequirementSkillGrants($grants, $this->cultureRequirements($culture), $skills);

        return $grants;
    }

    private function addRequirementSkillGrants(array &$grants, array $requirements, array $skills): void
    {
        foreach ($requirements['skills'] ?? [] as $skillName => $minimumValue) {
            $grantName = $this->grantableSkillName($skills, $skillName, (int) $minimumValue)
                ?? $this->canonicalSkillName($skillName);

            $this->setSkillGrant($grants, $grantName, (int) $minimumValue);
        }

        foreach ($requirements['anySkills'] ?? [] as $choice) {
            foreach ($choice['names'] as $skillName) {
                $grantName = $this->grantableSkillName($skills, $skillName, (int) $choice['minimum']);

                if ($grantName !== null) {
                    $this->setSkillGrant($grants, $grantName, (int) $choice['minimum']);
                    break;
                }
            }
        }

        foreach ($requirements['countSkills'] ?? [] as $choice) {
            $remaining = (int) $choice['count'];

            foreach ($choice['names'] as $skillName) {
                if ($remaining <= 0) {
                    break;
                }

                $grantName = $this->grantableSkillName($skills, $skillName, (int) $choice['minimum']);

                if ($grantName !== null) {
                    $this->setSkillGrant($grants, $grantName, (int) $choice['minimum']);
                    $remaining--;
                }
            }
        }
    }

    private function grantableSkillName(array $skills, string $skillName, int $minimumValue): ?string
    {
        $canonicalSkillName = $this->canonicalSkillName($skillName);
        $matchesSpecializationBase = $this->isSpecializableBaseSkill($canonicalSkillName);

        foreach ($skills as $skill) {
            $name = $this->canonicalSkillName((string) ($skill['name'] ?? ''));

            if ($name !== $canonicalSkillName
                && (! $matchesSpecializationBase || $this->skillBaseName($name) !== $canonicalSkillName)
            ) {
                continue;
            }

            if (is_numeric($skill['value'] ?? null) && (int) $skill['value'] >= $minimumValue) {
                return $name;
            }
        }

        return null;
    }

    private function isSpecializableBaseSkill(string $skillName): bool
    {
        $canonicalSkillName = $this->canonicalSkillName($skillName);

        if ($canonicalSkillName !== $this->skillBaseName($canonicalSkillName)) {
            return false;
        }

        if (! array_key_exists($canonicalSkillName, self::SKILL_RULES)) {
            return false;
        }

        return (bool) (self::SKILL_RULES[$canonicalSkillName]['specializable'] ?? false);
    }

    private function setSkillGrant(array &$grants, string $skillName, int $value): void
    {
        $canonicalName = $this->canonicalSkillName($skillName);
        $grants[$canonicalName] = max($grants[$canonicalName] ?? self::SKILL_BASE_MIN, $value);
    }

    private function skillPointsUsed(array $skills, array $grants): int
    {
        $used = 0;

        foreach ($skills as $skill) {
            $name = (string) ($skill['name'] ?? '');
            $rawValue = (string) ($skill['value'] ?? '');

            if (! preg_match('/^-?\d+$/', $rawValue)) {
                continue;
            }

            $grantValue = $this->skillGrantValue($grants, $name) ?? self::SKILL_BASE_MIN;
            $used += max((int) $rawValue - $grantValue, 0);
        }

        return $used;
    }

    private function skillGrantValue(array $grants, string $skillName): ?int
    {
        $canonicalSkillName = $this->canonicalSkillName($skillName);

        return array_key_exists($canonicalSkillName, $grants)
            ? $grants[$canonicalSkillName]
            : null;
    }

    private function validateRaceRequirements(string $race, array $attributes, array $skills, array $advantages, array $disadvantages): void
    {
        $requirements = $this->raceRequirements($race);

        if ($requirements === []) {
            return;
        }

        foreach ($requirements['advantages'] ?? [] as $advantage) {
            if (! in_array($advantage, $advantages, true)) {
                throw ValidationException::withMessages([
                    'advantages' => "Die Rasse {$race} benötigt den Vorteil {$advantage}.",
                ]);
            }
        }

        foreach ($requirements['disadvantages'] ?? [] as $disadvantage) {
            if (! in_array($disadvantage, $disadvantages, true)) {
                throw ValidationException::withMessages([
                    'disadvantages' => "Die Rasse {$race} benötigt den Nachteil {$disadvantage}.",
                ]);
            }
        }

        foreach ($requirements['skills'] ?? [] as $skillName => $minimumValue) {
            if ($this->skillValue($skills, $skillName) < $minimumValue) {
                throw ValidationException::withMessages([
                    'skills' => "Die Rasse {$race} benötigt {$skillName} mindestens auf {$minimumValue}.",
                ]);
            }
        }

        foreach ($requirements['anySkills'] ?? [] as $choice) {
            $hasChoice = collect($choice['names'])
                ->contains(fn (string $skillName) => $this->skillValue($skills, $skillName) >= $choice['minimum']);

            if (! $hasChoice) {
                throw ValidationException::withMessages([
                    'skills' => "Die Rasse {$race} benötigt {$choice['label']}.",
                ]);
            }
        }

    }

    private function validateCultureRequirements(string $culture, array $skills): void
    {
        $requirements = $this->cultureRequirements($culture);

        if ($requirements === []) {
            return;
        }

        foreach ($requirements['skills'] ?? [] as $skillName => $minimumValue) {
            if ($this->skillValue($skills, $skillName) < $minimumValue) {
                throw ValidationException::withMessages([
                    'skills' => "Die Kultur {$culture} benötigt {$skillName} mindestens auf {$minimumValue}.",
                ]);
            }
        }

        foreach ($requirements['anySkills'] ?? [] as $choice) {
            $hasChoice = collect($choice['names'])
                ->contains(fn (string $skillName) => $this->skillValue($skills, $skillName) >= $choice['minimum']);

            if (! $hasChoice) {
                throw ValidationException::withMessages([
                    'skills' => "Die Kultur {$culture} benötigt {$choice['label']}.",
                ]);
            }
        }

        foreach ($requirements['countSkills'] ?? [] as $choice) {
            $matchingSkills = collect($choice['names'])
                ->filter(fn (string $skillName) => $this->skillValue($skills, $skillName) >= $choice['minimum'])
                ->count();

            if ($matchingSkills < $choice['count']) {
                throw ValidationException::withMessages([
                    'skills' => "Die Kultur {$culture} benötigt {$choice['label']}.",
                ]);
            }
        }
    }

    private function cultureRequirements(string $culture): array
    {
        return match ($culture) {
            'Landbewohner' => [
                'skills' => ['Kunde: Wetter' => 1],
                'anySkills' => [[
                    'names' => ['Beruf: Viehzüchter', 'Beruf: Landwirt'],
                    'minimum' => 2,
                    'label' => 'Beruf: Viehzüchter oder Beruf: Landwirt mindestens auf 2',
                ]],
            ],
            'Stadtbewohner' => [
                'skills' => ['Beruf' => 1, 'Kunde' => 1],
                'anySkills' => [[
                    'names' => ['Unterhalten', 'Sprachen'],
                    'minimum' => 1,
                    'label' => 'Unterhalten oder Sprachen mindestens auf 1',
                ]],
            ],
            'Meeresbewohner' => [
                'skills' => ['Athletik' => 1],
                'anySkills' => [
                    [
                        'names' => ['Beruf: Farmer', 'Beruf: Künstler'],
                        'minimum' => 1,
                        'label' => 'Beruf: Farmer oder Beruf: Künstler mindestens auf 1',
                    ],
                    [
                        'names' => ['Wissenschaftler', 'Techniker', 'Nahkampf'],
                        'minimum' => 1,
                        'label' => 'Wissenschaftler, Techniker oder Nahkampf mindestens auf 1',
                    ],
                ],
            ],
            'Bunkermensch' => [
                'skills' => ['Bildung' => 1, 'Nahkampf' => 1],
                'anySkills' => [[
                    'names' => ['Feuerwaffen', 'Pilot', 'Wissenschaftler'],
                    'minimum' => 1,
                    'label' => 'Feuerwaffen, Pilot oder Wissenschaftler mindestens auf 1',
                ]],
            ],
            'Mensch des 21. Jahrhunderts' => [
                'skills' => ['Beruf' => 1],
                'countSkills' => [[
                    'names' => ['Bildung', 'Pilot', 'Techniker', 'Wissenschaftler'],
                    'minimum' => 1,
                    'count' => 2,
                    'label' => 'zwei verschiedene Fertigkeiten aus Bildung, Pilot, Techniker oder Wissenschaftler mindestens auf 1',
                ]],
            ],
            'Nomade' => [
                'skills' => ['Überleben' => 1],
                'anySkills' => [
                    [
                        'names' => ['Nahkampf', 'Fernkampf'],
                        'minimum' => 1,
                        'label' => 'Nahkampf oder Fernkampf mindestens auf 1',
                    ],
                    [
                        'names' => ['Reiten', 'Athletik'],
                        'minimum' => 1,
                        'label' => 'Reiten oder Athletik mindestens auf 1',
                    ],
                ],
            ],
            'Ruinenbewohner' => [
                'skills' => ['Diebeskunst' => 1, 'Heimlichkeit' => 1],
                'anySkills' => [[
                    'names' => ['Nahkampf', 'Fernkampf', 'Athletik', 'Kunde'],
                    'minimum' => 1,
                    'label' => 'Nahkampf, Fernkampf, Athletik oder Kunde mindestens auf 1',
                ]],
            ],
            'Untergrundbewohner' => [
                'skills' => ['Athletik' => 1, 'Beruf: Bergmann' => 1, 'Überleben' => 1],
            ],
            'Volk der 13 Inseln' => [
                'skills' => ['Athletik' => 1, 'Überleben' => 1],
                'anySkills' => [[
                    'names' => ['Beruf: Bauer', 'Beruf: Fischer'],
                    'minimum' => 1,
                    'label' => 'Beruf: Bauer oder Beruf: Fischer mindestens auf 1',
                ]],
            ],
            'Disuuslachter (Nordmann)' => [
                'skills' => ['Nahkampf' => 1, 'Überleben' => 1, 'Beruf: Seemann' => 1],
            ],
            default => [],
        };
    }

    private function validateSpecialLists(array $advantages, array $disadvantages): void
    {
        foreach ($advantages as $advantage) {
            if (! in_array($advantage, self::ADVANTAGE_VALUES, true)) {
                throw ValidationException::withMessages([
                    'advantages' => "Der Vorteil {$advantage} ist laut Regelwerk nicht erlaubt.",
                ]);
            }
        }

        foreach ($disadvantages as $disadvantage) {
            if (! in_array($disadvantage, self::DISADVANTAGE_VALUES, true)) {
                throw ValidationException::withMessages([
                    'disadvantages' => "Der Nachteil {$disadvantage} ist laut Regelwerk nicht erlaubt.",
                ]);
            }
        }
    }

    private function validateAdvantageCounts(array $advantages, array $advantageCounts): void
    {
        foreach ($advantageCounts as $advantage => $count) {
            if (! in_array($advantage, self::ADVANTAGE_VALUES, true)) {
                throw ValidationException::withMessages([
                    'advantages' => "Der Vorteil {$advantage} ist laut Regelwerk nicht erlaubt.",
                ]);
            }

            if (! in_array($advantage, self::REPEATABLE_ADVANTAGES, true)) {
                throw ValidationException::withMessages([
                    'advantages' => "Der Vorteil {$advantage} kann laut Regelwerk nicht mehrfach gewählt werden.",
                ]);
            }

            if (! in_array($advantage, $advantages, true)) {
                throw ValidationException::withMessages([
                    'advantages' => "Für {$advantage} wurde eine Anzahl übermittelt, ohne den Vorteil zu wählen.",
                ]);
            }

            if (! is_int($count) || $count < 1) {
                throw ValidationException::withMessages([
                    'advantages' => "Die Anzahl für {$advantage} muss mindestens 1 sein.",
                ]);
            }
        }
    }

    private function validateSpecialBudgetAndDetails(
        string $race,
        string $culture,
        string $gender,
        array $advantages,
        array $disadvantages,
        array $advantageDetails,
        array $disadvantageDetails,
        array $advantageCounts,
    ): void {
        $lockedAdvantages = $this->lockedAdvantages($race, $culture, $gender);
        $lockedDisadvantages = $this->lockedDisadvantages($race);
        $cost = $this->chosenAdvantageCost($advantages, $lockedAdvantages, $advantageCounts);

        if ($cost > self::BASE_FREE_ADVANTAGES) {
            throw ValidationException::withMessages([
                'advantages' => 'Die gewählten Vorteile überschreiten die verfügbaren Vorteilspunkte.',
            ]);
        }

        if (count($disadvantages) < $cost) {
            throw ValidationException::withMessages([
                'disadvantages' => 'Für die gewählten Vorteile müssen ausreichend Nachteile gewählt werden.',
            ]);
        }

        foreach (self::ADVANTAGE_DETAIL_REQUIRED as $advantage) {
            if (in_array($advantage, $advantages, true)
                && ! in_array($advantage, $lockedAdvantages, true)
                && ($advantageDetails[$advantage] ?? '') === '') {
                throw ValidationException::withMessages([
                    'advantage_details' => "Für den Vorteil {$advantage} muss eine nähere Angabe gemacht werden.",
                ]);
            }
        }

        foreach (self::DISADVANTAGE_DETAIL_REQUIRED as $disadvantage) {
            if (in_array($disadvantage, $disadvantages, true)
                && ! in_array($disadvantage, $lockedDisadvantages, true)
                && ($disadvantageDetails[$disadvantage] ?? '') === '') {
                throw ValidationException::withMessages([
                    'disadvantage_details' => "Für den Nachteil {$disadvantage} muss eine nähere Angabe gemacht werden.",
                ]);
            }
        }
    }

    private function chosenAdvantageCost(array $advantages, array $lockedAdvantages, array $advantageCounts): int
    {
        $cost = 0;

        foreach ($advantages as $advantage) {
            if ($advantage === 'Zäh' || in_array($advantage, $lockedAdvantages, true)) {
                continue;
            }

            $baseCost = self::ADVANTAGE_COSTS[$advantage] ?? 1;
            $count = in_array($advantage, self::REPEATABLE_ADVANTAGES, true)
                ? ($advantageCounts[$advantage] ?? 1)
                : 1;

            $cost += $baseCost * $count;
        }

        return $cost;
    }

    private function lockedAdvantages(string $race, string $culture, string $gender): array
    {
        $advantages = $this->raceRequirements($race)['advantages'] ?? [];

        if ($culture === 'Volk der 13 Inseln' && $gender === 'weiblich') {
            $advantages[] = 'Psychische Kraft';
        }

        return array_values(array_unique($advantages));
    }

    private function lockedDisadvantages(string $race): array
    {
        return array_values(array_unique($this->raceRequirements($race)['disadvantages'] ?? []));
    }

    private function validateAttributes(string $race, array $attributes, string $barbarAttributeBonus): void
    {
        if ($race === 'Barbar') {
            $this->validateBarbarAttributes($attributes, $barbarAttributeBonus);

            return;
        }

        $error = $this->attributeValidationError($race, $attributes, $this->raceAttributeModifiers($race));

        if ($error !== null) {
            throw ValidationException::withMessages(['attributes' => $error]);
        }
    }

    private function validateBarbarAttributes(array $attributes, string $barbarAttributeBonus): void
    {
        if ($barbarAttributeBonus !== '' && ! in_array($barbarAttributeBonus, self::ATTRIBUTE_KEYS, true)) {
            throw ValidationException::withMessages([
                'attributes' => 'Der Attributbonus der Barbaren muss einem erlaubten Attribut entsprechen.',
            ]);
        }

        $candidates = $barbarAttributeBonus !== '' ? [$barbarAttributeBonus] : self::ATTRIBUTE_KEYS;
        $firstError = null;

        foreach ($candidates as $candidate) {
            $error = $this->attributeValidationError('Barbar', $attributes, [$candidate => 1]);

            if ($error === null) {
                return;
            }

            $firstError ??= $error;
        }

        throw ValidationException::withMessages(['attributes' => $firstError]);
    }

    private function attributeValidationError(string $race, array $attributes, array $modifiers): ?string
    {
        $spentPoints = 0;

        foreach (self::ATTRIBUTE_KEYS as $attributeName) {
            $modifier = (int) ($modifiers[$attributeName] ?? 0);
            $attributeLabel = $this->attributeLabel($attributeName);

            if (! array_key_exists($attributeName, $attributes) || $attributes[$attributeName] === '') {
                if ($modifier !== 0) {
                    return "Das Attribut {$attributeLabel} muss für die Rasse {$race} übermittelt werden.";
                }

                continue;
            }

            $rawValue = (string) $attributes[$attributeName];

            if (! preg_match('/^-?\d+$/', $rawValue)) {
                return "Das Attribut {$attributeLabel} muss als ganzer Zahlenwert übermittelt werden.";
            }

            $attributeValue = (int) $rawValue;
            [$minimumValue, $maximumValue] = $this->attributeRange($modifier);

            if ($attributeValue < $minimumValue || $attributeValue > $maximumValue) {
                if ($modifier !== 0) {
                    return "Das Attribut {$attributeLabel} passt nicht zu den Rassenmodifikatoren von {$race}.";
                }

                return "Das Attribut {$attributeLabel} muss im Bereich von {$minimumValue} bis {$maximumValue} liegen.";
            }

            $spentPoints += max($attributeValue - $modifier, 0);
        }

        if ($spentPoints > self::ATTRIBUTE_CREATION_POINTS) {
            return 'Die gewählten Attribute überschreiten die verfügbaren Attributspunkte.';
        }

        return null;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function attributeRange(int $modifier): array
    {
        return [
            max(self::ATTRIBUTE_ABSOLUTE_MIN, self::ATTRIBUTE_BASE_MIN + $modifier),
            min(self::ATTRIBUTE_ABSOLUTE_MAX, self::ATTRIBUTE_BASE_MAX + $modifier),
        ];
    }

    private function raceAttributeModifiers(string $race): array
    {
        return self::RACE_ATTRIBUTE_MODIFIERS[$race] ?? [];
    }

    private function attributeLabel(string $attributeName): string
    {
        return self::ATTRIBUTE_LABELS[$attributeName] ?? $attributeName;
    }

    private function raceRequirements(string $race): array
    {
        return match ($race) {
            'Barbar' => [
                'skills' => ['Überleben' => 1, 'Intuition' => 1],
                'anySkills' => [[
                    'names' => ['Nahkampf', 'Fernkampf'],
                    'minimum' => 1,
                    'label' => 'Nahkampf oder Fernkampf mindestens auf 1',
                ]],
            ],
            'Guul' => [
                'skills' => ['Heimlichkeit' => 2, 'Intuition' => 1, 'Natürliche Waffen' => 1],
                'advantages' => ['Natürliche Waffen'],
                'disadvantages' => ['Primitiv', 'Gejagt'],
            ],
            'Hydrit' => [
                'skills' => ['Athletik' => 2, 'Bildung' => 1, 'Natürliche Waffen' => 1],
                'advantages' => ['Kiemen', 'Natürliche Waffen'],
                'disadvantages' => ['Anfälligkeit gegen Wahnsinn'],
            ],
            'Nosfera' => [
                'skills' => ['Intuition' => 2, 'Heimlichkeit' => 2],
                'advantages' => ['Nachtsicht'],
                'disadvantages' => ['Blutdurst', 'Lichtscheu', 'Gejagt'],
            ],
            'Taratze' => [
                'skills' => ['Intuition' => 2, 'Heimlichkeit' => 1, 'Überleben' => 1],
                'disadvantages' => ['Auffällig', 'Primitiv', 'Gejagt'],
            ],
            'Wulfane' => [
                'skills' => ['Intuition' => 1, 'Nahkampf' => 1],
                'disadvantages' => ['Ehrenkodex'],
            ],
            'Techno' => [
                'skills' => ['Bildung' => 3],
                'advantages' => ['High-Tech-Ausrüstung'],
                'disadvantages' => ['Tödliche Immunschwäche'],
            ],
            'Präkristofluu' => [
                'skills' => ['Beruf' => 3],
                'advantages' => ['High-Tech-Ausrüstung'],
            ],
            default => [],
        };
    }

    private function skillValue(array $skills, string $skillName): int
    {
        $value = null;
        $canonicalSkillName = $this->canonicalSkillName($skillName);
        $matchesSpecializationBase = $this->isSpecializableBaseSkill($canonicalSkillName);

        foreach ($skills as $skill) {
            $name = $this->canonicalSkillName((string) ($skill['name'] ?? ''));

            if ($name !== $canonicalSkillName
                && (! $matchesSpecializationBase || $this->skillBaseName($name) !== $canonicalSkillName)
            ) {
                continue;
            }

            if (! is_numeric($skill['value'] ?? null)) {
                continue;
            }

            $value = max($value ?? PHP_INT_MIN, (int) $skill['value']);
        }

        return $value ?? PHP_INT_MIN;
    }

    private function stringPayload(mixed $value): string
    {
        if (! is_scalar($value) && $value !== null) {
            return '';
        }

        return trim((string) $value);
    }

    private function attributesPayload(mixed $attributes): array
    {
        if (! is_array($attributes)) {
            return [];
        }

        $payload = [];

        foreach (self::ATTRIBUTE_KEYS as $key) {
            if (array_key_exists($key, $attributes)) {
                $payload[$key] = $this->stringPayload($attributes[$key]);
            }
        }

        return $payload;
    }

    private function skillsPayload(mixed $skills): array
    {
        if (! is_array($skills)) {
            return [];
        }

        $payload = [];

        foreach ($skills as $skill) {
            if (! is_array($skill)) {
                continue;
            }

            $name = $this->canonicalSkillName($this->stringPayload($skill['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $payload[] = [
                'name' => $name,
                'value' => $this->stringPayload($skill['value'] ?? ''),
            ];
        }

        return $payload;
    }

    private function canonicalSkillName(string $value): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', str_replace('_', ' ', $value)) ?? '');
        $normalized = preg_replace('/\s*:\s*/', ': ', $normalized) ?? $normalized;

        if ($normalized === '') {
            return '';
        }

        return self::SKILL_NAME_ALIASES[$normalized] ?? $normalized;
    }

    private function skillBaseName(string $skillName): string
    {
        $canonicalName = $this->canonicalSkillName($skillName);

        if (! str_contains($canonicalName, ':')) {
            return $canonicalName;
        }

        return $this->canonicalSkillName(Str::before($canonicalName, ':'));
    }

    private function skillSpecialization(string $skillName): ?string
    {
        $canonicalName = $this->canonicalSkillName($skillName);

        if (! str_contains($canonicalName, ':')) {
            return null;
        }

        return trim(Str::after($canonicalName, ':'));
    }

    private function listPayload(mixed $values): array
    {
        if (! is_array($values)) {
            $values = [$values];
        }

        $payload = [];

        foreach ($values as $value) {
            $normalized = $this->stringPayload($value);

            if ($normalized !== '') {
                $payload[] = $normalized;
            }
        }

        return array_values(array_unique($payload));
    }

    private function specialDetailsPayload(mixed $details): array
    {
        if (! is_array($details)) {
            return [];
        }

        $payload = [];

        foreach ($details as $key => $value) {
            $name = $this->stringPayload($key);
            $detail = $this->stringPayload($value);

            if ($name !== '' && $detail !== '') {
                $payload[$this->canonicalSpecialName($name)] = $detail;
            }
        }

        return $payload;
    }

    private function advantageCountsPayload(mixed $counts): array
    {
        if (! is_array($counts)) {
            return [];
        }

        $payload = [];

        foreach ($counts as $key => $value) {
            $name = $this->stringPayload($key);
            $rawValue = $this->stringPayload($value);

            if ($name === '') {
                continue;
            }

            $payload[$this->canonicalSpecialName($name)] = is_numeric($rawValue) ? (int) $rawValue : 0;
        }

        return $payload;
    }

    private function canonicalSpecialList(array $values): array
    {
        return array_values(array_unique(array_map(
            fn (string $value) => $this->canonicalSpecialName($value),
            $values,
        )));
    }

    private function canonicalSpecialMap(array $values): array
    {
        $payload = [];

        foreach ($values as $key => $value) {
            $payload[$this->canonicalSpecialName((string) $key)] = $value;
        }

        return $payload;
    }

    private function filterSpecialMapByNames(array $values, array $names): array
    {
        return array_intersect_key($values, array_flip($names));
    }

    private function canonicalSpecialName(string $value): string
    {
        $normalized = str_replace('_', ' ', $value);

        return self::SPECIAL_NAME_ALIASES[$normalized] ?? $normalized;
    }

    private function portraitPayload(Request $request): ?string
    {
        if ($request->hasFile('portrait') && $request->file('portrait')->isValid()) {
            return 'data:'.$request->file('portrait')->getMimeType().';base64,'.base64_encode($request->file('portrait')->get());
        }

        return $this->portraitDataUrlPayload($request->input('portrait_data_url'));
    }

    private function portraitDataUrlPayload(mixed $dataUrl): ?string
    {
        $dataUrl = $this->stringPayload($dataUrl);

        if ($dataUrl === '') {
            return null;
        }

        if (! preg_match('/^data:(image\/(?:png|jpeg|gif|webp|bmp));base64,([A-Za-z0-9+\/=]+)$/', $dataUrl, $matches)) {
            throw $this->portraitDataUrlValidationException();
        }

        $binary = base64_decode($matches[2], true);
        $imageInfo = $binary === false ? false : @getimagesizefromstring($binary);

        if (
            $binary === false
            || strlen($binary) > self::PORTRAIT_MAX_BYTES
            || $imageInfo === false
            || ($imageInfo['mime'] ?? null) !== $matches[1]
        ) {
            throw $this->portraitDataUrlValidationException();
        }

        return 'data:'.$matches[1].';base64,'.base64_encode($binary);
    }

    private function portraitDataUrlValidationException(): ValidationException
    {
        return ValidationException::withMessages([
            'portrait_data_url' => 'Das Porträt konnte nicht für den PDF-Export verarbeitet werden.',
        ]);
    }
}
