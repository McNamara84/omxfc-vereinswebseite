<?php

namespace App\View\Components\Concerns;

trait HasAccessibleLabel
{
    public function withAttributes(array $attributes): static
    {
        // Mary's fieldset legend names the group, but not its input. Keep an
        // explicitly supplied accessible name; otherwise use the visible label.
        if ($this->label && ! array_key_exists('aria-label', $attributes) && ! array_key_exists('aria-labelledby', $attributes)) {
            $attributes['aria-label'] = $this->label;
        }

        return parent::withAttributes($attributes);
    }
}
