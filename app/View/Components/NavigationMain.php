<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Mary\View\Components\Main;

/**
 * maryUI main layout with an axe-compatible drawer overlay.
 *
 * Upstream renders aria-label on a label element. Axe 4.13 rejects that ARIA
 * attribute for this role-less element, while the overlay needs no accessible
 * name because the drawer already closes through its toggle and Escape.
 */
class NavigationMain extends Main
{
    public function render(): View|Closure|string
    {
        $template = parent::render();

        if (!is_string($template)) {
            return $template;
        }

        return str_replace(' aria-label="close sidebar"', '', $template);
    }
}
