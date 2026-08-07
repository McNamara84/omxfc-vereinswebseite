<?php

namespace App\Services;

use App\Support\RpgCharEditorEquipment;
use App\Support\RpgCharEditorSpecialRules;
use App\Support\RpgCharEditorTraining;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelPdf\Facades\Pdf;

class RpgCharacterSheetService
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

    private const CHARACTER_NAME_MAX_CHARS = 255;

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

    private const TECHNO_SKILL_POOL_VALUES = ['Fahren', 'Feuerwaffen', 'Heiler', 'Pilot', 'Techniker', 'Wissenschaftler'];

    private const TECHNO_SKILL_POOL_POINTS = 12;

    private const PRAEKRISTOFLUU_SKILL_POOL_VALUES = ['Bildung', 'Fahren', 'Feuerwaffen', 'Pilot', 'Techniker', 'Wissenschaftler'];

    private const PRAEKRISTOFLUU_SKILL_POOL_POINTS = 12;

    private const BUNKERMENSCH_BONUS_SKILL_VALUES = ['Feuerwaffen', 'Pilot', 'Wissenschaftler'];

    private const MENSCH_21_BONUS_SKILL_VALUES = ['Bildung', 'Pilot', 'Techniker', 'Wissenschaftler'];

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

    private const SKILL_NAME_ALIASES = [
        'Ueberleben' => 'Überleben',
        'Natuerliche Waffen' => 'Natürliche Waffen',
        'Beruf: Kuenstler' => 'Beruf: Künstler',
        'Beruf: Viehzuechter' => 'Beruf: Viehzüchter',
        'Kunde: Kraeuter' => 'Kunde: Kräuter',
    ];

    private const LEGACY_FREE_ADVANTAGE_UNITS = 2;

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

    private const SPECIAL_DETAIL_MAX_ITEMS = 32;

    private const SPECIAL_DETAIL_MAX_CHARS = 255;

    private const ADVANTAGE_COUNT_MAX_ITEMS = 20;

    private const ADVANTAGE_COUNT_MAX_VALUE = 20;

    private const PORTRAIT_MAX_BYTES = 2_097_152;

    private const PORTRAIT_MAX_BASE64_CHARS = 2_796_204;

    private const PORTRAIT_DATA_URL_PREFIX_MAX_CHARS = 23;

    private const PORTRAIT_DATA_URL_MAX_CHARS = self::PORTRAIT_DATA_URL_PREFIX_MAX_CHARS + self::PORTRAIT_MAX_BASE64_CHARS;

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
            'specialSkills' => [],
        ];
    }

    public static function specialRuleConfig(): array
    {
        return RpgCharEditorSpecialRules::ruleConfig() + [
            'attributeRules' => self::attributeRuleConfig(),
            'skillRules' => self::skillRuleConfig(),
            'trainingRules' => RpgCharEditorTraining::ruleConfig(),
            'equipmentRules' => RpgCharEditorEquipment::ruleConfig(),
        ];
    }

    public function validatedPdfPayload(Request $request): array
    {
        $validCreationLevels = RpgCharEditorSpecialRules::validCreationLevels();
        $maximumSkillPoints = max(array_column(RpgCharEditorSpecialRules::CREATION_LEVELS, 'skillPoints'));

        $request->validate([
            'figurenstaerke' => 'required|integer|in:'.implode(',', $validCreationLevels),
            'portrait' => 'nullable|image|max:2048',
            'portrait_data_url' => 'nullable|string|max:'.self::PORTRAIT_DATA_URL_MAX_CHARS,
            'attribute_adjustments' => 'required|array:st,ge,ro,wi,wa,in,au',
            'attribute_adjustments.st' => 'required|integer|min:-1|max:1',
            'attribute_adjustments.ge' => 'required|integer|min:-1|max:1',
            'attribute_adjustments.ro' => 'required|integer|min:-1|max:1',
            'attribute_adjustments.wi' => 'required|integer|min:-1|max:1',
            'attribute_adjustments.wa' => 'required|integer|min:-1|max:1',
            'attribute_adjustments.in' => 'required|integer|min:-1|max:1',
            'attribute_adjustments.au' => 'required|integer|min:-1|max:1',
            'extra_ap_attribute' => 'nullable|string|in:'.implode(',', self::ATTRIBUTE_KEYS),
            'advantage_compensation_attributes' => 'nullable|array|max:'.RpgCharEditorSpecialRules::MAX_EXTRA_ADVANTAGE_UNITS,
            'advantage_compensation_attributes.*' => 'required|string|distinct|in:'.implode(',', self::ATTRIBUTE_KEYS),
            'negated_racial_disadvantages' => 'nullable|array|max:10',
            'negated_racial_disadvantages.*' => 'required|string|distinct|max:100',
            'advantage_effects' => 'nullable|array|max:10',
            'advantage_effects.*.name' => 'required|string|max:100',
            'advantage_effects.*.target' => 'nullable|string|max:100',
            'advantage_effects.*.justification' => 'nullable|string|max:'.self::SPECIAL_DETAIL_MAX_CHARS,
            'languages' => 'nullable|array|max:12',
            'languages.*' => 'required|string|distinct|max:100',
            'advantage_details' => 'nullable|array|max:'.self::SPECIAL_DETAIL_MAX_ITEMS,
            'advantage_details.*' => 'nullable|string|max:'.self::SPECIAL_DETAIL_MAX_CHARS,
            'disadvantage_details' => 'nullable|array|max:'.self::SPECIAL_DETAIL_MAX_ITEMS,
            'disadvantage_details.*' => 'nullable|string|max:'.self::SPECIAL_DETAIL_MAX_CHARS,
            'advantage_counts' => 'nullable|array|max:'.self::ADVANTAGE_COUNT_MAX_ITEMS,
            'advantage_counts.*' => 'nullable|integer|min:1|max:'.self::ADVANTAGE_COUNT_MAX_VALUE,
            'trainings' => 'nullable|array|max:'.RpgCharEditorTraining::MAX_TRAININGS,
            'trainings.*' => 'required|string|max:100',
            'training_allocations' => 'nullable|array|max:'.RpgCharEditorTraining::MAX_ALLOCATIONS,
            'training_allocations.*.training' => 'required|string|max:100',
            'training_allocations.*.skill' => 'required|string|max:255',
            'training_allocations.*.points' => 'required|integer|min:1|max:'.$maximumSkillPoints,
            'clothing' => 'required|string',
            'equipment_items' => 'required|array|max:'.RpgCharEditorEquipment::MAX_ITEMS,
            'equipment_items.*.id' => 'required|string',
            'equipment_items.*.quantity' => 'required|integer|min:1|max:'.RpgCharEditorEquipment::QUANTITY_MAX,
            'active_armor_id' => 'nullable|string|max:255',
            'active_shield_id' => 'nullable|string|max:255',
        ]);

        $character = $this->characterPayload($request);
        $creationLevel = (int) $request->input('figurenstaerke');
        $this->validateCharacterPayload($character);
        $this->validateCharacterSelection($character);
        $usesCreationV2 = true;
        $attributes = $this->attributesPayload($request->input('attributes', []));
        $skills = $this->skillsPayload($request->input('skills', []));
        $skillPools = [
            'techno' => $this->skillPoolPayload($request->input('techno_skill_points', []), self::TECHNO_SKILL_POOL_VALUES),
            'praekristofluu' => $this->skillPoolPayload($request->input('praekristofluu_skill_points', []), self::PRAEKRISTOFLUU_SKILL_POOL_VALUES),
        ];
        $cultureChoices = [
            'bunkermensch_bonus_skill' => $this->canonicalSkillName($this->stringPayload($request->input('bunkermensch_bonus_skill', ''))),
            'mensch_21_first_bonus_skill' => $this->canonicalSkillName($this->stringPayload($request->input('mensch_21_first_bonus_skill', ''))),
            'mensch_21_second_bonus_skill' => $this->canonicalSkillName($this->stringPayload($request->input('mensch_21_second_bonus_skill', ''))),
        ];
        $advantages = $this->canonicalSpecialList($this->listPayload($request->input('advantages', [])));
        $disadvantages = $this->canonicalSpecialList($this->listPayload($request->input('disadvantages', [])));
        $advantageDetails = $this->filterSpecialMapByNames($this->specialDetailsPayload($request->input('advantage_details', [])), $advantages);
        $disadvantageDetails = $this->filterSpecialMapByNames($this->specialDetailsPayload($request->input('disadvantage_details', [])), $disadvantages);
        $advantageCounts = $this->advantageCountsPayload($request->input('advantage_counts', []));
        $trainings = $this->trainingNamesPayload($request->input('trainings', []));
        $trainingAllocations = $this->trainingAllocationsPayload($request->input('training_allocations', []));
        $barbarAttributeBonus = $this->stringPayload($request->input('barbar_attribute_bonus', ''));
        $clothing = $this->stringPayload($request->input('clothing', ''));
        $equipmentItems = $this->equipmentItemsPayload($request->input('equipment_items', []));
        $activeArmorId = $this->stringPayload($request->input('active_armor_id', ''));
        $activeShieldId = $this->stringPayload($request->input('active_shield_id', ''));
        $languages = array_values(array_unique($this->listPayload($request->input('languages', []))));
        $creationEvaluation = null;

        if ($usesCreationV2) {
            $advantageEffects = $this->advantageEffectsPayload($request->input('advantage_effects', []));
            $creationEvaluation = (new RpgCharacterCreationEvaluator)->evaluate([
                'creation_level' => $creationLevel,
                'race' => $character['race'] ?? '',
                'culture' => $character['culture'] ?? '',
                'gender' => $character['gender'] ?? '',
                'barbar_attribute_bonus' => $barbarAttributeBonus,
                'attribute_adjustments' => $request->input('attribute_adjustments', []),
                'extra_ap_attribute' => $this->stringPayload($request->input('extra_ap_attribute', '')),
                'advantage_compensation_attributes' => $this->listPayload($request->input('advantage_compensation_attributes', [])),
                'negated_racial_disadvantages' => $this->canonicalSpecialList($this->listPayload($request->input('negated_racial_disadvantages', []))),
                'advantage_effects' => $advantageEffects,
                'advantages' => $advantages,
                'disadvantages' => $disadvantages,
            ]);

            if (! $creationEvaluation['valid']) {
                throw ValidationException::withMessages($creationEvaluation['errors']);
            }

            foreach (self::ATTRIBUTE_KEYS as $attribute) {
                $submitted = array_key_exists($attribute, $attributes) ? (string) $attributes[$attribute] : '0';
                $calculated = (string) $creationEvaluation['attributes']['final'][$attribute];
                if ($submitted !== $calculated) {
                    throw ValidationException::withMessages([
                        'attributes' => "Der übermittelte Endwert für {$attribute} stimmt nicht mit der serverseitigen Erschaffungsberechnung überein.",
                    ]);
                }
                $attributes[$attribute] = $calculated;
            }

            $advantages = $creationEvaluation['effective_advantages'];
            $disadvantages = $creationEvaluation['effective_disadvantages'];
            $disadvantageDetails = $this->filterSpecialMapByNames($disadvantageDetails, $disadvantages);
            [$advantageDetails, $advantageCounts] = $this->legacyAdvantageMaps($creationEvaluation['advantage_effects']);
        }

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
            $skillPools,
            $cultureChoices,
            $trainings,
            $trainingAllocations,
            $creationEvaluation,
        );
        $this->validateLanguages($skills, $advantages, $languages, $usesCreationV2);
        $this->validateEquipmentRules($clothing, $equipmentItems, $advantages, $activeArmorId, $activeShieldId, $usesCreationV2);

        $payload = [
            'rules' => $usesCreationV2 ? [
                'edition' => 2007,
                'creation_level' => $creationLevel,
                'payload_version' => 2,
            ] : null,
            'creation' => $usesCreationV2 ? [
                'level_rules' => $creationEvaluation['level_rules'],
                'attribute_adjustments' => $creationEvaluation['attributes']['creation_adjustments'],
                'attribute_race_modifiers' => $creationEvaluation['attributes']['race_modifiers'],
                'attribute_advantage_bonuses' => $creationEvaluation['attributes']['advantage_bonuses'],
                'attribute_budget' => $creationEvaluation['attribute_budget'],
                'advantage_budget' => $creationEvaluation['advantage_budget'],
                'extra_ap_attribute' => $this->stringPayload($request->input('extra_ap_attribute', '')),
                'advantage_compensation_attributes' => $this->listPayload($request->input('advantage_compensation_attributes', [])),
                'negated_racial_disadvantages' => $creationEvaluation['negated_racial_disadvantages'],
            ] : null,
            'character' => $character,
            'attributes' => $attributes,
            'skills' => $skills,
            'advantages' => $advantages,
            'disadvantages' => $disadvantages,
            'advantage_details' => $advantageDetails,
            'disadvantage_details' => $disadvantageDetails,
            'advantage_counts' => $advantageCounts,
            'advantage_effects' => $usesCreationV2 ? $creationEvaluation['advantage_effects'] : null,
            'languages' => $languages,
            'trainings' => $trainings,
            'training_allocations' => $trainingAllocations,
            'equipment' => $this->equipmentExportPayload($clothing, $equipmentItems, $character['equipment'], $activeArmorId, $activeShieldId),
            'portrait' => $this->portraitPayload($request),
        ];

        if (! $usesCreationV2) {
            unset($payload['rules'], $payload['creation'], $payload['advantage_effects']);
        }

        return $payload;
    }

    public function characterSheetPdfResponse(array $data) // @pest-ignore-profanity -- RPG domain term.
    {
        $character = is_array($data['character'] ?? null) ? $data['character'] : [];
        $name = Str::slug((string) ($character['character_name'] ?? 'charakter')) ?: 'charakter';
        $combat = (new RpgCharacterCombatCalculator)->calculate($data);
        $data['combat'] = $combat;
        $data['sheet'] = (new RpgCharacterSheetPresenter)->present($data, $combat); // @pest-ignore-profanity -- Structured character-sheet payload key.

        return Pdf::view('rpg.char-sheet', $data) /* @pest-ignore-profanity -- Existing RPG view path. */
            ->driver('dompdf')
            ->format('a4')
            ->margins(4, 4, 4, 4)
            ->inline($name.'.pdf');
    }

    private function characterPayload(Request $request): array
    {
        $character = [];

        foreach (self::CHARACTER_KEYS as $key) {
            $character[$key] = $this->stringPayload($request->input($key, ''));
        }

        return $character;
    }

    private function validateCharacterPayload(array $character): void
    {
        if (Str::length($character['character_name'] ?? '') > self::CHARACTER_NAME_MAX_CHARS) {
            throw ValidationException::withMessages([
                'character_name' => 'Der Charaktername darf maximal '.self::CHARACTER_NAME_MAX_CHARS.' Zeichen lang sein.',
            ]);
        }
    }

    private function validateCharacterSelection(array $character): void
    {
        $race = $character['race'] ?? '';
        $culture = $character['culture'] ?? '';
        $gender = $character['gender'] ?? '';

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
    }

    private function validateCharacterRules(array $character, array $attributes, array $skills, array $advantages, array $disadvantages, array $advantageDetails = [], array $disadvantageDetails = [], array $advantageCounts = [], string $barbarAttributeBonus = '', array $skillPools = [], array $cultureChoices = [], array $trainings = [], array $trainingAllocations = [], ?array $creationEvaluation = null): void
    {
        $race = $character['race'] ?? '';
        $culture = $character['culture'] ?? '';
        $gender = $character['gender'] ?? '';
        $canonicalAdvantages = $this->canonicalSpecialList($advantages);
        $canonicalDisadvantages = $this->canonicalSpecialList($disadvantages);
        $canonicalAdvantageDetails = $this->canonicalSpecialMap($advantageDetails);
        $canonicalDisadvantageDetails = $this->canonicalSpecialMap($disadvantageDetails);
        $canonicalAdvantageCounts = $this->canonicalSpecialMap($advantageCounts);
        $levelRules = is_array($creationEvaluation['level_rules'] ?? null)
            ? $creationEvaluation['level_rules']
            : RpgCharEditorSpecialRules::creationLevel(RpgCharEditorSpecialRules::DEFAULT_CREATION_LEVEL);
        $skillPoints = (int) $levelRules['skillPoints'];
        $skillMax = (int) $levelRules['skillMax'];

        $this->validateSpecialLists($canonicalAdvantages, $canonicalDisadvantages);
        $this->validateAdvantageCounts($canonicalAdvantages, $canonicalAdvantageCounts);

        if ($culture === 'Volk der 13 Inseln'
            && $gender === 'weiblich'
            && ! in_array('Psychische Kraft', $canonicalAdvantages, true)) {
            throw ValidationException::withMessages([
                'advantages' => 'Weibliche Charaktere aus dem Volk der 13 Inseln müssen Psychische Kraft wählen.',
            ]);
        }

        if ($creationEvaluation === null) {
            $this->validateAttributes($race, $attributes, $barbarAttributeBonus);
        } elseif ($this->exactSkillValue($skills, 'Natürliche Waffen') !== null) {
            throw ValidationException::withMessages([
                'skills' => 'Natürliche Waffen ist keine eigene Fertigkeit; Angriffe verwenden Nahkampf mit ST oder GE.',
            ]);
        }
        $this->validateSkillRules($race, $culture, $skills, $canonicalAdvantages, $skillPools, $cultureChoices, $skillPoints, $skillMax);
        $this->validateTrainingRules(
            $race,
            $culture,
            $skills,
            $canonicalAdvantages,
            $skillPools,
            $cultureChoices,
            $trainings,
            $trainingAllocations,
            $skillPoints,
            $skillMax,
        );
        $this->validateRaceRequirements(
            $race,
            $attributes,
            $skills,
            $canonicalAdvantages,
            $canonicalDisadvantages,
            $creationEvaluation['negated_racial_disadvantages'] ?? [],
            $creationEvaluation !== null,
        );
        $this->validateCultureRequirements($culture, $skills);
        if ($creationEvaluation === null) {
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
        } else {
            $this->validateSpecialDetails(
                $race,
                $canonicalAdvantages,
                $canonicalDisadvantages,
                $canonicalAdvantageDetails,
                $canonicalDisadvantageDetails,
            );
        }
    }

    private function validateSkillRules(
        string $race,
        string $culture,
        array $skills,
        array $advantages,
        array $skillPools = [],
        array $cultureChoices = [],
        int $skillPoints = self::BASE_SKILL_POINTS,
        int $skillMax = self::SKILL_BASE_MAX,
    ): void {
        $seen = [];
        $this->validateCultureChoiceInputs($culture, $cultureChoices);
        $grants = $this->freeSkillGrants($race, $culture, $skills, $skillPools, $cultureChoices, $skillMax);

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
            $grantValue = $this->skillGrantValue($grants, $name) ?? self::SKILL_BASE_MIN;
            $maximumFinalValue = max($skillMax, $grantValue);

            if ($skillValue < self::SKILL_BASE_MIN || $skillValue > $maximumFinalValue) {
                throw ValidationException::withMessages([
                    'skills' => "Der Fertigkeitswert für {$name} muss im Bereich von ".self::SKILL_BASE_MIN." bis {$maximumFinalValue} liegen.",
                ]);
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

        if ($this->skillPointsUsed($skills, $grants) > $skillPoints) {
            throw ValidationException::withMessages([
                'skills' => 'Die gewählten Fertigkeiten überschreiten die verfügbaren Fertigkeitspunkte.',
            ]);
        }
    }

    private function validateTrainingRules(
        string $race,
        string $culture,
        array $skills,
        array $advantages,
        array $skillPools,
        array $cultureChoices,
        array $trainings,
        array $allocations,
        int $skillPoints = self::BASE_SKILL_POINTS,
        int $skillMax = self::SKILL_BASE_MAX,
    ): void {
        $definitions = RpgCharEditorTraining::map();
        $seenTrainings = [];
        $totalTrainingCost = 0;

        foreach ($trainings as $training) {
            if (! array_key_exists($training, $definitions)) {
                throw ValidationException::withMessages([
                    'trainings' => "Die Ausbildung {$training} ist laut Regelwerk nicht erlaubt.",
                ]);
            }

            if (isset($seenTrainings[$training])) {
                throw ValidationException::withMessages([
                    'trainings' => "Die Ausbildung {$training} wurde mehrfach gewählt.",
                ]);
            }

            $seenTrainings[$training] = true;
            $totalTrainingCost += (int) $definitions[$training]['cost'];

            foreach ($definitions[$training]['requiredAdvantages'] ?? [] as $requiredAdvantage) {
                if (! in_array($requiredAdvantage, $advantages, true)) {
                    throw ValidationException::withMessages([
                        'trainings' => "Die Ausbildung {$training} benötigt den Vorteil {$requiredAdvantage}.",
                    ]);
                }
            }
        }

        if ($totalTrainingCost > $skillPoints) {
            throw ValidationException::withMessages([
                'trainings' => 'Die gewählten Ausbildungen kosten mehr Fertigkeitspunkte als verfügbar sind.',
            ]);
        }

        $pointsByTraining = array_fill_keys($trainings, 0);
        $claimedPaidPoints = [];
        $seenAllocations = [];

        foreach ($allocations as $allocation) {
            $training = (string) ($allocation['training'] ?? '');
            $skillName = $this->canonicalSkillName((string) ($allocation['skill'] ?? ''));
            $points = (int) ($allocation['points'] ?? 0);

            if (! isset($seenTrainings[$training])) {
                throw ValidationException::withMessages([
                    'training_allocations' => "Für die nicht gewählte Ausbildung {$training} wurden Fertigkeitspunkte übermittelt.",
                ]);
            }

            if ($points < 1) {
                throw ValidationException::withMessages([
                    'training_allocations' => 'Ausbildungspunkte müssen positive ganze Zahlen sein.',
                ]);
            }

            $baseName = $this->skillBaseName($skillName);

            if (! in_array($baseName, $definitions[$training]['skills'], true)
                || ! $this->isAllowedSkillName($skillName, $race)) {
                throw ValidationException::withMessages([
                    'training_allocations' => "Die Fertigkeit {$skillName} gehört nicht zur Ausbildung {$training}.",
                ]);
            }

            $allocationKey = $training."\0".$skillName;

            if (isset($seenAllocations[$allocationKey])) {
                throw ValidationException::withMessages([
                    'training_allocations' => "Die Fertigkeit {$skillName} wurde für {$training} mehrfach zugeordnet.",
                ]);
            }

            $seenAllocations[$allocationKey] = true;
            $pointsByTraining[$training] += $points;
            $claimedPaidPoints[$skillName] = ($claimedPaidPoints[$skillName] ?? 0) + $points;
        }

        foreach ($trainings as $training) {
            $expected = (int) $definitions[$training]['cost'];
            $actual = (int) ($pointsByTraining[$training] ?? 0);

            if ($actual !== $expected) {
                throw ValidationException::withMessages([
                    'training_allocations' => "Für die Ausbildung {$training} müssen genau {$expected} FP verteilt werden; übermittelt wurden {$actual}.",
                ]);
            }
        }

        if ($trainings === [] && $allocations !== []) {
            throw ValidationException::withMessages([
                'training_allocations' => 'Ausbildungspunkte dürfen nur mit einer gewählten Ausbildung übermittelt werden.',
            ]);
        }

        $grants = $this->freeSkillGrants($race, $culture, $skills, $skillPools, $cultureChoices, $skillMax);

        foreach ($claimedPaidPoints as $skillName => $claimedPoints) {
            $finalValue = $this->exactSkillValue($skills, $skillName);
            $grantValue = $this->skillGrantValue($grants, $skillName) ?? self::SKILL_BASE_MIN;

            if ($finalValue === null || $finalValue < $grantValue + $claimedPoints) {
                throw ValidationException::withMessages([
                    'training_allocations' => "Die Ausbildungspunkte für {$skillName} sind nicht im übermittelten Fertigkeitswert enthalten.",
                ]);
            }

            if ($claimedPoints > max($skillMax - $grantValue, 0)) {
                throw ValidationException::withMessages([
                    'training_allocations' => "Die Ausbildungspunkte für {$skillName} überschreiten den erlaubten Fertigkeitswert; verteile die überschüssigen Punkte anderweitig.",
                ]);
            }
        }

        if ($totalTrainingCost > $this->skillPointsUsed($skills, $grants)) {
            throw ValidationException::withMessages([
                'training_allocations' => 'Die Ausbildungspunkte sind nicht vollständig in den bezahlten Fertigkeitswerten enthalten.',
            ]);
        }
    }

    private function isAllowedSkillName(string $name, string $race): bool
    {
        if (in_array($name, self::SKILL_VALUES, true)) {
            return true;
        }

        $baseName = $this->skillBaseName($name);
        $detail = $this->skillSpecialization($name);

        return $detail !== null
            && $detail !== ''
            && in_array($baseName, self::SKILL_VALUES, true)
            && (bool) (self::SKILL_RULES[$baseName]['specializable'] ?? false);
    }

    private function freeSkillGrants(
        string $race,
        string $culture,
        array $skills,
        array $skillPools = [],
        array $cultureChoices = [],
        int $skillMax = self::SKILL_BASE_MAX,
    ): array {
        $raceGrants = [];
        $cultureGrants = [];
        $raceSkillPool = $this->skillPoolForRace($race, $skills, $skillPools, $skillMax);

        $this->validateRaceSkillPool($race, $skills, $raceSkillPool, $skillMax);
        $this->addRequirementSkillGrants($raceGrants, $this->raceRequirements($race), $skills);
        $this->addSkillPoolGrants($raceGrants, $raceSkillPool);
        $this->addRequirementSkillGrants($cultureGrants, $this->cultureRequirementsForGrants($culture, $cultureChoices), $skills);
        $this->addCultureChoiceSkillGrants($cultureGrants, $culture, $cultureChoices);

        $grants = $raceGrants;
        foreach ($cultureGrants as $skillName => $value) {
            $grants[$skillName] = ($grants[$skillName] ?? self::SKILL_BASE_MIN) + $value;
        }

        return $grants;
    }

    private function skillPoolForRace(string $race, array $skills, array $skillPools, int $skillMax = self::SKILL_BASE_MAX): array
    {
        return match ($race) {
            'Techno' => $this->normalizedSkillPool(
                $skillPools['techno'] ?? [],
                self::TECHNO_SKILL_POOL_VALUES,
                self::TECHNO_SKILL_POOL_POINTS,
                $skills,
                $skillMax,
            ),
            'Präkristofluu' => $this->normalizedSkillPool(
                $skillPools['praekristofluu'] ?? [],
                self::PRAEKRISTOFLUU_SKILL_POOL_VALUES,
                self::PRAEKRISTOFLUU_SKILL_POOL_POINTS,
                $skills,
                $skillMax,
            ),
            default => [],
        };
    }

    private function normalizedSkillPool(array $submittedPool, array $skillNames, int $poolPoints, array $skills, int $skillMax = self::SKILL_BASE_MAX): array
    {
        if ($submittedPool === []) {
            return $this->inferSkillPoolFromSkills($skills, $skillNames, $poolPoints, $skillMax);
        }

        $pool = [];

        foreach ($skillNames as $skillName) {
            $pool[$skillName] = $submittedPool[$skillName] ?? 0;
        }

        return $pool;
    }

    private function inferSkillPoolFromSkills(array $skills, array $skillNames, int $poolPoints, int $skillMax = self::SKILL_BASE_MAX): array
    {
        $pool = array_fill_keys($skillNames, 0);
        $remaining = $poolPoints;

        foreach ($skillNames as $skillName) {
            if ($remaining <= 0) {
                break;
            }

            $skillValue = min(max($this->skillValue($skills, $skillName), 0), $skillMax);
            $grantValue = min($skillValue, $remaining);
            $pool[$skillName] = $grantValue;
            $remaining -= $grantValue;
        }

        return $pool;
    }

    private function validateRaceSkillPool(string $race, array $skills, array $skillPool, int $skillMax = self::SKILL_BASE_MAX): void
    {
        $expectedPoolPoints = match ($race) {
            'Techno' => self::TECHNO_SKILL_POOL_POINTS,
            'Präkristofluu' => self::PRAEKRISTOFLUU_SKILL_POOL_POINTS,
            default => null,
        };

        if ($expectedPoolPoints === null) {
            return;
        }

        $sum = 0;

        foreach ($skillPool as $skillName => $value) {
            if (! is_int($value)) {
                throw ValidationException::withMessages([
                    'skills' => "Die Rassenpunkte für {$skillName} müssen als ganze Zahl übermittelt werden.",
                ]);
            }

            if ($value < self::SKILL_BASE_MIN || $value > $skillMax) {
                throw ValidationException::withMessages([
                    'skills' => "Die Rassenpunkte für {$skillName} müssen im Bereich von ".self::SKILL_BASE_MIN." bis {$skillMax} liegen.",
                ]);
            }

            if ($value > 0 && $this->skillValue($skills, (string) $skillName) < $value) {
                throw ValidationException::withMessages([
                    'skills' => "Die Rassenpunkte für {$skillName} dürfen den Fertigkeitswert nicht überschreiten.",
                ]);
            }

            $sum += $value;
        }

        if ($sum !== $expectedPoolPoints) {
            $label = $race === 'Techno' ? 'Techno-Rassenpunkte' : 'Präkristofluu-Rassenpunkte';

            throw ValidationException::withMessages([
                'skills' => "Die {$label} müssen genau {$expectedPoolPoints} Punkte ergeben.",
            ]);
        }
    }

    private function addSkillPoolGrants(array &$grants, array $skillPool): void
    {
        foreach ($skillPool as $skillName => $value) {
            if (is_int($value) && $value > 0) {
                $this->setSkillGrant($grants, (string) $skillName, $value);
            }
        }
    }

    private function cultureRequirementsForGrants(string $culture, array $cultureChoices): array
    {
        $requirements = $this->cultureRequirements($culture);

        if ($culture === 'Bunkermensch'
            && $this->selectedCultureChoice($cultureChoices, 'bunkermensch_bonus_skill', self::BUNKERMENSCH_BONUS_SKILL_VALUES) !== null) {
            unset($requirements['anySkills']);
        }

        if ($culture === 'Mensch des 21. Jahrhunderts'
            && ($this->selectedCultureChoice($cultureChoices, 'mensch_21_first_bonus_skill', self::MENSCH_21_BONUS_SKILL_VALUES) !== null
                || $this->selectedCultureChoice($cultureChoices, 'mensch_21_second_bonus_skill', self::MENSCH_21_BONUS_SKILL_VALUES) !== null)) {
            unset($requirements['countSkills']);
        }

        return $requirements;
    }

    private function addCultureChoiceSkillGrants(array &$grants, string $culture, array $cultureChoices): void
    {
        if ($culture === 'Bunkermensch') {
            $skillName = $this->selectedCultureChoice($cultureChoices, 'bunkermensch_bonus_skill', self::BUNKERMENSCH_BONUS_SKILL_VALUES);

            if ($skillName !== null) {
                $this->setAdditiveChoiceGrant($grants, $skillName);
            }
        }

        if ($culture !== 'Mensch des 21. Jahrhunderts') {
            return;
        }

        $skillNames = array_values(array_unique(array_filter([
            $this->selectedCultureChoice($cultureChoices, 'mensch_21_first_bonus_skill', self::MENSCH_21_BONUS_SKILL_VALUES),
            $this->selectedCultureChoice($cultureChoices, 'mensch_21_second_bonus_skill', self::MENSCH_21_BONUS_SKILL_VALUES),
        ])));

        foreach ($skillNames as $skillName) {
            $this->setAdditiveChoiceGrant($grants, $skillName);
        }
    }

    private function setAdditiveChoiceGrant(array &$grants, string $skillName): void
    {
        $currentGrant = $this->skillGrantValue($grants, $skillName) ?? self::SKILL_BASE_MIN;
        $this->setSkillGrant($grants, $skillName, $currentGrant + 1);
    }

    private function selectedCultureChoice(array $cultureChoices, string $key, array $allowedSkills): ?string
    {
        $skillName = $this->canonicalSkillName((string) ($cultureChoices[$key] ?? ''));

        return in_array($skillName, $allowedSkills, true) ? $skillName : null;
    }

    private function validateCultureChoiceInputs(string $culture, array $cultureChoices): void
    {
        $bunkermenschBonusSkill = $this->canonicalSkillName((string) ($cultureChoices['bunkermensch_bonus_skill'] ?? ''));

        if ($culture === 'Bunkermensch'
            && $bunkermenschBonusSkill !== ''
            && ! in_array($bunkermenschBonusSkill, self::BUNKERMENSCH_BONUS_SKILL_VALUES, true)) {
            throw ValidationException::withMessages([
                'skills' => 'Der Bunkermensch-Zusatzbonus muss eine erlaubte Fertigkeit verwenden.',
            ]);
        }

        if ($culture !== 'Mensch des 21. Jahrhunderts') {
            return;
        }

        $first = $this->canonicalSkillName((string) ($cultureChoices['mensch_21_first_bonus_skill'] ?? ''));
        $second = $this->canonicalSkillName((string) ($cultureChoices['mensch_21_second_bonus_skill'] ?? ''));

        foreach ([$first, $second] as $skillName) {
            if ($skillName !== '' && ! in_array($skillName, self::MENSCH_21_BONUS_SKILL_VALUES, true)) {
                throw ValidationException::withMessages([
                    'skills' => 'Die Boni für Mensch des 21. Jahrhunderts müssen erlaubte Fertigkeiten verwenden.',
                ]);
            }
        }

        if ($first !== '' && $first === $second) {
            throw ValidationException::withMessages([
                'skills' => 'Die Boni für Mensch des 21. Jahrhunderts müssen zwei verschiedene Fertigkeiten verwenden.',
            ]);
        }
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

    private function validateRaceRequirements(string $race, array $attributes, array $skills, array $advantages, array $disadvantages, array $negatedDisadvantages = [], bool $creationV2 = false): void
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
            if (in_array($disadvantage, $negatedDisadvantages, true)) {
                continue;
            }
            if (! in_array($disadvantage, $disadvantages, true)) {
                throw ValidationException::withMessages([
                    'disadvantages' => "Die Rasse {$race} benötigt den Nachteil {$disadvantage}.",
                ]);
            }
        }

        foreach ($requirements['skills'] ?? [] as $skillName => $minimumValue) {
            if ($creationV2 && $skillName === 'Natürliche Waffen') {
                continue;
            }
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
        $advantageRules = RpgCharEditorSpecialRules::advantages();
        $disadvantageRules = RpgCharEditorSpecialRules::disadvantages();

        foreach ($advantages as $advantage) {
            if (! array_key_exists($advantage, $advantageRules)) {
                throw ValidationException::withMessages([
                    'advantages' => "Der Vorteil {$advantage} ist laut Regelwerk nicht erlaubt.",
                ]);
            }
        }

        foreach ($disadvantages as $disadvantage) {
            if (! array_key_exists($disadvantage, $disadvantageRules)) {
                throw ValidationException::withMessages([
                    'disadvantages' => "Der Nachteil {$disadvantage} ist laut Regelwerk nicht erlaubt.",
                ]);
            }
        }
    }

    private function validateAdvantageCounts(array $advantages, array $advantageCounts): void
    {
        $advantageRules = RpgCharEditorSpecialRules::advantages();

        foreach ($advantageCounts as $advantage => $count) {
            if (! array_key_exists($advantage, $advantageRules)) {
                throw ValidationException::withMessages([
                    'advantages' => "Der Vorteil {$advantage} ist laut Regelwerk nicht erlaubt.",
                ]);
            }

            if (! in_array($advantage, RpgCharEditorSpecialRules::ruleConfig()['repeatableAdvantages'], true)) {
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

        if ($cost > self::LEGACY_FREE_ADVANTAGE_UNITS) {
            throw ValidationException::withMessages([
                'advantages' => 'Die gewählten Vorteile überschreiten die verfügbaren Vorteilspunkte.',
            ]);
        }

        if (count($disadvantages) < $cost) {
            throw ValidationException::withMessages([
                'disadvantages' => 'Für die gewählten Vorteile müssen ausreichend Nachteile gewählt werden.',
            ]);
        }

        foreach (RpgCharEditorSpecialRules::advantages() as $advantage => $rule) {
            if (in_array($advantage, $advantages, true)
                && ! in_array($advantage, $lockedAdvantages, true)
                && ($rule['requires_detail'] || $rule['requires_justification'])
                && ($advantageDetails[$advantage] ?? '') === '') {
                throw ValidationException::withMessages([
                    'advantage_details' => "Für den Vorteil {$advantage} muss eine nähere Angabe gemacht werden.",
                ]);
            }
        }

        foreach (RpgCharEditorSpecialRules::disadvantages() as $disadvantage => $rule) {
            if (in_array($disadvantage, $disadvantages, true)
                && ! in_array($disadvantage, $lockedDisadvantages, true)
                && $rule['requires_detail']
                && ($disadvantageDetails[$disadvantage] ?? '') === '') {
                throw ValidationException::withMessages([
                    'disadvantage_details' => "Für den Nachteil {$disadvantage} muss eine nähere Angabe gemacht werden.",
                ]);
            }
        }
    }

    private function validateSpecialDetails(
        string $race,
        array $advantages,
        array $disadvantages,
        array $advantageDetails,
        array $disadvantageDetails,
    ): void {
        $lockedAdvantages = $this->raceRequirements($race)['advantages'] ?? [];
        $rules = RpgCharEditorSpecialRules::ruleConfig();

        foreach ($rules['advantageDetailRequired'] as $advantage) {
            if (in_array($advantage, $advantages, true)
                && ! in_array($advantage, $lockedAdvantages, true)
                && ($advantageDetails[$advantage] ?? '') === '') {
                throw ValidationException::withMessages([
                    'advantage_effects' => "Für den Vorteil {$advantage} muss eine nähere Angabe gemacht werden.",
                ]);
            }
        }

        foreach ($rules['disadvantageDetailRequired'] as $disadvantage) {
            if (in_array($disadvantage, $disadvantages, true)
                && ! in_array($disadvantage, $this->lockedDisadvantages($race), true)
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

            $rule = RpgCharEditorSpecialRules::advantages()[$advantage] ?? ['cost' => 1, 'repeat' => 'none'];
            $baseCost = (int) $rule['cost'];
            $count = $rule['repeat'] !== 'none'
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
                'skills' => ['Heimlichkeit' => 2, 'Intuition' => 1],
                'advantages' => ['Natürliche Waffen'],
                'disadvantages' => ['Primitiv', 'Gejagt'],
            ],
            'Hydrit' => [
                'skills' => ['Athletik' => 2, 'Bildung' => 1],
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

    private function exactSkillValue(array $skills, string $skillName): ?int
    {
        $canonicalSkillName = $this->canonicalSkillName($skillName);

        foreach ($skills as $skill) {
            if ($this->canonicalSkillName((string) ($skill['name'] ?? '')) !== $canonicalSkillName
                || ! is_numeric($skill['value'] ?? null)) {
                continue;
            }

            return (int) $skill['value'];
        }

        return null;
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

    private function skillPoolPayload(mixed $skillPoints, array $allowedSkills): array
    {
        if (! is_array($skillPoints)) {
            return [];
        }

        $payload = [];

        foreach ($skillPoints as $key => $value) {
            $skillName = $this->canonicalSkillName($this->stringPayload($key));

            if (! in_array($skillName, $allowedSkills, true)) {
                continue;
            }

            $rawValue = $this->stringPayload($value);
            $payload[$skillName] = preg_match('/^-?\d+$/', $rawValue) ? (int) $rawValue : $rawValue;
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

    private function advantageEffectsPayload(mixed $effects): array
    {
        if (! is_array($effects)) {
            return [];
        }

        $payload = [];
        foreach ($effects as $effect) {
            if (! is_array($effect)) {
                continue;
            }

            $name = $this->canonicalSpecialName($this->stringPayload($effect['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $payload[] = [
                'name' => $name,
                'target' => $this->stringPayload($effect['target'] ?? ''),
                'justification' => $this->stringPayload($effect['justification'] ?? ''),
            ];
        }

        return $payload;
    }

    /**
     * @return array{0: array<string, string>, 1: array<string, int>}
     */
    private function legacyAdvantageMaps(array $effects): array
    {
        $details = [];
        $counts = [];
        $repeatable = RpgCharEditorSpecialRules::ruleConfig()['repeatableAdvantages'];

        foreach ($effects as $effect) {
            $name = (string) ($effect['name'] ?? '');
            if ($name === '') {
                continue;
            }

            if (in_array($name, $repeatable, true)) {
                $counts[$name] = ($counts[$name] ?? 0) + 1;
            }

            $parts = array_values(array_filter([
                (string) ($effect['target'] ?? ''),
                (string) ($effect['justification'] ?? ''),
            ], static fn (string $part): bool => $part !== ''
                && $part !== 'Rasse'
                && ! preg_match('/^Figurenstärke [1-5]$/u', $part)));
            if ($parts !== []) {
                $details[$name] = implode(' – ', $parts);
            }
        }

        return [$details, $counts];
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

    private function trainingNamesPayload(mixed $trainings): array
    {
        if (! is_array($trainings)) {
            return [];
        }

        $payload = [];

        foreach ($trainings as $training) {
            $name = $this->stringPayload($training);

            if ($name !== '') {
                $payload[] = $name;
            }
        }

        return $payload;
    }

    private function trainingAllocationsPayload(mixed $allocations): array
    {
        if (! is_array($allocations)) {
            return [];
        }

        $payload = [];

        foreach ($allocations as $allocation) {
            if (! is_array($allocation)) {
                continue;
            }

            $training = $this->stringPayload($allocation['training'] ?? '');
            $skill = $this->canonicalSkillName($this->stringPayload($allocation['skill'] ?? ''));
            $rawPoints = $this->stringPayload($allocation['points'] ?? '');

            if ($training === '' || $skill === '' || ! preg_match('/^\d+$/', $rawPoints)) {
                continue;
            }

            $payload[] = [
                'training' => $training,
                'skill' => $skill,
                'points' => (int) $rawPoints,
            ];
        }

        return $payload;
    }

    private function equipmentItemsPayload(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $payload = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $id = $this->stringPayload($item['id'] ?? '');
            $rawQuantity = $this->stringPayload($item['quantity'] ?? '');

            if ($id === '' || ! preg_match('/^\d+$/', $rawQuantity)) {
                continue;
            }

            $payload[$id] = ($payload[$id] ?? 0) + max(0, (int) $rawQuantity);
        }

        return array_values(array_map(
            fn (string $id, int $quantity): array => ['id' => $id, 'quantity' => $quantity],
            array_keys($payload),
            $payload,
        ));
    }

    private function validateEquipmentRules(string $clothing, array $equipmentItems, array $advantages, string $activeArmorId = '', string $activeShieldId = '', bool $creationV2 = false): void
    {
        $clothingMap = RpgCharEditorEquipment::clothingMap();
        $itemMap = RpgCharEditorEquipment::itemMap();

        if (! array_key_exists($clothing, $clothingMap)) {
            throw ValidationException::withMessages([
                'clothing' => 'Die Kleidung muss aus dem Ausrüstungskapitel gewählt werden.',
            ]);
        }

        $total = 0;
        $highTechTotal = 0;
        $hasHighTechAdvantage = in_array(RpgCharEditorEquipment::HIGH_TECH_ADVANTAGE, $advantages, true);

        foreach ($equipmentItems as $equipmentItem) {
            $id = (string) ($equipmentItem['id'] ?? '');
            $quantity = (int) ($equipmentItem['quantity'] ?? 0);

            if (! array_key_exists($id, $itemMap)) {
                throw ValidationException::withMessages([
                    'equipment_items' => 'Die Ausrüstung enthält einen Gegenstand, der laut Regelwerk nicht erlaubt ist.',
                ]);
            }

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    'equipment_items' => 'Die Anzahl jedes Ausrüstungsgegenstands muss mindestens 1 sein.',
                ]);
            }

            $item = $itemMap[$id];

            if (RpgCharEditorEquipment::countsTowardLimit($item)) {
                $total += $quantity;
            }

            if (! RpgCharEditorEquipment::requiresHighTechAdvantage($item)) {
                continue;
            }

            if (! $hasHighTechAdvantage) {
                throw ValidationException::withMessages([
                    'equipment_items' => 'High-Tech- oder Techno-Gegenstände dürfen nur mit dem Vorteil High-Tech-Ausrüstung gewählt werden.',
                ]);
            }

            $highTechTotal += $quantity;
        }

        if ($total !== RpgCharEditorEquipment::ITEM_LIMIT) {
            throw ValidationException::withMessages([
                'equipment_items' => 'Zu Beginn müssen genau '.RpgCharEditorEquipment::ITEM_LIMIT.' gezählte Ausrüstungsgegenstände gewählt werden.',
            ]);
        }

        if ($highTechTotal > RpgCharEditorEquipment::HIGH_TECH_ITEM_LIMIT) {
            throw ValidationException::withMessages([
                'equipment_items' => 'Mit High-Tech-Ausrüstung dürfen höchstens '.RpgCharEditorEquipment::HIGH_TECH_ITEM_LIMIT.' High-Tech- oder Techno-Gegenstände gewählt werden.',
            ]);
        }

        if ($creationV2 && $hasHighTechAdvantage && $highTechTotal !== RpgCharEditorEquipment::HIGH_TECH_ITEM_LIMIT) {
            throw ValidationException::withMessages([
                'equipment_items' => 'Mit High-Tech-Ausrüstung müssen genau '.RpgCharEditorEquipment::HIGH_TECH_ITEM_LIMIT.' High-Tech- oder Techno-Gegenstände gewählt werden.',
            ]);
        }

        $selectedIds = array_fill_keys(array_map(
            static fn (array $equipmentItem): string => (string) ($equipmentItem['id'] ?? ''),
            $equipmentItems,
        ), true);

        $this->validateActiveEquipmentId($activeArmorId, 'armor', 'active_armor_id', 'Rüstung', $selectedIds, $itemMap);
        $this->validateActiveEquipmentId($activeShieldId, 'shield', 'active_shield_id', 'Schild', $selectedIds, $itemMap);
    }

    private function validateLanguages(array $skills, array $advantages, array $languages, bool $creationV2): void
    {
        if (! $creationV2) {
            return;
        }

        foreach ($skills as $skill) {
            if (str_starts_with($this->canonicalSkillName((string) ($skill['name'] ?? '')), 'Sprachen:')) {
                throw ValidationException::withMessages([
                    'skills' => 'Sprachen wird als eine gemeinsame Fertigkeit geführt; konkrete Sprachen gehören in die separate Sprachenliste.',
                ]);
            }
        }

        $languageSkill = max($this->exactSkillValue($skills, 'Sprachen') ?? 0, 0);
        $languageCount = count($languages);

        if (in_array('Sprachbegabt', $advantages, true)) {
            if ($languageCount < $languageSkill || $languageCount > $languageSkill * 3) {
                throw ValidationException::withMessages([
                    'languages' => "Mit Sprachbegabt müssen bei Sprachen FW {$languageSkill} zwischen {$languageSkill} und ".($languageSkill * 3).' Sprachen oder Dialekte benannt werden.',
                ]);
            }

            return;
        }

        if ($languageCount !== $languageSkill) {
            throw ValidationException::withMessages([
                'languages' => "Ohne Sprachbegabt muss bei Sprachen FW {$languageSkill} genau dieselbe Zahl an Sprachen oder Dialekten benannt werden.",
            ]);
        }
    }

    private function validateActiveEquipmentId(string $id, string $expectedKind, string $field, string $label, array $selectedIds, array $itemMap): void
    {
        if ($id === '') {
            return;
        }

        $kind = $itemMap[$id]['combat']['kind'] ?? null;

        if (! isset($selectedIds[$id]) || $kind !== $expectedKind) {
            throw ValidationException::withMessages([
                $field => "Der aktive {$label} muss aus den gewählten Ausrüstungsgegenständen stammen.",
            ]);
        }
    }

    private function equipmentExportPayload(string $clothing, array $equipmentItems, string $notes, string $activeArmorId = '', string $activeShieldId = ''): array
    {
        $clothingMap = RpgCharEditorEquipment::clothingMap();
        $itemMap = RpgCharEditorEquipment::itemMap();
        $categories = RpgCharEditorEquipment::categories();
        $exportItems = [];

        foreach ($equipmentItems as $equipmentItem) {
            $id = (string) ($equipmentItem['id'] ?? '');

            if (! array_key_exists($id, $itemMap)) {
                continue;
            }

            $item = $itemMap[$id];
            $exportItems[] = [
                'id' => $id,
                'quantity' => (int) $equipmentItem['quantity'],
                'name' => $item['name'],
                'category' => $categories[$item['category']] ?? $item['category'],
                'summary' => $item['summary'] ?? '',
                'tw' => $item['tw'] ?? '',
                'bucks' => $item['bucks'] ?? '',
                'requires_high_tech_advantage' => RpgCharEditorEquipment::requiresHighTechAdvantage($item),
                'counts_toward_limit' => RpgCharEditorEquipment::countsTowardLimit($item),
            ];
        }

        return [
            'clothing' => $clothingMap[$clothing] ?? null,
            'items' => $exportItems,
            'ammunition' => $this->equipmentAmmunitionPayload($equipmentItems),
            'notes' => $notes,
            'active_armor_id' => $activeArmorId !== '' ? $activeArmorId : null,
            'active_shield_id' => $activeShieldId !== '' ? $activeShieldId : null,
        ];
    }

    private function equipmentAmmunitionPayload(array $equipmentItems): array
    {
        $itemMap = RpgCharEditorEquipment::itemMap();
        $payload = [];

        foreach ($equipmentItems as $equipmentItem) {
            $id = (string) ($equipmentItem['id'] ?? '');

            if (! array_key_exists($id, $itemMap)) {
                continue;
            }

            $item = $itemMap[$id];
            $ammunition = $item['ammunition'] ?? null;

            if (! is_array($ammunition)) {
                continue;
            }

            $quantity = (int) ($equipmentItem['quantity'] ?? 0);
            $amount = (int) ($ammunition['amount'] ?? 0);
            $unit = $this->stringPayload($ammunition['unit'] ?? '');

            if ($quantity < 1 || $amount < 1 || $unit === '') {
                continue;
            }

            $payload[] = [
                'source' => $item['name'],
                'quantity' => $quantity * $amount,
                'unit' => $unit,
            ];
        }

        return $payload;
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
