<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Mary\View\Components\Spotlight;

/** Spotlight adapter with named input, natural tab order and explicit error/focus handling. */
class NavigationSpotlight extends Spotlight
{
    public function render(): View|Closure|string
    {
        return view('components.navigation-spotlight');
    }
}
