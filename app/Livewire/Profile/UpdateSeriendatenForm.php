<?php
// app\Livewire\Profile\UpdateSeriendatenForm.php
namespace App\Livewire\Profile;

use Livewire\Component;
use App\Actions\Fortify\UpdateUserSeriendaten;
use Illuminate\Support\Facades\Auth;
use App\Services\MaddraxDataService;
use App\Models\Book;
use App\Enums\BookType;

class UpdateSeriendatenForm extends Component
{
    public array $state = [];
    public array $autoren = [];
    public array $zyklen = [];
    public array $romane = [];
    public array $figuren = [];
    public array $schauplaetze = [];
    public array $schlagworte = [];
    public array $hardcover = [];
    public array $covers = [];

    public function mount()
    {
        $this->state = Auth::user()->only([
            'einstiegsroman',
            'lesestand',
            'lieblingsroman',
            'lieblingsfigur',
            'lieblingsmutation',
            'lieblingsschauplatz',
            'lieblingsautor',
            'lieblingszyklus',
            'lieblingsthema',
            'lieblingshardcover',
            'lieblingscover',
        ]);
        $this->autoren = MaddraxDataService::getAutoren();
        $this->zyklen = MaddraxDataService::getZyklen();
        $this->romane = MaddraxDataService::getRomane();
        $this->figuren = MaddraxDataService::getFiguren();
        $this->schauplaetze = MaddraxDataService::getSchauplaetze();
        $this->schlagworte = MaddraxDataService::getSchlagworte();
        $this->hardcover = Book::where('type', BookType::MaddraxHardcover)
            ->orderBy('roman_number')
            ->get()
            ->map(fn ($book) => ($book->roman_number ? $book->roman_number.' - ' : '').$book->title)
            ->toArray();

        $romanCoverNumbers = collect($this->romane)
            ->map(fn ($roman) => 'Roman ' . explode(' - ', $roman)[0])
            ->toArray();

        $hardcoverCoverNumbers = Book::where('type', BookType::MaddraxHardcover)
            ->whereNotNull('roman_number')
            ->orderBy('roman_number')
            ->pluck('roman_number')
            ->map(fn ($num) => 'Hardcover ' . $num)
            ->toArray();

        $this->covers = array_merge($romanCoverNumbers, $hardcoverCoverNumbers);
    }

    public function updateSeriendaten(UpdateUserSeriendaten $updater)
    {
        $updater->update(Auth::user(), $this->state);
        $this->dispatch('saved');
    }

    public function render()
    {
        return view('profile.update-seriendaten-form');
    }
}
