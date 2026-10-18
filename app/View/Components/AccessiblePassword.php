<?php

namespace App\View\Components;

use App\View\Components\Concerns\HasAccessibleLabel;
use Illuminate\Contracts\View\View;
use Mary\View\Components\Password;

class AccessiblePassword extends Password
{
    use HasAccessibleLabel {
        render as private renderAccessibleField;
    }

    public function render(): View|\Closure|string
    {
        $this->passwordIconTabindex = true;
        $template = $this->renderAccessibleField();

        return is_string($template) ? str_replace(
            'x-on:click="hidden = !hidden"',
            'x-on:click="hidden = !hidden" aria-label="Passwort anzeigen" x-bind:aria-label="hidden ? \'Passwort anzeigen\' : \'Passwort verbergen\'" x-bind:aria-pressed="(!hidden).toString()"',
            $template,
        ) : $template;
    }
}
