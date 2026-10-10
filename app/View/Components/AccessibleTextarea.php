<?php

namespace App\View\Components;

use App\View\Components\Concerns\HasAccessibleLabel;
use Mary\View\Components\Textarea;

class AccessibleTextarea extends Textarea
{
    use HasAccessibleLabel;
}
