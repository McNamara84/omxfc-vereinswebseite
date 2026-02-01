<?php

namespace App\Livewire;

use App\Jobs\DeIndexiereRomanJob;
use App\Jobs\IndexiereRomanJob;
use App\Models\KompendiumRoman;
use App\Models\RomanExcerpt;
use App\Services\KompendiumService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Livewire-Komponente für die Admin-Verwaltung des Kompendiums.
 *
 * Ermöglicht das Hochladen, Indexieren, De-Indexieren und Löschen von Romantexten.
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

    public string $filterSerie = '';

    public string $filterStatus = '';

    public string $suchbegriff = '';

    protected function rules(): array
    {
        return [
            'uploads.*' => 'required|file|mimes:txt|max:10240', // 10 MB
            'ausgewaehlteSerie' => 'required|in:maddrax,hardcovers,missionmars,volkdertiefe,2012,abenteurer',
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
            ->when($this->filterSerie, fn ($q) => $q->where('serie', $this->filterSerie))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->suchbegriff, fn ($q) => $q->where(function ($query) {
                $query->where('titel', 'like', "%{$this->suchbegriff}%")
                    ->orWhere('roman_nr', 'like', "%{$this->suchbegriff}%");
            }))
            ->orderBy('serie')
            ->orderBy('roman_nr')
            ->paginate(25);
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

            // Metadaten aus JSON laden
            $meta = $service->findeMetadaten($parsed['nummer'], $parsed['titel']);

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

        unset($this->romane, $this->statistiken);
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

        unset($this->romane, $this->statistiken);
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

        unset($this->romane, $this->statistiken);
    }

    public function loeschen(int $id): void
    {
        $roman = KompendiumRoman::findOrFail($id);
        $titel = $roman->titel;

        // Erst de-indexieren falls indexiert
        if ($roman->status === 'indexiert') {
            $excerpt = new RomanExcerpt(['path' => $roman->dateipfad]);
            $excerpt->unsearchable();
        }

        // Datei löschen
        Storage::disk('private')->delete($roman->dateipfad);

        // DB-Eintrag löschen
        $roman->delete();

        session()->flash('success', "Roman \"{$titel}\" gelöscht.");

        unset($this->romane, $this->statistiken);
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

        unset($this->romane, $this->statistiken);
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

        unset($this->romane, $this->statistiken);
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

        unset($this->romane, $this->statistiken);
    }

    public function render()
    {
        return view('livewire.kompendium-admin-dashboard');
    }
}
