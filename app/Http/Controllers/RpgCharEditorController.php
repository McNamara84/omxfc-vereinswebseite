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

    private const PORTRAIT_MAX_BYTES = 2_097_152;

    private const PORTRAIT_MAX_BASE64_CHARS = 2_796_204;

    private const PORTRAIT_DATA_URL_PREFIX_MAX_CHARS = 23;

    private const PORTRAIT_DATA_URL_MAX_CHARS = self::PORTRAIT_DATA_URL_PREFIX_MAX_CHARS + self::PORTRAIT_MAX_BASE64_CHARS;

    private const PDF_EXPORT_SESSION_MINUTES = 10;

    private const PDF_EXPORT_CACHE_STORE = 'rpg_pdf_exports';

    private const PDF_EXPORT_CACHE_KEY_PREFIX = 'rpg-char-editor-pdf:';

    private const PDF_EXPORT_SESSION_ACTIVE_TOKEN_KEY = 'rpg-char-editor-pdf.active-token';

    /**
     * Show the character editor form.
     */
    public function index()
    {
        return view('rpg.char-editor');
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
        ]);

        $character = $this->characterPayload($request);
        $attributes = $this->attributesPayload($request->input('attributes', []));
        $skills = $this->skillsPayload($request->input('skills', []));
        $advantages = $this->listPayload($request->input('advantages', []));
        $disadvantages = $this->listPayload($request->input('disadvantages', []));

        $this->validateCharacterRules($character, $attributes, $skills, $advantages, $disadvantages);

        return [
            'character' => $character,
            'attributes' => $attributes,
            'skills' => $skills,
            'advantages' => $advantages,
            'disadvantages' => $disadvantages,
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

    private function validateCharacterRules(array $character, array $attributes, array $skills, array $advantages, array $disadvantages): void
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

        if ($culture === 'Volk der 13 Inseln'
            && $gender === 'weiblich'
            && ! in_array('Psychische Kraft', $advantages, true)) {
            throw ValidationException::withMessages([
                'advantages' => 'Weibliche Charaktere aus dem Volk der 13 Inseln müssen Psychische Kraft wählen.',
            ]);
        }

        $this->validateRaceRequirements($race, $attributes, $skills, $advantages, $disadvantages);
        $this->validateCultureRequirements($culture, $skills);
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

        foreach ($requirements['attributeRanges'] ?? [] as $attributeName => [$minimumValue, $maximumValue]) {
            $attributeValue = $this->attributeValue($attributes, $attributeName);
            $attributeLabel = $this->attributeLabel($attributeName);

            if ($attributeValue === null) {
                throw ValidationException::withMessages([
                    'attributes' => "Das Attribut {$attributeLabel} muss für die Rasse {$race} übermittelt werden.",
                ]);
            }

            if ($attributeValue < $minimumValue || $attributeValue > $maximumValue) {
                throw ValidationException::withMessages([
                    'attributes' => "Das Attribut {$attributeLabel} passt nicht zu den Rassenmodifikatoren von {$race}.",
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
                'attributeRanges' => ['au' => [-1, 0]],
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
                'attributeRanges' => ['ge' => [0, 2], 'au' => [-1, 0]],
            ],
            'Taratze' => [
                'skills' => ['Intuition' => 2, 'Heimlichkeit' => 1, 'Überleben' => 1],
                'disadvantages' => ['Auffällig', 'Primitiv', 'Gejagt'],
                'attributeRanges' => ['st' => [0, 2], 'wa' => [0, 2], 'in' => [-1, 0], 'au' => [-1, 0]],
            ],
            'Wulfane' => [
                'skills' => ['Intuition' => 1, 'Nahkampf' => 1],
                'disadvantages' => ['Ehrenkodex'],
                'attributeRanges' => ['ro' => [0, 2], 'au' => [-1, 0]],
            ],
            'Techno' => [
                'skills' => ['Bildung' => 3],
                'advantages' => ['High-Tech-Ausrüstung'],
                'disadvantages' => ['Tödliche Immunschwäche'],
                'attributeRanges' => ['st' => [-1, 0], 'ro' => [-1, 0], 'in' => [0, 2]],
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

        foreach ($skills as $skill) {
            if (($skill['name'] ?? null) !== $skillName || ! is_numeric($skill['value'] ?? null)) {
                continue;
            }

            $value = max($value ?? PHP_INT_MIN, (int) $skill['value']);
        }

        return $value ?? PHP_INT_MIN;
    }

    private function attributeValue(array $attributes, string $attributeName): ?int
    {
        if (! array_key_exists($attributeName, $attributes) || ! is_numeric($attributes[$attributeName])) {
            return null;
        }

        return (int) $attributes[$attributeName];
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

            $name = $this->stringPayload($skill['name'] ?? '');

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
