<?php

namespace App\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Mary\View\Components\Popover;

class AccessiblePopover extends Popover
{
    public function render(): View
    {
        $this->uuid = 'mary-popover-'.Str::ulid();

        return view('components.ui.accessible-popover');
    }
}
