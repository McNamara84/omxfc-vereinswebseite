<?php

namespace App\Livewire;

use App\Enums\Role;
use App\Models\FantreffenAnmeldung;
use App\Models\FantreffenAnmeldungMerchartikel;
use App\Models\Veranstaltung;
use App\Services\VeranstaltungsBaxxService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class FantreffenAdminDashboard extends Component
{
    use WithPagination;

    #[Locked]
    public ?Veranstaltung $veranstaltung = null;

    #[Url(except: 'alle')]
    public string $filterTeilnahme = 'alle';

    #[Computed]
    public function eligibleUserIds(): Collection
    {
        return app(VeranstaltungsBaxxService::class)->eligibleUserIds($this->currentVeranstaltung());
    }

    #[Computed]
    public function baxxVergaben(): Collection
    {
        return $this->currentVeranstaltung()->baxxVergaben()->get()->keyBy('user_id');
    }

    #[Computed]
    public function baxxSummary(): array
    {
        $event = $this->currentVeranstaltung();
        $confirmed = $event->anmeldungen()->where('teilgenommen', true);
        $eligible = (clone $confirmed)->whereIn('user_id', $this->eligibleUserIds)->count();

        return [
            'eligible' => $eligible,
            'ineligible' => $confirmed->count() - $eligible,
            'expected' => $eligible * $event->teilnahme_baxx,
            'awarded' => $this->baxxVergaben->count(),
            'total' => (int) $this->baxxVergaben->sum('points'),
        ];
    }

    public function updatedFilterTeilnahme(): void
    {
        $this->resetPage();
        unset($this->stats, $this->anmeldungen);
    }

    public function setTeilnahme(int $anmeldungId, mixed $teilgenommen): void
    {
        $this->authorize('confirmAttendance', $this->currentVeranstaltung());
        $this->resetValidation(['teilnahme', 'teilgenommen']);
        $validated = Validator::make(['teilgenommen' => $teilgenommen], ['teilgenommen' => ['required', 'boolean']])->validate();
        app(VeranstaltungsBaxxService::class)->setTeilnahme(
            $this->currentVeranstaltung(), $anmeldungId, (bool) $validated['teilgenommen'], auth()->user()
        );
        unset($this->stats, $this->anmeldungen, $this->baxxSummary, $this->eligibleUserIds);
        session()->flash('success', 'Teilnahmebestätigung gespeichert. Baxx werden erst beim Archivieren vergeben.');
    }

    // URL-Query-Parameter automatisch synchronisieren
    #[Url(except: 'alle')]
    public string $filterMemberStatus = 'alle'; // alle, mitglieder, gaeste

    #[Url(except: 'alle')]
    public string $filterTshirt = 'alle'; // alle, mit_tshirt, ohne_tshirt

    #[Url(except: 'alle')]
    public string $filterPayment = 'alle'; // alle, bezahlt, ausstehend, kostenlos

    #[Url(except: 'alle')]
    public string $filterZahlungseingang = 'alle'; // alle, erhalten, ausstehend

    #[Url(except: 'alle')]
    public string $filterTshirtFertig = 'alle'; // alle, fertig, offen

    #[Url(except: '')]
    public string $search = '';

    /**
     * Statistiken als Computed Property - wird automatisch gecached und bei Bedarf neu berechnet.
     */
    #[Computed]
    public function stats(): array
    {
        $query = $this->getFilteredQuery();

        return [
            'total' => $query->count(),
            'mitglieder' => (clone $query)->where('ist_mitglied', true)->count(),
            'gaeste' => (clone $query)->where('ist_mitglied', false)->count(),
            'tshirts' => (clone $query)->mitTshirt()->count(),
            'zahlungen_ausstehend' => (clone $query)->where('payment_status', 'pending')->where('zahlungseingang', false)->count(),
            'zahlungen_offen_betrag' => (clone $query)->where('payment_status', 'pending')->where('zahlungseingang', false)->sum('payment_amount'),
            'tshirts_offen' => (clone $query)->mitOffenemMerch()->count(),
        ];
    }

    /**
     * Paginierte Anmeldungen als Computed Property.
     */
    #[Computed]
    public function anmeldungen()
    {
        return $this->getFilteredQuery()->paginate(20);
    }

    public function mount(?Veranstaltung $veranstaltung = null): void
    {
        $this->veranstaltung = $veranstaltung ?? Veranstaltung::featuredPublic() ?? Veranstaltung::query()->orderByDesc('ist_highlight')->firstOrFail();
    }

    protected function currentVeranstaltung(): Veranstaltung
    {
        if ($this->veranstaltung instanceof Veranstaltung && $this->veranstaltung->exists) {
            return $this->veranstaltung;
        }

        return $this->veranstaltung = Veranstaltung::featuredPublic() ?? Veranstaltung::query()->orderByDesc('ist_highlight')->firstOrFail();
    }

    protected function findAnmeldung(int $anmeldungId): FantreffenAnmeldung
    {
        return FantreffenAnmeldung::query()
            ->where('veranstaltung_id', $this->currentVeranstaltung()->id)
            ->with(['merchartikelBestellungen.artikel', 'merchartikelBestellungen.variante'])
            ->findOrFail($anmeldungId);
    }

    protected function findMerchBestellung(int $bestellungId): FantreffenAnmeldungMerchartikel
    {
        return FantreffenAnmeldungMerchartikel::query()
            ->with(['anmeldung', 'artikel', 'variante'])
            ->whereHas('anmeldung', function ($query) {
                $query->where('veranstaltung_id', $this->currentVeranstaltung()->id);
            })
            ->findOrFail($bestellungId);
    }

    public function updatedFilterMemberStatus(): void
    {
        $this->resetPage();
        unset($this->stats, $this->anmeldungen);
    }

    public function updatedFilterTshirt(): void
    {
        $this->resetPage();
        unset($this->stats, $this->anmeldungen);
    }

    public function updatedFilterPayment(): void
    {
        $this->resetPage();
        unset($this->stats, $this->anmeldungen);
    }

    public function updatedFilterZahlungseingang(): void
    {
        $this->resetPage();
        unset($this->stats, $this->anmeldungen);
    }

    public function updatedFilterTshirtFertig(): void
    {
        $this->resetPage();
        unset($this->stats, $this->anmeldungen);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        unset($this->anmeldungen);
    }

    public function toggleZahlungseingang(int $anmeldungId): void
    {
        $anmeldung = $this->findAnmeldung($anmeldungId);
        $anmeldung->zahlungseingang = ! $anmeldung->zahlungseingang;
        $anmeldung->save();

        unset($this->stats, $this->anmeldungen);
        session()->flash('success', 'Zahlungseingang aktualisiert.');
    }

    public function toggleTshirtFertig(int $anmeldungId): void
    {
        $anmeldung = $this->findAnmeldung($anmeldungId);

        $bestellung = $anmeldung->merchartikelBestellungen->first();

        if ($bestellung) {
            $this->toggleMerchBestellungStatus($bestellung);
        } else {
            $anmeldung->tshirt_fertig = ! $anmeldung->tshirt_fertig;
            $anmeldung->save();
        }

        unset($this->stats, $this->anmeldungen);
        session()->flash('success', 'Merchandise-Status aktualisiert.');
    }

    public function toggleMerchFertig(int $bestellungId): void
    {
        $bestellung = $this->findMerchBestellung($bestellungId);

        $this->toggleMerchBestellungStatus($bestellung);

        unset($this->stats, $this->anmeldungen);
        session()->flash('success', 'Merchandise-Status aktualisiert.');
    }

    public function deleteAnmeldung(int $anmeldungId): void
    {
        $this->authorize('manage', $this->currentVeranstaltung());
        $this->resetValidation('teilnahme');
        $name = app(VeranstaltungsBaxxService::class)->deleteAnmeldung($this->currentVeranstaltung(), $anmeldungId, auth()->user());

        unset($this->stats, $this->anmeldungen, $this->baxxSummary, $this->eligibleUserIds);
        session()->flash('success', "Anmeldung von {$name} wurde gelöscht.");
    }

    public function toggleOrgaTeam(int $anmeldungId): void
    {
        $user = auth()->user();

        if (! $user || ! $user->hasAnyRole(Role::Admin, Role::Vorstand, Role::Kassenwart)) {
            abort(403);
        }

        $anmeldung = $this->findAnmeldung($anmeldungId);

        if (! $anmeldung->ist_mitglied) {
            session()->flash('error', 'Nur Mitglieder können dem Orga-Team hinzugefügt werden.');

            return;
        }

        $anmeldung->syncPaymentForOrgaStatus(! $anmeldung->orga_team);

        unset($this->stats, $this->anmeldungen);
        session()->flash('success', $anmeldung->orga_team ? 'Anmeldung dem Orga-Team zugewiesen.' : 'Orga-Team Status entfernt.');
    }

    public function exportCsv()
    {
        $anmeldungen = $this->getFilteredQuery()->get();
        $vergaben = $this->baxxVergaben;

        return Response::streamDownload(function () use ($anmeldungen, $vergaben) {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            echo "\xEF\xBB\xBF"; // UTF-8 BOM für Excel

            fputcsv($output, [
                'Name',
                'Email',
                'Mobil',
                'Mitglied',
                'Orga-Team',
                'Merchandise',
                'Merch-Status',
                'Zahlungsstatus',
                'Betrag',
                'Zahlungseingang',
                'PayPal ID',
                'Registriert am',
                'Teilnahme bestätigt',
                'Teilnahme bestätigt am',
                'Gutgeschriebene Baxx',
                'Baxx vergeben am',
            ]);

            foreach ($anmeldungen as $anmeldung) {
                $vergabe = $vergaben->get($anmeldung->user_id);
                $orderedMerchandise = $anmeldung->ordered_merchandise;

                $merchandise = $orderedMerchandise->map(function (array $bestellung) {
                    return $bestellung['name'].($bestellung['variant'] ? ' ('.$bestellung['variant'].')' : '');
                })->implode('; ');

                $merchStatus = $orderedMerchandise->map(function (array $bestellung) {
                    return $bestellung['name'].': '.($bestellung['done'] ? 'erledigt' : 'offen');
                })->implode('; ');

                fputcsv($output, $this->sanitizeCsvRow([
                    $anmeldung->full_name,
                    $anmeldung->registrant_email,
                    $anmeldung->mobile ?? '-',
                    $anmeldung->ist_mitglied ? 'Ja' : 'Nein',
                    $anmeldung->orga_team ? 'Ja' : 'Nein',
                    $merchandise !== '' ? $merchandise : '-',
                    $merchStatus !== '' ? $merchStatus : '-',
                    match ($anmeldung->payment_status) {
                        'paid' => 'Bezahlt',
                        'pending' => 'Ausstehend',
                        'free' => 'Kostenlos',
                        default => $anmeldung->payment_status
                    },
                    number_format((float) $anmeldung->payment_amount, 2, ',', '.').' €',
                    $anmeldung->zahlungseingang ? 'Ja' : 'Nein',
                    $anmeldung->paypal_transaction_id ?? '-',
                    $anmeldung->created_at->format('d.m.Y H:i'),
                    $anmeldung->teilgenommen ? 'Ja' : 'Nicht bestätigt',
                    $anmeldung->teilnahme_bestaetigt_am?->format('d.m.Y H:i') ?? '',
                    $vergabe?->points ?? 0,
                    $vergabe?->created_at?->format('d.m.Y H:i') ?? '',
                ]));
            }

            fclose($output);
        }, $this->currentVeranstaltung()->slug.'-anmeldungen-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function getFilteredQuery()
    {
        $query = FantreffenAnmeldung::query()
            ->with(['user', 'merchartikelBestellungen.artikel', 'merchartikelBestellungen.variante'])
            ->where('veranstaltung_id', $this->currentVeranstaltung()->id);

        if (in_array($this->filterTeilnahme, ['bestaetigt', 'offen'], true)) {
            $query->where('teilgenommen', $this->filterTeilnahme === 'bestaetigt');
        }

        // Mitgliedsstatus-Filter
        if ($this->filterMemberStatus === 'mitglieder') {
            $query->mitglieder();
        } elseif ($this->filterMemberStatus === 'gaeste') {
            $query->gaeste();
        }

        // T-Shirt-Filter
        if ($this->filterTshirt === 'mit_tshirt') {
            $query->mitTshirt();
        } elseif ($this->filterTshirt === 'ohne_tshirt') {
            $query->ohneMerch();
        }

        // Payment-Filter
        if ($this->filterPayment === 'bezahlt') {
            $query->bezahlt();
        } elseif ($this->filterPayment === 'ausstehend') {
            $query->zahlungAusstehend();
        } elseif ($this->filterPayment === 'kostenlos') {
            $query->where('payment_status', 'free');
        }

        // Zahlungseingang-Filter
        if ($this->filterZahlungseingang === 'erhalten') {
            $query->where('zahlungseingang', true);
        } elseif ($this->filterZahlungseingang === 'ausstehend') {
            $query->where('zahlungseingang', false)->where('payment_status', '!=', 'free');
        }

        // T-Shirt fertig-Filter
        if ($this->filterTshirtFertig === 'fertig') {
            $query->mitErledigtemMerch();
        } elseif ($this->filterTshirtFertig === 'offen') {
            $query->mitOffenemMerch();
        }

        // Suchfilter
        if (! empty($this->search)) {
            $query->where(function ($q) {
                $q->where('vorname', 'like', '%'.$this->search.'%')
                    ->orWhere('nachname', 'like', '%'.$this->search.'%')
                    ->orWhere('email', 'like', '%'.$this->search.'%')
                    ->orWhereHas('user', function ($userQuery) {
                        $userQuery->where('vorname', 'like', '%'.$this->search.'%')
                            ->orWhere('nachname', 'like', '%'.$this->search.'%')
                            ->orWhere('email', 'like', '%'.$this->search.'%');
                    });
            });
        }

        return $query->latest()->orderByDesc('id');
    }

    public function placeholder()
    {
        return view('components.skeleton-card', ['cols' => 2, 'rows' => 3]);
    }

    public function render()
    {
        $veranstaltung = $this->currentVeranstaltung()->refresh();

        return view('livewire.fantreffen-admin-dashboard', [
            'veranstaltung' => $veranstaltung,
            'bearbeitenUrl' => route('admin.veranstaltungen.edit', ['veranstaltung' => $veranstaltung]),
            'vipAutorenUrl' => route('admin.veranstaltungen.vip-authors', ['veranstaltung' => $veranstaltung]),
        ])
            ->layout('layouts.member', [
                'title' => $veranstaltung->titel.' - Anmeldungen',
            ]);
    }

    private function toggleMerchBestellungStatus(FantreffenAnmeldungMerchartikel $bestellung): void
    {
        $bestellung->status_erledigt = ! $bestellung->status_erledigt;
        $bestellung->status_erledigt_am = $bestellung->status_erledigt ? now() : null;
        $bestellung->save();

        if (mb_strtolower($bestellung->artikel?->bezeichnung ?? '') === 't-shirt') {
            $bestellung->anmeldung?->update([
                'tshirt_fertig' => $bestellung->status_erledigt,
            ]);
        }
    }

    private function sanitizeCsvRow(array $values): array
    {
        return array_map(fn (mixed $value) => $this->sanitizeCsvValue($value), $values);
    }

    private function sanitizeCsvValue(mixed $value): string
    {
        $stringValue = str_replace(["\r\n", "\r"], "\n", (string) ($value ?? '-'));

        if (preg_match('/^\s*[=+\-@]/u', $stringValue) === 1) {
            return "'".$stringValue;
        }

        return $stringValue;
    }
}
