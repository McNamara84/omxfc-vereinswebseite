<?php

namespace App\View\Components;

use App\View\Components\Concerns\HasAccessibleLabel;
use Mary\View\Components\Input;

class AccessibleInput extends Input
{
    use HasAccessibleLabel;
}
