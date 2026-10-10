<?php

namespace App\View\Components;

use App\View\Components\Concerns\HasAccessibleLabel;
use Mary\View\Components\File;

class AccessibleFile extends File
{
    use HasAccessibleLabel;
}
