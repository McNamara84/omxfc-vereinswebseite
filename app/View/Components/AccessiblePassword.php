<?php

namespace App\View\Components;

use App\View\Components\Concerns\HasAccessibleLabel;
use Mary\View\Components\Password;

class AccessiblePassword extends Password
{
    use HasAccessibleLabel;
}
