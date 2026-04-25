<?php

namespace App\Livewire;

use App\Models\BookOffer;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RomantauschShowOffer extends Component
{
    #[Locked]
    public int $offerId;

    public function mount(BookOffer $offer): void
    {
        $user = Auth::user();

        $isOwner = $user->id === $offer->user_id;
        $swap = $offer->swap;
        $isSwapPartner = $swap && $swap->request !== null && $user->id === $swap->request->user_id;

        abort_unless($isOwner || $isSwapPartner, 403);

        $this->offerId = $offer->id;
    }

    #[Computed]
    public function offer(): BookOffer
    {
        return BookOffer::findOrFail($this->offerId);
    }

    public function placeholder()
    {
        return view('components.skeleton-detail', ['hasImage' => true, 'sections' => 2]);
    }

    public function render()
    {
        return view('livewire.romantausch-show-offer', [
            'offer' => $this->offer,
        ])->layout('layouts.app', ['title' => 'Angebotsdetails']);
    }
}
