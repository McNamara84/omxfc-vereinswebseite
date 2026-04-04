<?php

namespace App\Livewire;

use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Changelog extends Component
{
    #[Computed(cache: true, seconds: 3600)]
    public function releases(): Collection
    {
        $path = public_path('changelog.json');

        if (! file_exists($path)) {
            return collect();
        }

        $data = json_decode(file_get_contents($path), true);

        if (! is_array($data)) {
            return collect();
        }

        return collect($data)->sortByDesc('pub_date')->values();
    }

    public function render()
    {
        return view('livewire.changelog');
    }
}
