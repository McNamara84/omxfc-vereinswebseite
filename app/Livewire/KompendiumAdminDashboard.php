<?php

namespace App\Livewire;

use App\Jobs\DeIndexiereRomanJob;
use App\Jobs\IndexiereRomanJob;
use App\Models\KompendiumRoman;
use App\Services\KompendiumSearchService;
use App\Services\KompendiumService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Livewire-Komponente für die Admin-Verwaltung des Kompendiums.
 *
 * Ermöglicht das Hochladen, Indexieren, De-Indexieren, Bearbeiten und Löschen von Romantexten.
 */
#[Layout('layouts.app')]
#[Title('Kompendium-Administration')]
class KompendiumAdminDashboard extends Component
{
    use WithFileUploads;
    use WithPagination;

    /** @var array<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $uploads = [];

    public string $ausgewaehlteSerie = 'maddrax';

    /** Tab-Navigation: Aktive Serie (Default: Maddrax) */
    public string $filterSerie = 'maddrax';

    public string $filterStatus = '';

    public string $suchbegriff = '';

    /** Bearbeitungs-Modal State */
    public bool $showEditModal = false;

    public ?int $editId = null;

    public string $editSerie = '';

    public string $editZyklus = '';

    public int $editNummer = 0;

    public string $editTitel = '';

    /** Timestamp für Optimistic Locking beim Bearbeiten */
    public ?string $editUpdatedAt = null;

    protected function rules(): array
    {
        return [
            'uploads' => 'required|array|min:1',
            'uploads.*' => 'required|file|mimes:txt|max:10240', // 10 MB
            'ausgewaehlteSerie' => ['required', Rule::in(array_keys(KompendiumService::SERIEN))],
        ];
    }

    protected function messages(): array
    {
        return [
            'uploads.*.mimes' => 'Nur TXT-Dateien sind erlaubt.',
            'uploads.*.max' => 'Die Datei darf maximal 10 MB groß sein.',
        ];
    }

