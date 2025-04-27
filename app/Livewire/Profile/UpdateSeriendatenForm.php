<?php
// app\Livewire\Profile\UpdateSeriendatenForm.php
namespace App\Livewire\Profile;

use Livewire\Component;
use App\Actions\Fortify\UpdateUserSeriendaten;
use Illuminate\Support\Facades\Auth;
use App\Services\MaddraxDataService;

class UpdateSeriendatenForm extends Component
{
    public array $state = [];
    public array $autoren = [];
    public array $zyklen = [];
    public array $romane = [];
    public array $figuren = [];
    public array $schauplaetze = [];

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
        ]);
        $this->autoren = MaddraxDataService::getAutoren();
        $this->zyklen = MaddraxDataService::getZyklen();
        $this->romane = MaddraxDataService::getRomane();
        $this->figuren = MaddraxDataService::getFiguren();
        $this->schauplaetze = MaddraxDataService::getSchauplaetze();
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
