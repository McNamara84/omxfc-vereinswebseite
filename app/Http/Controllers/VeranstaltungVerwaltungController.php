<?php

namespace App\Http\Controllers;

use App\Models\Veranstaltung;
use App\Models\VeranstaltungsAbschnitt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class VeranstaltungVerwaltungController extends Controller
{
    public function index(): View
    {
        $this->authorize('manage', Veranstaltung::class);

        return view('admin.veranstaltungen.index', [
            'veranstaltungen' => Veranstaltung::query()
                ->withCount(['anmeldungen', 'abschnitte', 'vipAutoren'])
                ->orderByDesc('ist_highlight')
                ->orderBy('datum_von')
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('manage', Veranstaltung::class);

        return view('admin.veranstaltungen.form', [
            'veranstaltung' => new Veranstaltung([
                'status' => 'entwurf',
                'anmeldung_aktiv' => false,
                'zahlung_aktiv' => false,
                'tshirt_aktiv' => false,
                'vip_autoren_aktiv' => false,
                'gastgebuehr' => 0,
                'tshirt_preis' => 25,
            ]),
            'abschnitte' => collect(),
            'isCreate' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage', Veranstaltung::class);

        $veranstaltung = Veranstaltung::create($this->validatedData($request));
        $this->syncHighlight($veranstaltung);

        return redirect()->route('admin.veranstaltungen.edit', $veranstaltung)
            ->with('success', 'Veranstaltung erfolgreich angelegt.');
    }

    public function edit(Veranstaltung $veranstaltung): View
    {
        $this->authorize('manage', Veranstaltung::class);

        return view('admin.veranstaltungen.form', [
            'veranstaltung' => $veranstaltung,
            'abschnitte' => $veranstaltung->abschnitte()->orderBy('sort_order')->get(),
            'isCreate' => false,
        ]);
    }

    public function update(Request $request, Veranstaltung $veranstaltung): RedirectResponse
    {
        $this->authorize('manage', Veranstaltung::class);

        $veranstaltung->update($this->validatedData($request, $veranstaltung));
        $this->syncHighlight($veranstaltung);

        return redirect()->route('admin.veranstaltungen.edit', $veranstaltung)
            ->with('success', 'Veranstaltung erfolgreich aktualisiert.');
    }

    public function storeAbschnitt(Request $request, Veranstaltung $veranstaltung): RedirectResponse
    {
        $this->authorize('manage', Veranstaltung::class);

        $veranstaltung->abschnitte()->create($this->validatedAbschnitt($request));

        return redirect()->route('admin.veranstaltungen.edit', $veranstaltung)
            ->with('success', 'Abschnitt hinzugefügt.');
    }

    public function updateAbschnitt(Request $request, Veranstaltung $veranstaltung, VeranstaltungsAbschnitt $abschnitt): RedirectResponse
    {
        $this->authorize('manage', Veranstaltung::class);
        abort_unless($abschnitt->veranstaltung_id === $veranstaltung->id, 404);

        $abschnitt->update($this->validatedAbschnitt($request));

        return redirect()->route('admin.veranstaltungen.edit', $veranstaltung)
            ->with('success', 'Abschnitt aktualisiert.');
    }

    public function destroyAbschnitt(Veranstaltung $veranstaltung, VeranstaltungsAbschnitt $abschnitt): RedirectResponse
    {
        $this->authorize('manage', Veranstaltung::class);
        abort_unless($abschnitt->veranstaltung_id === $veranstaltung->id, 404);

        $abschnitt->delete();

        return redirect()->route('admin.veranstaltungen.edit', $veranstaltung)
            ->with('success', 'Abschnitt gelöscht.');
    }

    private function validatedData(Request $request, ?Veranstaltung $veranstaltung = null): array
    {
        $validated = $request->validate([
            'titel' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('veranstaltungen', 'slug')->ignore($veranstaltung?->id)],
            'status' => ['required', Rule::in(['entwurf', 'veroeffentlicht', 'archiviert'])],
            'veranstaltungsart' => ['nullable', 'string', 'max:255'],
            'untertitel' => ['nullable', 'string', 'max:255'],
            'teaser' => ['nullable', 'string'],
            'beschreibung' => ['nullable', 'string'],
            'datum_von' => ['nullable', 'date'],
            'datum_bis' => ['nullable', 'date', 'after_or_equal:datum_von'],
            'ort_name' => ['nullable', 'string', 'max:255'],
            'ort_adresse' => ['nullable', 'string'],
            'maps_url' => ['nullable', 'url', 'max:255'],
            'anmeldung_start' => ['nullable', 'date'],
            'anmeldung_ende' => ['nullable', 'date', 'after_or_equal:anmeldung_start'],
            'tshirt_deadline' => ['nullable', 'date'],
            'gastgebuehr' => ['nullable', 'numeric', 'min:0'],
            'tshirt_preis' => ['nullable', 'numeric', 'min:0'],
            'benachrichtigungs_email' => ['nullable', 'email', 'max:255'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['anmeldung_aktiv'] = $request->boolean('anmeldung_aktiv');
        $validated['zahlung_aktiv'] = $request->boolean('zahlung_aktiv');
        $validated['tshirt_aktiv'] = $request->boolean('tshirt_aktiv');
        $validated['vip_autoren_aktiv'] = $request->boolean('vip_autoren_aktiv');
        $validated['ist_highlight'] = $request->boolean('ist_highlight');
        $validated['gastgebuehr'] = $validated['gastgebuehr'] ?? 0;
        $validated['tshirt_preis'] = $validated['tshirt_preis'] ?? 0;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        return $validated;
    }

    private function validatedAbschnitt(Request $request): array
    {
        $validated = $request->validate([
            'schluessel' => ['nullable', 'string', 'max:255'],
            'titel' => ['required', 'string', 'max:255'],
            'markdown_inhalt' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['is_visible'] = $request->boolean('is_visible', true);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        return $validated;
    }

    private function syncHighlight(Veranstaltung $veranstaltung): void
    {
        if (! $veranstaltung->ist_highlight) {
            return;
        }

        Veranstaltung::query()
            ->whereKeyNot($veranstaltung->id)
            ->update(['ist_highlight' => false]);
    }
}