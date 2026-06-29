<?php

namespace App\Http\Controllers;

use App\Models\RpgCharacter;
use App\Models\User;
use App\Services\RpgCharacterSheetService;
use App\Services\RpgCharacterSlotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class RpgCharacterController extends Controller
{
    private const PORTRAIT_STORAGE_ERROR_MESSAGE = 'Das Portrait konnte nicht gespeichert werden.';

    public function __construct(
        private readonly RpgCharacterSlotService $slotService,
        private readonly RpgCharacterSheetService $characterSheetService,
    ) {}

    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('rpg.characters.index', [
            'characters' => RpgCharacter::query()
                ->where('user_id', $user->id)
                ->latest()
                ->get(),
            'slotSummary' => $this->slotService->summary($user),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $pdfPayload = $this->characterSheetService->validatedPdfPayload($request);
        $payload = $pdfPayload;
        unset($payload['portrait']);

        $storedPortrait = ['path' => null, 'mime' => null, 'original_name' => null];

        try {
            DB::transaction(function () use ($request, $user, $payload, $pdfPayload, &$storedPortrait): void {
                $this->slotService->ensureFreeSlotForStore(
                    $user,
                    $request->boolean('purchase_slot_if_needed'),
                );

                $storedPortrait = $this->storePortraitFromPayload($pdfPayload['portrait'] ?? null, $request, $user);

                RpgCharacter::query()->create([
                    'user_id' => $user->id,
                    'character_name' => $payload['character']['character_name'] ?: 'Charakter',
                    'payload' => $payload,
                    'portrait_path' => $storedPortrait['path'],
                    'portrait_mime' => $storedPortrait['mime'],
                    'portrait_original_name' => $storedPortrait['original_name'],
                ]);
            });
        } catch (Throwable $exception) {
            $this->deletePortraitIfPresent($storedPortrait['path']);

            throw $exception;
        }

        return redirect()
            ->route('rpg.characters.index')
            ->with('success', 'Charakter wurde gespeichert.');
    }

    public function pdf(RpgCharacter $rpgCharacter)
    {
        $this->authorize('view', $rpgCharacter);

        $payload = $rpgCharacter->payload;
        $payload['portrait'] = $this->portraitDataUrl($rpgCharacter);

        return $this->characterSheetService->characterSheetPdfResponse($payload);
    }

    public function destroy(RpgCharacter $rpgCharacter): RedirectResponse
    {
        $this->authorize('delete', $rpgCharacter);

        $portraitPath = $rpgCharacter->portrait_path;
        $rpgCharacter->delete();
        $this->deletePortraitIfPresent($portraitPath);

        return redirect()
            ->route('rpg.characters.index')
            ->with('success', 'Charakter wurde geloescht.');
    }

    public function purchaseSlot(Request $request): RedirectResponse
    {
        try {
            $purchase = $this->slotService->purchaseSlot($request->user());

            return redirect()
                ->route('rpg.characters.index')
                ->with('success', "Ein weiterer Speicher-Slot wurde fuer {$purchase->cost_baxx} Baxx freigeschaltet.");
        } catch (ValidationException $exception) {
            return redirect()
                ->route('rpg.characters.index')
                ->withErrors($exception->errors());
        }
    }

    /**
     * @return array{path: ?string, mime: ?string, original_name: ?string}
     */
    private function storePortraitFromPayload(?string $dataUrl, Request $request, User $user): array
    {
        if (! $dataUrl) {
            return ['path' => null, 'mime' => null, 'original_name' => null];
        }

        if (! preg_match('/^data:(image\/(?:png|jpeg|gif|webp|bmp));base64,([A-Za-z0-9+\/=]+)$/', $dataUrl, $matches)) {
            throw $this->portraitStorageValidationException($request);
        }

        $binary = base64_decode($matches[2], true);

        if ($binary === false) {
            throw $this->portraitStorageValidationException($request);
        }

        $mime = $matches[1];
        $path = 'rpg-characters/'.$user->id.'/'.Str::uuid().'.'.$this->extensionForMime($mime);

        try {
            $portraitStored = Storage::disk('private')->put($path, $binary);
        } catch (Throwable $exception) {
            report($exception);
            $portraitStored = false;
        }

        if (! $portraitStored) {
            throw $this->portraitStorageValidationException($request);
        }

        return [
            'path' => $path,
            'mime' => $mime,
            'original_name' => $this->portraitOriginalName($request),
        ];
    }

    private function portraitStorageValidationException(Request $request): ValidationException
    {
        $field = $request->hasFile('portrait') ? 'portrait' : 'portrait_data_url';

        return ValidationException::withMessages([
            $field => self::PORTRAIT_STORAGE_ERROR_MESSAGE,
        ]);
    }

    private function portraitDataUrl(RpgCharacter $rpgCharacter): ?string
    {
        if (! $rpgCharacter->portrait_path || ! Storage::disk('private')->exists($rpgCharacter->portrait_path)) {
            return null;
        }

        $binary = Storage::disk('private')->get($rpgCharacter->portrait_path);
        $mime = $rpgCharacter->portrait_mime ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }

    private function deletePortraitIfPresent(?string $path): void
    {
        if ($path && Storage::disk('private')->exists($path)) {
            Storage::disk('private')->delete($path);
        }
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/bmp' => 'bmp',
            default => 'png',
        };
    }

    private function portraitOriginalName(Request $request): ?string
    {
        if (! $request->hasFile('portrait')) {
            return null;
        }

        $filename = basename($request->file('portrait')->getClientOriginalName());
        $filename = preg_replace('/[\x00-\x1F\x7F]/', '', $filename) ?: null;

        return $filename ? Str::limit($filename, 255, '') : null;
    }
}
