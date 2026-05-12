<?php

namespace App\Http\Controllers;

use App\Enums\AuktionsStatus;
use App\Http\Requests\StoreAuktionGebotRequest;
use App\Models\Auktion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuktionController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Auktion::class);

        return view('auktionen.index', [
            'aktiveAuktionen' => Auktion::query()
                ->aktiv()
                ->with(['hoechstgebotRelation', 'verkauftesGebot'])
                ->withCount('gebote')
                ->orderByDesc('created_at')
                ->get(),
            'archivierteAuktionen' => Auktion::query()
                ->archiviert()
                ->with(['hoechstgebotRelation', 'verkauftesGebot'])
                ->withCount('gebote')
                ->orderByDesc('verkauft_at')
                ->orderByDesc('updated_at')
                ->get(),
        ]);
    }

    public function show(Auktion $auktion): View
    {
        $this->authorize('view', $auktion);

        $auktion->load(['gebote', 'verkauftesGebot']);

        return view('auktionen.show', [
            'auktion' => $auktion,
        ]);
    }

    public function storeGebot(StoreAuktionGebotRequest $request, Auktion $auktion): RedirectResponse
    {
        $this->authorize('bid', $auktion);

        DB::transaction(function () use ($request, $auktion): void {
            /** @var Auktion $lockedAuktion */
            $lockedAuktion = Auktion::query()
                ->with('gebote')
                ->lockForUpdate()
                ->findOrFail($auktion->id);

            if (! $request->user()->can('bid', $lockedAuktion)) {
                abort(403);
            }

            $betragInCent = $request->betragInCent();
            $naechstesMindestgebot = $lockedAuktion->naechstesMindestgebotCent();

            if ($betragInCent < $naechstesMindestgebot) {
                throw ValidationException::withMessages([
                    'betrag' => 'Das Gebot muss mindestens '.$lockedAuktion->naechstesMindestgebot().' betragen.',
                ]);
            }

            $lockedAuktion->gebote()->create([
                'user_id' => $request->user()->id,
                'bieter_name' => $request->user()->name,
                'betrag_cent' => $betragInCent,
            ]);

            if ($lockedAuktion->status !== AuktionsStatus::Laufend) {
                $lockedAuktion->update([
                    'status' => AuktionsStatus::Laufend,
                    'verkauft_an_user_id' => null,
                    'verkauft_gebot_id' => null,
                    'verkauft_at' => null,
                ]);
            }
        });

        return redirect()
            ->route('auktionen.show', $auktion)
            ->with('success', 'Dein Gebot wurde gespeichert.');
    }
}
