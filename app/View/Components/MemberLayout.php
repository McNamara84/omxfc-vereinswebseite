<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class MemberLayout extends Component
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
    ) {}

    public function render(): View
    {
        return view('layouts.member');
    }
}
