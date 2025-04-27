<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

class DownloadsController extends Controller
{
    /**
     * Gesamte Download‑Konfiguration (Kategorie → Dateien).
     * Jede Datei enthält den Titel, den Dateinamen im privaten Storage
     * und die benötigte Mindestpunktzahl.
     */
    private array $downloads = [
        'Klemmbaustein-Anleitungen' => [
            [
                'titel' => 'Bauanleitung Euphoriewurm',
                'datei' => 'BauanleitungEuphoriewurmV2.pdf',
                'punkte' => 5,
            ],
            [
                'titel' => 'Bauanleitung Prototyp XP-1',
                'datei' => 'BauanleitungProtoV11.pdf',
                'punkte' => 10,
            ],
        ],
        'Fanstories' => [
            [
                'titel' => 'Das Flüstern der Vergangenheit von Max T. Hardwet',
                'datei' => 'DasFlüsternDerVergangenheit.pdf',
                'punkte' => 2,
            ],
        ],
    ];

    /**
     * Zeigt die Downloads‑Seite.
     */
    public function index()
    {
        /** @var User $user */
        $user = Auth::user();
        $currentTeam = $user->currentTeam;
        $userPoints = $currentTeam ? $user->totalPointsForTeam($currentTeam) : 0;

        return view('pages.downloads', [
            'downloads' => $this->downloads,
            'userPoints' => $userPoints,
        ]);
    }

    /**
     * Liefert eine Datei aus, wenn der Nutzer genug Punkte hat.
     *
     * @param string $datei  Dateiname (wie in $downloads angegeben)
     */
    public function download(string $datei)
    {
        // passende Metadaten heraussuchen
        $meta = collect($this->downloads)
            ->flatten(1)
            ->firstWhere('datei', $datei);

        if (!$meta) {
            return back()->withErrors('Die Datei wurde nicht gefunden.');
        }

        /** @var User $user */
        $user = Auth::user();
        $currentTeam = $user->currentTeam;
        $userPoints = $currentTeam ? $user->totalPointsForTeam($currentTeam) : 0;

        if ($userPoints < $meta['punkte']) {
            return back()->withErrors('Du hast nicht genügend Punkte für diesen Download.');
        }

        $path = 'downloads/' . $datei;
        if (!Storage::disk('private')->exists($path)) {
            return back()->withErrors('Die Datei existiert nicht.');
        }

        return Storage::disk('private')->download($path, $meta['titel'] . '.pdf');
    }
}
