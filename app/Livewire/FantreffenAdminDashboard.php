<?php

namespace App\Livewire;

use App\Models\FantreffenAnmeldung;
use App\Enums\Role;
use Illuminate\Support\Facades\Response;
use Livewire\Component;
use Livewire\WithPagination;

class FantreffenAdminDashboard extends Component
{
    use WithPagination;

    // Filter-Properties
    public $filterMemberStatus = 'alle'; // alle, mitglieder, gaeste
    public $filterTshirt = 'alle'; // alle, mit_tshirt, ohne_tshirt
    public $filterPayment = 'alle'; // alle, bezahlt, ausstehend, kostenlos
    public $filterZahlungseingang = 'alle'; // alle, erhalten, ausstehend
    public $filterTshirtFertig = 'alle'; // alle, fertig, offen
    public $search = '';

    // Statistik-Properties
    public $stats = [];

    protected $queryString = [
        'filterMemberStatus' => ['except' => 'alle'],
        'filterTshirt' => ['except' => 'alle'],
        'filterPayment' => ['except' => 'alle'],
        'filterZahlungseingang' => ['except' => 'alle'],
        'filterTshirtFertig' => ['except' => 'alle'],
        'search' => ['except' => ''],
    ];

    public function mount()
    {
        $this->calculateStats();
    }

    public function updatedFilterMemberStatus()
    {
        $this->resetPage();
        $this->calculateStats();
    }

    public function updatedFilterTshirt()
    {
        $this->resetPage();
        $this->calculateStats();
    }

    public function updatedFilterPayment()
    {
        $this->resetPage();
        $this->calculateStats();
    }

    public function updatedFilterZahlungseingang()
    {
        $this->resetPage();
        $this->calculateStats();
    }

    public function updatedFilterTshirtFertig()
    {
        $this->resetPage();
        $this->calculateStats();
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function toggleZahlungseingang($anmeldungId)
    {
        $anmeldung = FantreffenAnmeldung::findOrFail($anmeldungId);
        $anmeldung->zahlungseingang = !$anmeldung->zahlungseingang;
        $anmeldung->save();

        $this->calculateStats();
        session()->flash('success', 'Zahlungseingang aktualisiert.');
    }

    public function toggleTshirtFertig($anmeldungId)
    {
        $anmeldung = FantreffenAnmeldung::findOrFail($anmeldungId);
        $anmeldung->tshirt_fertig = !$anmeldung->tshirt_fertig;
        $anmeldung->save();

        $this->calculateStats();
        session()->flash('success', 'T-Shirt-Status aktualisiert.');
    }

    public function deleteAnmeldung($anmeldungId)
    {
        $anmeldung = FantreffenAnmeldung::findOrFail($anmeldungId);
        $name = $anmeldung->full_name;
        $anmeldung->delete();

        $this->calculateStats();
        session()->flash('success', "Anmeldung von {$name} wurde gelöscht.");
    }

    public function toggleOrgaTeam($anmeldungId)
    {
        $user = auth()->user();

        if (!$user || !$user->hasAnyRole(Role::Admin, Role::Vorstand, Role::Kassenwart)) {
            abort(403);
        }

        $anmeldung = FantreffenAnmeldung::findOrFail($anmeldungId);

        if (!$anmeldung->ist_mitglied) {
            session()->flash('error', 'Nur Mitglieder können dem Orga-Team hinzugefügt werden.');
            return;
        }

        $anmeldung->syncPaymentForOrgaStatus(!$anmeldung->orga_team);

        $this->calculateStats();
        session()->flash('success', $anmeldung->orga_team ? 'Anmeldung dem Orga-Team zugewiesen.' : 'Orga-Team Status entfernt.');
    }

    public function exportCsv()
    {
        $anmeldungen = $this->getFilteredQuery()->get();

        $csv = "Name,Email,Mobil,Mitglied,Orga-Team,T-Shirt,Größe,Zahlungsstatus,Betrag,Zahlungseingang,T-Shirt fertig,PayPal ID,Registriert am\n";

        foreach ($anmeldungen as $anmeldung) {
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s","%s"' . "\n",
                $anmeldung->full_name,
                $anmeldung->registrant_email,
                $anmeldung->mobile ?? '-',
                $anmeldung->ist_mitglied ? 'Ja' : 'Nein',
                $anmeldung->orga_team ? 'Ja' : 'Nein',
                $anmeldung->tshirt_bestellt ? 'Ja' : 'Nein',
                $anmeldung->tshirt_groesse ?? '-',
                match ($anmeldung->payment_status) {
                    'paid' => 'Bezahlt',
                    'pending' => 'Ausstehend',
                    'free' => 'Kostenlos',
                    default => $anmeldung->payment_status
                },
                number_format((float) $anmeldung->payment_amount, 2, ',', '.') . ' €',
                $anmeldung->zahlungseingang ? 'Ja' : 'Nein',
                $anmeldung->tshirt_fertig ? 'Ja' : 'Nein',
                $anmeldung->paypal_transaction_id ?? '-',
                $anmeldung->created_at->format('d.m.Y H:i')
            );
        }

        return Response::streamDownload(function () use ($csv) {
            echo "\xEF\xBB\xBF"; // UTF-8 BOM für Excel
            echo $csv;
        }, 'fantreffen-anmeldungen-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function getFilteredQuery()
    {
        $query = FantreffenAnmeldung::query()->with('user');

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
            $query->where('tshirt_bestellt', false);
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
            $query->where('tshirt_fertig', true);
        } elseif ($this->filterTshirtFertig === 'offen') {
            $query->where('tshirt_fertig', false)->where('tshirt_bestellt', true);
        }

        // Suchfilter
        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('vorname', 'like', '%' . $this->search . '%')
                    ->orWhere('nachname', 'like', '%' . $this->search . '%')
                    ->orWhere('email', 'like', '%' . $this->search . '%')
                    ->orWhereHas('user', function ($userQuery) {
                        $userQuery->where('vorname', 'like', '%' . $this->search . '%')
                            ->orWhere('nachname', 'like', '%' . $this->search . '%')
                            ->orWhere('email', 'like', '%' . $this->search . '%');
                    });
            });
        }

        return $query->latest()->orderByDesc('id');
    }

    protected function calculateStats()
    {
        $query = $this->getFilteredQuery();

        $this->stats = [
            'total' => $query->count(),
            'mitglieder' => (clone $query)->where('ist_mitglied', true)->count(),
            'gaeste' => (clone $query)->where('ist_mitglied', false)->count(),
            'tshirts' => (clone $query)->where('tshirt_bestellt', true)->count(),
            'zahlungen_ausstehend' => (clone $query)->where('payment_status', 'pending')->where('zahlungseingang', false)->count(),
            'zahlungen_offen_betrag' => (clone $query)->where('payment_status', 'pending')->where('zahlungseingang', false)->sum('payment_amount'),
            'tshirts_offen' => (clone $query)->where('tshirt_bestellt', true)->where('tshirt_fertig', false)->count(),
        ];
    }

    public function render()
    {
        $anmeldungen = $this->getFilteredQuery()->paginate(20);

        return view('livewire.fantreffen-admin-dashboard', [
            'anmeldungen' => $anmeldungen,
        ])->layout('layouts.app', [
            'title' => 'Fantreffen 2026 - Admin Dashboard',
        ]);
    }
}
