<?php

namespace App\Http\Controllers;

use App\Enums\AuktionsStatus;
use App\Http\Requests\StoreAuktionRequest;
use App\Http\Requests\UpdateAuktionRequest;
use App\Models\Auktion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuktionVerwaltungController extends Controller
{
    public function index(): View
    {
        $this->authorize('manage', Auktion::class);

        return view('admin.auktionen.index', [
            'auktionen' => Auktion::query()
                ->with(['hoechstgebotRelation'])
                ->withCount('gebote')
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('manage', Auktion::class);

        return view('admin.auktionen.form', [
            'auktion' => new Auktion([
                'status' => AuktionsStatus::Laufend,
                'startbetrag_cent' => 0,
                'mindestschritt_cent' => 100,
            ]),
            'isCreate' => true,
        ]);
    }

    public function store(StoreAuktionRequest $request): RedirectResponse
    {
        $this->authorize('manage', Auktion::class);

        $auktion = Auktion::create($request->payload());

        return redirect()
            ->route('admin.auktionen.edit', $auktion)
            ->with('success', 'Auktion erfolgreich angelegt.');
    }

    public function edit(Auktion $auktion): View
    {
        $this->authorize('update', $auktion);

        $auktion->load(['gebote']);

        return view('admin.auktionen.form', [
            'auktion' => $auktion,
            'isCreate' => false,
        ]);
    }

    public function update(UpdateAuktionRequest $request, Auktion $auktion): RedirectResponse
    {
        $this->authorize('update', $auktion);

        $auktion->update($request->payload($auktion));

        return redirect()
            ->route('admin.auktionen.edit', $auktion)
            ->with('success', 'Auktion erfolgreich aktualisiert.');
    }

    public function destroy(Auktion $auktion): RedirectResponse
    {
        $this->authorize('delete', $auktion);

        $auktion->delete();

        return redirect()
            ->route('admin.auktionen.index')
            ->with('success', 'Auktion gelöscht.');
    }

    public function zumErsten(Auktion $auktion): RedirectResponse
    {
        $this->authorize('call', $auktion);

        DB::transaction(function () use ($auktion): void {
            $lockedAuktion = Auktion::query()->lockForUpdate()->findOrFail($auktion->id);
            $this->ensureTransition($lockedAuktion->kannZumErstenAufgerufenWerden(), 'Die Auktion kann nur aus dem Status "Laufend" auf "Zum ersten" gesetzt werden.');

            $lockedAuktion->update(['status' => AuktionsStatus::ZumErsten]);
        });

        return back()->with('success', 'Auktion steht jetzt auf "Zum ersten".');
    }

    public function zumZweiten(Auktion $auktion): RedirectResponse
    {
        $this->authorize('call', $auktion);

        DB::transaction(function () use ($auktion): void {
            $lockedAuktion = Auktion::query()->lockForUpdate()->findOrFail($auktion->id);
            $this->ensureTransition($lockedAuktion->kannZumZweitenAufgerufenWerden(), 'Die Auktion kann nur aus dem Status "Zum ersten" auf "Zum zweiten" gesetzt werden.');

            $lockedAuktion->update(['status' => AuktionsStatus::ZumZweiten]);
        });

        return back()->with('success', 'Auktion steht jetzt auf "Zum zweiten".');
    }

    public function verkaufen(Auktion $auktion): RedirectResponse
    {
        $this->authorize('call', $auktion);

        DB::transaction(function () use ($auktion): void {
            $lockedAuktion = Auktion::query()->with('hoechstgebotRelation')->lockForUpdate()->findOrFail($auktion->id);
            $this->ensureTransition($lockedAuktion->status === AuktionsStatus::ZumZweiten, 'Verkaufen ist erst nach "Zum zweiten" möglich.');

            $hoechstgebot = $lockedAuktion->hoechstgebot();

            if (! $hoechstgebot) {
                throw ValidationException::withMessages([
                    'status' => 'Ohne Gebot kann die Auktion nicht verkauft werden. Bitte als nicht verkauft beenden.',
                ]);
            }

            $lockedAuktion->update([
                'status' => AuktionsStatus::Verkauft,
                'verkauft_an_user_id' => $hoechstgebot->user_id,
                'verkauft_gebot_id' => $hoechstgebot->id,
                'verkauft_at' => now(),
            ]);
        });

        return back()->with('success', 'Auktion wurde an das aktuelle Höchstgebot verkauft.');
    }

    public function nichtVerkauft(Auktion $auktion): RedirectResponse
    {
        $this->authorize('call', $auktion);

        DB::transaction(function () use ($auktion): void {
            $lockedAuktion = Auktion::query()->lockForUpdate()->findOrFail($auktion->id);
            $this->ensureTransition($lockedAuktion->kannAlsNichtVerkauftBeendetWerden(), 'Die Auktion kann erst nach "Zum zweiten" als nicht verkauft beendet werden.');

            $lockedAuktion->update([
                'status' => AuktionsStatus::NichtVerkauft,
                'verkauft_an_user_id' => null,
                'verkauft_gebot_id' => null,
                'verkauft_at' => now(),
            ]);
        });

        return back()->with('success', 'Auktion wurde als nicht verkauft beendet.');
    }

    private function ensureTransition(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([
                'status' => $message,
            ]);
        }
    }
}
