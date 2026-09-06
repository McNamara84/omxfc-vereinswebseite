<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class NavigationMenu extends Component
{
    #[Locked]
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
        return view('navigation-menu');
    }

    /** @return list<string> */
    private function allowedVariants(): array
    {
        return ['public-navbar', 'member-sidebar', 'member-profile'];
    }
}
