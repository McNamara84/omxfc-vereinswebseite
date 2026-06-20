<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelPdf\Facades\Pdf;

class RpgCharEditorController extends Controller
{
    private const ATTRIBUTE_KEYS = ['st', 'ge', 'ro', 'wi', 'wa', 'in', 'au'];

    private const CHARACTER_KEYS = [
        'player_name',
        'character_name',
        'race',
        'culture',
        'description',
        'equipment',
    ];

    private const PORTRAIT_MAX_BYTES = 2_097_152;

    private const PORTRAIT_MAX_BASE64_CHARS = 2_796_204;

    private const PORTRAIT_DATA_URL_PREFIX_MAX_CHARS = 23;

    private const PORTRAIT_DATA_URL_MAX_CHARS = self::PORTRAIT_DATA_URL_PREFIX_MAX_CHARS + self::PORTRAIT_MAX_BASE64_CHARS;

    /**
     * Show the character editor form.
     */
    public function index()
    {
        return view('rpg.char-editor');
    }

    /**
     * Generate a character sheet PDF.
     */
    public function pdf(Request $request)
    {
        $request->validate([
            'portrait' => 'nullable|image|max:2048',
            'portrait_data_url' => 'nullable|string|max:'.self::PORTRAIT_DATA_URL_MAX_CHARS,
        ]);

        $data = [
            'character' => $this->characterPayload($request),
            'attributes' => $this->attributesPayload($request->input('attributes', [])),
            'skills' => $this->skillsPayload($request->input('skills', [])),
            'advantages' => $this->listPayload($request->input('advantages', [])),
            'disadvantages' => $this->listPayload($request->input('disadvantages', [])),
            'portrait' => $this->portraitPayload($request),
        ];

        $name = Str::slug($data['character']['character_name'] ?: 'charakter') ?: 'charakter';

        return Pdf::view('rpg.char-sheet', $data)
            ->driver('dompdf')
            ->format('a4')
            ->margins(10, 10, 10, 10)
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
