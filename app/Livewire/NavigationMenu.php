<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;

class NavigationMenu extends Component
{
    public string $variant = 'public-navbar';

    /** @var array<string, string> */
    protected $listeners = [
        'refresh-navigation-menu' => '$refresh',
    ];

    public function mount(string $variant = 'public-navbar'): void
    {
        $this->variant = in_array($variant, $this->allowedVariants(), true)
            ? $variant
            : 'public-navbar';
    }

    public function render(): View
    {
        if (! in_array($this->variant, $this->allowedVariants(), true)) {
            $this->variant = 'public-navbar';
        }

        return view('navigation-menu');
    }

    /** @return list<string> */
    private function allowedVariants(): array
    {
        return ['public-navbar', 'member-sidebar', 'member-profile'];
    }
}
