<?php

namespace App\Http\Controllers;

use App\Models\Download;
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
        $unlockedDownloadIds = RewardPurchase::where('reward_purchases.user_id', $user->id)
            ->active()
            ->join('rewards', 'reward_purchases.reward_id', '=', 'rewards.id')
            ->whereNotNull('rewards.download_id')
            ->distinct()
            ->pluck('rewards.download_id')
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
        // Inaktive Downloads sind nicht abrufbar
        abort_if(! $download->is_active, 404);

        /** @var User $user */
        $user = Auth::user();

        // Prüfe ob eine Belohnung mit diesem Download verknüpft ist
        if ($download->reward()->exists()) {
            // Prüfe ob der User die verknüpfte Belohnung freigeschaltet hat
            $hasAccess = RewardPurchase::where('user_id', $user->id)
                ->active()
                ->whereHas('reward', fn ($q) => $q->where('download_id', $download->id))
                ->exists();

            if (! $hasAccess) {
                $download->load('reward');

                if (! $download->reward->is_active) {
                    return back()->withErrors('Dieser Download ist derzeit nicht verfügbar.');
                }

                return back()->withErrors('Du musst diese Belohnung erst unter Belohnungen freischalten.');
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