    #[Computed]
    public function romane()
    {
        return KompendiumRoman::query()
            ->where('serie', $this->filterSerie)
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->suchbegriff, fn ($q) => $q->where(function ($query) {
                $query->where('titel', 'like', "%{$this->suchbegriff}%")
                    ->orWhere('roman_nr', 'like', "%{$this->suchbegriff}%");
            }))
            ->orderBy('roman_nr')
            ->paginate(50);
    }

    #[Computed]
    public function statistiken(): array
    {
        return app(KompendiumService::class)->getStatistiken();
    }

    #[Computed]
    public function serien(): array
    {
        return app(KompendiumService::class)->getSerienListe();
    }

    /** Anzahl Romane pro Serie für Tab-Badges. */
    #[Computed]
    public function romanZahlenProSerie(): array
    {
        return KompendiumRoman::query()
            ->selectRaw('serie, COUNT(*) as anzahl')
            ->groupBy('serie')
            ->pluck('anzahl', 'serie')
            ->toArray();
    }

    /** Zyklen-Fortschritt für das Statistik-Dashboard. */
    #[Computed]
    public function zyklenFortschritt()
    {
        return app(KompendiumService::class)->getZyklenFortschritt();
    }

    public function updatedFilterSerie(): void
    {
        $this->resetPage();
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedSuchbegriff(): void
    {
        $this->resetPage();
    }

    public function hochladen(): void
    {
        $this->validate();

        $service = app(KompendiumService::class);
        $erfolge = 0;
        $fehler = [];

        foreach ($this->uploads as $upload) {
            $dateiname = $upload->getClientOriginalName();
            $parsed = $service->parseDateiname($dateiname);

            if (! $parsed) {
                $fehler[] = "{$dateiname}: Ungültiges Format (erwartet: '001 - Titel.txt')";

                continue;
            }

            // Metadaten aus JSON laden (mit Fuzzy-Match)
            $meta = $service->findeMetadatenMitFuzzy($parsed['nummer'], $parsed['titel']);

            // Serie bestimmen: Falls Metadaten gefunden, diese nutzen, sonst ausgewählte Serie
            $serie = $meta['serie'] ?? $this->ausgewaehlteSerie;

            // Zielpfad erstellen
            $pfad = "romane/{$serie}/{$dateiname}";

            // Prüfen ob bereits vorhanden
            if (KompendiumRoman::where('dateipfad', $pfad)->exists()) {
                $fehler[] = "{$dateiname}: Bereits hochgeladen";

                continue;
            }

            // Datei speichern
            $upload->storeAs("romane/{$serie}", $dateiname, 'private');

            // DB-Eintrag erstellen
            KompendiumRoman::create([
                'dateiname' => $dateiname,
                'dateipfad' => $pfad,
                'serie' => $serie,
                'roman_nr' => $parsed['nummer'],
                'titel' => $parsed['titel'],
                'zyklus' => $meta['zyklus'] ?? null,
                'hochgeladen_am' => now(),
                'hochgeladen_von' => auth()->id(),
            ]);

            $erfolge++;
        }

        $this->uploads = [];

        if ($erfolge > 0) {
            session()->flash('success', "{$erfolge} Roman(e) erfolgreich hochgeladen.");
        }
        if (! empty($fehler)) {
            session()->flash('error', implode("\n", $fehler));
        }

        unset($this->romane, $this->statistiken, $this->romanZahlenProSerie, $this->zyklenFortschritt);
    }

    /* --------------------------------------------------------------------- */
    /*  Bearbeitung (Edit-Modal) */
    /* --------------------------------------------------------------------- */

    /**
     * Öffnet das Edit-Modal mit den Daten des Romans.
     */
    public function bearbeiten(int $id): void
    {
        $roman = KompendiumRoman::findOrFail($id);

        if ($roman->status === 'indexierung_laeuft') {
            session()->flash('warning', 'Roman kann während der Indexierung nicht bearbeitet werden.');

            return;
        }

        $this->editId = $roman->id;
        $this->editSerie = $roman->serie;
        $this->editZyklus = $roman->zyklus ?? '';
        $this->editNummer = $roman->roman_nr;
        $this->editTitel = $roman->titel;
        $this->editUpdatedAt = $roman->updated_at?->toISOString();
        $this->showEditModal = true;
    }

    /**
     * Speichert die Änderungen am Roman und verschiebt ggf. die Datei.
     */
    public function speichern(): void
    {
        $this->validate([
            'editId' => 'required|integer|exists:kompendium_romane,id',
            'editSerie' => ['required', Rule::in(array_keys(KompendiumService::SERIEN))],
            'editZyklus' => 'nullable|string|max:100',
            'editNummer' => 'required|integer|min:1',
            'editTitel' => 'required|string|max:255',
        ]);

        $roman = KompendiumRoman::findOrFail($this->editId);

        // Optimistic Locking: Prüfen ob der Roman zwischenzeitlich geändert wurde
        if ($this->editUpdatedAt && $roman->updated_at?->toISOString() !== $this->editUpdatedAt) {
            session()->flash('warning', 'Der Roman wurde zwischenzeitlich von jemand anderem geändert. Bitte erneut öffnen.');
            $this->showEditModal = false;

            return;
        }

        if ($roman->status === 'indexierung_laeuft') {
            session()->flash('warning', 'Roman kann während der Indexierung nicht bearbeitet werden.');
            $this->showEditModal = false;

            return;
        }

        // Neuen Dateipfad berechnen (Titel sanitizen gegen Path-Traversal)
        $sichererTitel = $this->sanitizeTitelFuerPfad($this->editTitel);
        if ($sichererTitel === '') {
            $this->addError('editTitel', 'Der Titel darf nicht nur aus Sonderzeichen bestehen.');

            return;
        }
        $neuerDateiname = str_pad($this->editNummer, 3, '0', STR_PAD_LEFT).' - '.$sichererTitel.'.txt';
        $neuerPfad = "romane/{$this->editSerie}/{$neuerDateiname}";
        $alterPfad = $roman->dateipfad;

        // Duplikat-Prüfung (anderer Roman mit gleichem Pfad)
        if ($alterPfad !== $neuerPfad && KompendiumRoman::where('dateipfad', $neuerPfad)->where('id', '!=', $roman->id)->exists()) {
            $this->addError('editTitel', 'Ein Roman mit diesem Pfad existiert bereits.');

            return;
        }

        $warIndexiert = $roman->status === 'indexiert';
        $pfadGeaendert = $alterPfad !== $neuerPfad;

        // Falls Pfad geändert: Datei verschieben (mit Error Handling)
        if ($pfadGeaendert) {
            if (! Storage::disk('private')->exists($alterPfad)) {
                Log::warning('Kompendium: Datei fehlt bei Pfadänderung.', [
                    'roman_id' => $roman->id,
                    'alter_pfad' => $alterPfad,
                    'neuer_pfad' => $neuerPfad,
                ]);
            } else {
                try {
                    $zielVerzeichnis = dirname($neuerPfad);
                    if (! Storage::disk('private')->exists($zielVerzeichnis)) {
                        Storage::disk('private')->makeDirectory($zielVerzeichnis);
                    }
                    Storage::disk('private')->move($alterPfad, $neuerPfad);
                } catch (\Throwable $e) {
                    Log::error('Kompendium: Datei konnte nicht verschoben werden.', [
                        'roman_id' => $roman->id,
                        'alter_pfad' => $alterPfad,
                        'neuer_pfad' => $neuerPfad,
                        'fehler' => $e->getMessage(),
                    ]);
                    session()->flash('error', 'Fehler beim Verschieben der Datei: '.$e->getMessage());
                    $this->showEditModal = false;

                    return;
                }
            }
        }

        // DB aktualisieren (ein einzelner Update-Aufruf für alle Felder)
        $updateDaten = [
            'serie' => $this->editSerie,
            'zyklus' => $this->editZyklus ?: null,
            'roman_nr' => $this->editNummer,
            'titel' => $sichererTitel,
            'dateiname' => $neuerDateiname,
            'dateipfad' => $neuerPfad,
        ];

        // Falls indexiert und Pfad geändert: Status zurücksetzen für Re-Indexierung
        if ($warIndexiert && $pfadGeaendert) {
            $searchService = app(KompendiumSearchService::class);
            $searchService->removeFromIndex($alterPfad);
            $updateDaten['status'] = 'hochgeladen';
            $updateDaten['indexiert_am'] = null;
        }

        $roman->update($updateDaten);

        // Re-Indexierung anstoßen falls nötig
        if ($warIndexiert && $pfadGeaendert) {
            IndexiereRomanJob::dispatch($roman->fresh());
            session()->flash('info', "Roman \"{$sichererTitel}\" aktualisiert. Re-Indexierung gestartet.");
        } else {
            session()->flash('success', "Roman \"{$sichererTitel}\" aktualisiert.");
        }

        $this->showEditModal = false;
        unset($this->romane, $this->statistiken, $this->romanZahlenProSerie, $this->zyklenFortschritt);
    }

    /* --------------------------------------------------------------------- */
    /*  Indexierung & Löschung */
    /* --------------------------------------------------------------------- */

    /**
     * Bereinigt einen Titel für die Verwendung in Dateipfaden.
     * Entfernt Pfadseparatoren, Traversal-Sequenzen und gefährliche Zeichen.
     */
    private function sanitizeTitelFuerPfad(string $titel): string
    {
        // Steuerzeichen und nicht-druckbare Zeichen entfernen
        $titel = preg_replace('/[\x00-\x1F\x7F]/', '', $titel) ?? $titel;

        // Pfadseparatoren entfernen
        $titel = str_replace(['/', '\\'], '', $titel);

        // Traversal-Sequenzen iterativ entfernen (verhindert Bypass durch verschachtelte Muster wie '....//') 
        do {
            $vorher = $titel;
            $titel = str_replace('..', '', $titel);
        } while ($titel !== $vorher);

        // Mehrfache Leerzeichen normalisieren und trimmen
        $titel = trim(preg_replace('/\s+/', ' ', $titel) ?? $titel);

        return $titel;
    }

    public function indexieren(int $id): void
    {
        $roman = KompendiumRoman::findOrFail($id);

        if ($roman->status === 'indexierung_laeuft') {
            session()->flash('warning', 'Indexierung läuft bereits.');

            return;
        }

        IndexiereRomanJob::dispatch($roman);
        session()->flash('info', "Indexierung von \"{$roman->titel}\" gestartet.");

        unset($this->romane, $this->statistiken, $this->romanZahlenProSerie, $this->zyklenFortschritt);
    }

    public function deIndexieren(int $id): void
    {
        $roman = KompendiumRoman::findOrFail($id);

        if ($roman->status !== 'indexiert') {
            session()->flash('warning', 'Roman ist nicht indexiert.');

            return;
        }

        DeIndexiereRomanJob::dispatch($roman);
        session()->flash('info', "De-Indexierung von \"{$roman->titel}\" gestartet.");

        unset($this->romane, $this->statistiken, $this->romanZahlenProSerie, $this->zyklenFortschritt);
    }

    public function loeschen(int $id, KompendiumSearchService $searchService): void
    {
        $roman = KompendiumRoman::findOrFail($id);
        $titel = $roman->titel;

        // Erst de-indexieren falls indexiert
        if ($roman->status === 'indexiert') {
            $searchService->removeFromIndex($roman->dateipfad);
        }

        // Datei löschen
        Storage::disk('private')->delete($roman->dateipfad);

        // DB-Eintrag löschen
        $roman->delete();

        session()->flash('success', "Roman \"{$titel}\" gelöscht.");

        unset($this->romane, $this->statistiken, $this->romanZahlenProSerie, $this->zyklenFortschritt);
    }

    public function alleIndexieren(): void
    {
        $romane = KompendiumRoman::where('status', 'hochgeladen')->get();

        if ($romane->isEmpty()) {
            session()->flash('warning', 'Keine Romane zum Indexieren vorhanden.');

            return;
        }

        foreach ($romane as $roman) {
            IndexiereRomanJob::dispatch($roman);
        }

        session()->flash('info', "{$romane->count()} Romane zur Indexierung eingereiht.");

        unset($this->romane, $this->statistiken, $this->romanZahlenProSerie, $this->zyklenFortschritt);
    }

    public function alleDeIndexieren(): void
    {
        $romane = KompendiumRoman::where('status', 'indexiert')->get();

        if ($romane->isEmpty()) {
            session()->flash('warning', 'Keine indexierten Romane vorhanden.');

            return;
        }

        foreach ($romane as $roman) {
            DeIndexiereRomanJob::dispatch($roman);
        }

        session()->flash('info', "{$romane->count()} Romane zur De-Indexierung eingereiht.");

        unset($this->romane, $this->statistiken, $this->romanZahlenProSerie, $this->zyklenFortschritt);
    }

    public function retryFehler(int $id): void
    {
        $roman = KompendiumRoman::findOrFail($id);

        if ($roman->status !== 'fehler') {
            return;
        }

        $roman->update([
            'status' => 'hochgeladen',
            'fehler_nachricht' => null,
        ]);

        IndexiereRomanJob::dispatch($roman);
        session()->flash('info', "Erneuter Indexierungsversuch für \"{$roman->titel}\" gestartet.");

        unset($this->romane, $this->statistiken, $this->romanZahlenProSerie, $this->zyklenFortschritt);
    }

    public function render()
    {
        return view('livewire.kompendium-admin-dashboard');
    }
}
