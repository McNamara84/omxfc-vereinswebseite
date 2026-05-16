<?php

namespace App\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;

class Changelog extends Component
{
    #[Computed(cache: true, seconds: 3600)]
    public function releases(): array
    {
        $path = config('app.changelog_path', public_path('changelog.json'));

        if (! file_exists($path)) {
            return [];
        }

        $data = json_decode(file_get_contents($path), true);

        if (! is_array($data)) {
            return [];
        }

        return collect($data)->sortByDesc('pub_date')->values()->all();
    }

    public function render()
    {
        return view('livewire.changelog');
    }
}
