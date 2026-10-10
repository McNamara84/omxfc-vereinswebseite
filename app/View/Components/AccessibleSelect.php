<?php

namespace App\View\Components;

use App\View\Components\Concerns\HasAccessibleLabel;
use Mary\View\Components\Select;

class AccessibleSelect extends Select
{
    use HasAccessibleLabel;
}
