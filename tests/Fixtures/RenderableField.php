<?php

namespace Tests\Fixtures;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class RenderableField extends Component
{
    public ?string $label = null;

    public function __construct(public View|Closure|string $output) {}

    public function render(): View|Closure|string
    {
        return $this->output;
    }
}
