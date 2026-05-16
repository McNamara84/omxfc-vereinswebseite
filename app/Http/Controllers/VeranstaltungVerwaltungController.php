<?php

namespace App\Http\Controllers;

use App\Models\Veranstaltung;
use App\Models\VeranstaltungsAbschnitt;
use App\Models\VeranstaltungsMerchartikel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

#[\Illuminate\Routing\Attributes\Controllers\Middleware('can:manage,App\Models\Veranstaltung')]
#[Authorize('manage', Veranstaltung::class)]
class VeranstaltungVerwaltungController extends Controller
{
    public function index(): View
    {
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
            'merchartikel' => collect(),
            'isCreate' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $veranstaltung = Veranstaltung::create($this->validatedData($request));
        $this->syncHighlight($veranstaltung);

        return redirect()->route('admin.veranstaltungen.edit', $veranstaltung)
            ->with('success', 'Veranstaltung erfolgreich angelegt.');
    }

    public function edit(Veranstaltung $veranstaltung): View
    {
        return view('admin.veranstaltungen.form', [
            'veranstaltung' => $veranstaltung,
            'abschnitte' => $veranstaltung->abschnitte()->orderBy('sort_order')->get(),
            'merchartikel' => $veranstaltung->merchartikel()->with('varianten')->get(),
            'isCreate' => false,
        ]);
    }

    public function update(Request $request, Veranstaltung $veranstaltung): RedirectResponse
    {
        $veranstaltung->update($this->validatedData($request, $veranstaltung));
        $this->syncHighlight($veranstaltung);

        return redirect()->route('admin.veranstaltungen.edit', $veranstaltung)
            ->with('success', 'Veranstaltung erfolgreich aktualisiert.');
    }

    public function storeAbschnitt(Request $request, Veranstaltung $veranstaltung): RedirectResponse
    {
        $veranstaltung->abschnitte()->create($this->validatedAbschnitt($request));

        return redirect()->route('admin.veranstaltungen.edit', $veranstaltung)
            ->with('success', 'Abschnitt hinzugefügt.');
    }

    public function updateAbschnitt(Request $request, Veranstaltung $veranstaltung, VeranstaltungsAbschnitt $abschnitt): RedirectResponse
    {
        abort_unless($abschnitt->veranstaltung_id === $veranstaltung->id, 404);

        $abschnitt->update($this->validatedAbschnitt($request));

        return redirect()->route('admin.veranstaltungen.edit', $veranstaltung)
            ->with('success', 'Abschnitt aktualisiert.');
    }

    public function destroyAbschnitt(Veranstaltung $veranstaltung, VeranstaltungsAbschnitt $abschnitt): RedirectResponse
    {
        abort_unless($abschnitt->veranstaltung_id === $veranstaltung->id, 404);

        $abschnitt->delete();

        return redirect()->route('admin.veranstaltungen.edit', $veranstaltung)
            ->with('success', 'Abschnitt gelöscht.');
    }

    public function storeMerchartikel(Request $request, Veranstaltung $veranstaltung): RedirectResponse
    {
        if ($veranstaltung->merchartikel()->count() >= 10) {
            return redirect()->route('admin.veranstaltungen.edit', $veranstaltung)
                ->withErrors(['merch' => 'Maximal 10 Merchandise-Artikel pro Veranstaltung sind möglich.'])
                ->withInput();
        }

        $validated = $this->validatedMerchartikel($request);

        $merchartikel = $veranstaltung->merchartikel()->create([
            'bezeichnung' => $validated['bezeichnung'],
            'beschreibung' => $validated['beschreibung'],
            'preis' => $validated['preis'],
            'sort_order' => $validated['sort_order'],
            'is_active' => $validated['is_active'],
        ]);

        $this->syncVarianten($merchartikel, $validated['varianten']);

        return redirect()->route('admin.veranstaltungen.edit', $veranstaltung)
            ->with('success', 'Merchandise-Artikel hinzugefügt.');
    }

