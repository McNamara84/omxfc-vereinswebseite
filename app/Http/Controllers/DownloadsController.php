<?php

namespace App\Http\Controllers;

use App\Models\Download;
use App\Models\RewardPurchase;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DownloadsController extends Controller
{
    /**
     * Zeigt die Downloads-Seite.
     * Downloads sind über die DB verwaltet und über Belohnungen freigeschaltet.
     */
    public function index()
    {
        /** @var User $user */
        $user = Auth::user();

        // Eager-Load die Reward-Relation, um N+1 in der View zu vermeiden
        $downloads = Download::active()
            ->with(['reward:id,title,download_id,is_active'])
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->groupBy('category');

        // Ermittle alle Download-IDs, die der User über Reward-Käufe freigeschaltet hat.
        // Per Join statt lazy Loading, um N+1-Queries zu vermeiden.
        $unlockedDownloadIds = DB::table('reward_purchases')
            ->where('reward_purchases.user_id', $user->id)
            ->whereNull('reward_purchases.refunded_at')
            ->join('rewards', 'reward_purchases.reward_id', '=', 'rewards.id')
            ->whereNotNull('rewards.download_id')
            ->where('rewards.is_active', true)
            ->distinct()
            ->pluck('rewards.download_id')
            ->flip();

        return view('pages.downloads', [
            'downloads' => $downloads,
            'unlockedDownloadIds' => $unlockedDownloadIds,
        ]);
    }

    /**
     * Liefert eine Datei aus, wenn der Nutzer die entsprechende Belohnung freigeschaltet hat.
     */
    public function download(Download $download)
    {
        // Inaktive Downloads sind nicht abrufbar
        abort_if(! $download->is_active, 404);

        /** @var User $user */
        $user = Auth::user();

        // Relation einmalig laden, um doppelte Queries zu vermeiden
        $download->loadMissing('reward');

        // Prüfe ob eine Belohnung mit diesem Download verknüpft ist
        if ($download->reward) {
            if (! $download->reward->is_active) {
                return back()->withErrors('Dieser Download ist derzeit nicht verfügbar.');
            }

            // Prüfe ob der User die verknüpfte Belohnung freigeschaltet hat
            $hasAccess = RewardPurchase::query()
                ->where('user_id', $user->id)
                ->active()
                ->whereHas('reward', fn ($q) => $q->where('download_id', $download->id))
                ->exists();

            if (! $hasAccess) {
                return back()->withErrors('Diesen Download kannst du erst öffnen, nachdem du ihn im Bereich Belohnungen einlösen freigeschaltet hast.');
            }
        }

        // Downloads ohne verknüpfte Belohnung sind frei verfügbar

        $path = $this->validatedDownloadPath($download);
        if ($path === null) {
            return back()->withErrors('Die Datei existiert nicht.');
        }

        if (! Storage::disk('private')->exists($path)) {
            $this->restoreBundledDownloadIfMissing($path);
        }

        if (! Storage::disk('private')->exists($path)) {
            return back()->withErrors('Die Datei existiert nicht.');
        }

        return response()->download(
            Storage::disk('private')->path($path),
            $download->original_filename
        );
    }

    private function validatedDownloadPath(Download $download): ?string
    {
        $path = str_replace('\\', '/', $download->file_path);

        if ($path === '' || str_starts_with($path, '/')) {
            return null;
        }

        if (preg_match('/^[A-Za-z]:\//', $path) === 1) {
            return null;
        }

        if (! str_starts_with($path, 'downloads/')) {
            return null;
        }

        $segments = explode('/', $path);
        if (in_array('.', $segments, true) || in_array('..', $segments, true) || in_array('', $segments, true)) {
            return null;
        }

        return $path;
    }

    private function restoreBundledDownloadIfMissing(string $path): void
    {
        $sourcePath = $this->bundledSourcePath($path);

        if ($sourcePath === null) {
            return;
        }

        $stream = fopen($sourcePath, 'rb');

        if ($stream === false) {
            return;
        }

        try {
            Storage::disk('private')->put($path, $stream);
        } finally {
            fclose($stream);
        }
    }

    private function bundledSourcePath(string $path): ?string
    {
        $downloadsRoot = realpath(resource_path('downloads'));
        $sourcePath = realpath(resource_path($path));

        if ($downloadsRoot === false || $sourcePath === false) {
            return null;
        }

        $downloadsRoot = str_replace('\\', '/', $downloadsRoot);
        $sourcePath = str_replace('\\', '/', $sourcePath);

        if ($sourcePath !== $downloadsRoot && ! str_starts_with($sourcePath, $downloadsRoot.'/')) {
            return null;
        }

        return $sourcePath;
    }
}
