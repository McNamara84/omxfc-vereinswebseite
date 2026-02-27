<?php

namespace App\Http\Controllers;

use App\Models\Download;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
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

        $downloads = Download::active()
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->groupBy('category');

        // Ermittle alle Download-IDs, die der User über Reward-Käufe freigeschaltet hat
        $unlockedDownloadIds = RewardPurchase::where('user_id', $user->id)
            ->active()
            ->whereHas('reward', fn ($q) => $q->whereNotNull('download_id'))
            ->get()
            ->pluck('reward.download_id')
            ->unique()
            ->toArray();

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
        /** @var User $user */
        $user = Auth::user();

        // Prüfe ob eine Belohnung mit diesem Download verknüpft ist
        $reward = Reward::where('download_id', $download->id)->first();

        if ($reward) {
            $hasAccess = RewardPurchase::where('user_id', $user->id)
                ->where('reward_id', $reward->id)
                ->active()
                ->exists();

            if (! $hasAccess) {
                return back()->withErrors('Du musst diese Belohnung erst unter /belohnungen freischalten.');
            }
        }

        // Downloads ohne verknüpfte Belohnung sind frei verfügbar

        $path = $download->file_path;
        if (! Storage::disk('private')->exists($path)) {
            return back()->withErrors('Die Datei existiert nicht.');
        }

        return Storage::disk('private')->download($path, $download->original_filename);
    }
}