    public function updateMerchartikel(Request $request, Veranstaltung $veranstaltung, VeranstaltungsMerchartikel $merchartikel): RedirectResponse
    {
        abort_unless($merchartikel->veranstaltung_id === $veranstaltung->id, 404);

        $validated = $this->validatedMerchartikel($request);

        $merchartikel->update([
            'bezeichnung' => $validated['bezeichnung'],
            'beschreibung' => $validated['beschreibung'],
            'preis' => $validated['preis'],
            'sort_order' => $validated['sort_order'],
            'is_active' => $validated['is_active'],
        ]);

        $this->syncVarianten($merchartikel, $validated['varianten']);

        return redirect()->route('admin.veranstaltungen.edit', $veranstaltung)
            ->with('success', 'Merchandise-Artikel aktualisiert.');
    }

    public function destroyMerchartikel(Veranstaltung $veranstaltung, VeranstaltungsMerchartikel $merchartikel): RedirectResponse
    {
        abort_unless($merchartikel->veranstaltung_id === $veranstaltung->id, 404);

        if ($merchartikel->bestellungen()->exists()) {
            $merchartikel->update(['is_active' => false]);

            return redirect()->route('admin.veranstaltungen.edit', $veranstaltung)
                ->with('success', 'Merchandise-Artikel wurde deaktiviert, da bereits Bestellungen vorliegen.');
        }

        $merchartikel->delete();

        return redirect()->route('admin.veranstaltungen.edit', $veranstaltung)
            ->with('success', 'Merchandise-Artikel gelöscht.');
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
            'merch_deadline' => ['nullable', 'date'],
            'gastgebuehr' => ['nullable', 'numeric', 'min:0'],
            'benachrichtigungs_email' => ['nullable', 'email', 'max:255'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['anmeldung_aktiv'] = $request->boolean('anmeldung_aktiv');
        $validated['zahlung_aktiv'] = $request->boolean('zahlung_aktiv');
        $validated['tshirt_aktiv'] = $veranstaltung?->tshirt_aktiv ?? false;
        $validated['tshirt_deadline'] = $veranstaltung?->tshirt_deadline;
        $validated['vip_autoren_aktiv'] = $request->boolean('vip_autoren_aktiv');
        $validated['ist_highlight'] = $request->boolean('ist_highlight');
        $validated['gastgebuehr'] = $validated['gastgebuehr'] ?? 0;
        $validated['tshirt_preis'] = $veranstaltung?->tshirt_preis ?? 0;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;

        return $validated;
    }

    private function validatedMerchartikel(Request $request): array
    {
        $validated = $request->validate([
            'bezeichnung' => ['required', 'string', 'max:255'],
            'beschreibung' => ['nullable', 'string'],
            'preis' => ['required', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'varianten' => ['nullable', 'string'],
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['varianten'] = $this->parseVarianten($validated['varianten'] ?? null);

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

    private function parseVarianten(?string $varianten): array
    {
        $parts = preg_split('/[\r\n,]+/', (string) $varianten) ?: [];
        $normalized = [];

        foreach ($parts as $part) {
            $value = trim($part);

            if ($value === '') {
                continue;
            }

            if (in_array(Str::lower($value), array_map(Str::lower(...), $normalized), true)) {
                continue;
            }

            $normalized[] = $value;
        }

        return array_slice($normalized, 0, 25);
    }

    private function syncVarianten(VeranstaltungsMerchartikel $merchartikel, array $varianten): void
    {
        $existing = $merchartikel->varianten()->get()->keyBy(
            fn ($variante) => Str::lower($variante->bezeichnung)
        );
        $keptIds = [];

        foreach ($varianten as $index => $bezeichnung) {
            $key = Str::lower($bezeichnung);
            $variante = $existing->get($key);

            if ($variante) {
                $variante->update([
                    'bezeichnung' => $bezeichnung,
                    'sort_order' => $index,
                    'is_active' => true,
                ]);

                $keptIds[] = $variante->id;

                continue;
            }

            $keptIds[] = $merchartikel->varianten()->create([
                'bezeichnung' => $bezeichnung,
                'sort_order' => $index,
                'is_active' => true,
            ])->id;
        }

        $merchartikel->varianten()
            ->whereNotIn('id', $keptIds)
            ->get()
            ->each(function ($variante) {
                if ($variante->bestellungen()->exists()) {
                    $variante->update(['is_active' => false]);

                    return;
                }

                $variante->delete();
            });
    }
}