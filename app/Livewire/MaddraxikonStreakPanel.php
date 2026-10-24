<?php

namespace App\Livewire;

use App\Models\MaddraxikonStreakBooking;
use App\Models\User;
use App\Services\Maddraxikon\MaddraxikonStreakSummary;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class MaddraxikonStreakPanel extends Component
{
    use WithPagination;

    #[Locked]
    public bool $detailed = false;

    public function render(MaddraxikonStreakSummary $summary)
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);
        $data = $summary->forUser($user);

        return view('livewire.maddraxikon-streak-panel', [
            'streak' => $data,
            'bookings' => $data && $this->detailed
                ? MaddraxikonStreakBooking::query()->where('user_id', $user->id)
                    ->whereHas('day', fn ($query) => $query->where('wiki_key', config('maddraxikon.wiki_key')))
                    ->with('day')->latest('id')->paginate(10, pageName: 'streakPage') : null,
        ]);
    }
}
