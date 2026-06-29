<?php

namespace App\Http\Controllers;

use App\Services\RpgCharacterSheetService;
use App\Services\RpgCharacterSlotService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class RpgCharEditorController extends Controller
{
    private const PDF_EXPORT_SESSION_MINUTES = 10;

    private const PDF_EXPORT_CACHE_STORE = 'rpg_pdf_exports';

    private const PDF_EXPORT_CACHE_KEY_PREFIX = 'rpg-char-editor-pdf:';

    private const PDF_EXPORT_SESSION_ACTIVE_TOKEN_KEY = 'rpg-char-editor-pdf.active-token';

    public function __construct(
        private readonly RpgCharacterSheetService $characterSheetService,
    ) {}

    public static function attributeRuleConfig(): array
    {
        return RpgCharacterSheetService::attributeRuleConfig();
    }

    public static function skillRuleConfig(): array
    {
        return RpgCharacterSheetService::skillRuleConfig();
    }

    public static function specialRuleConfig(): array
    {
        return RpgCharacterSheetService::specialRuleConfig();
    }

    /**
     * Show the character editor form.
     */
    public function index(Request $request, RpgCharacterSlotService $slotService)
    {
        $specialRules = RpgCharacterSheetService::specialRuleConfig();

        return view('rpg.char-editor', [
            'specialRules' => $specialRules,
            'advantages' => $specialRules['advantages'],
            'disadvantages' => $specialRules['disadvantages'],
            'slotSummary' => $slotService->summary($request->user()),
        ]);
    }

    /**
     * Prepare a character sheet PDF and redirect to a GET viewer URL.
     */
    public function storePdfExport(Request $request)
    {
        $data = $this->characterSheetService->validatedPdfPayload($request);
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

        return $this->characterSheetService->characterSheetPdfResponse($export['data']);
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
}
