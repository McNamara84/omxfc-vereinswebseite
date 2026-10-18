<?php

namespace App\View\Components\Concerns;

use Illuminate\Contracts\View\View;

trait HasAccessibleLabel
{
    public function render(): View|\Closure|string
    {
        $template = parent::render();
        if (! is_string($template)) {
            return $template;
        }
        // Keep explicit field IDs stable for error-summary links. Mary otherwise
        // appends its generated component hash and model name to the supplied ID.
        $template = str_replace('id="{{ $uuid }}"', 'id="{{ $id ?? $uuid }}"', $template);
        $template = str_replace("'id' => \$uuid", "'id' => \$id ?? \$uuid", $template);
        $template = preg_replace('/\$attributes(?=\s*->)/', '$attributes->except([\'aria-invalid\', \'aria-describedby\'])', $template) ?? $template;
        $aria = <<<'BLADE'
             aria-invalid="{{ $errorFieldName() && $errors->has($errorFieldName()) && !$omitError ? 'true' : 'false' }}"
             aria-describedby="{{ trim(($attributes->get('aria-describedby') ?? '') . ($errorFieldName() && $errors->has($errorFieldName()) && !$omitError ? ' ' . ($id ?? $uuid) . '-error' : '') . ($hint ? ' ' . ($id ?? $uuid) . '-hint' : '')) }}"
            BLADE;
        $template = preg_replace('/<(input|textarea|select)(\s)(?!type="hidden")/', '<$1'.$aria.'$2', $template) ?? $template;
        foreach (['ERROR' => 'error', 'HINT' => 'hint'] as $marker => $suffix) {
            $template = preg_replace(
                '/(\{\{-- '.$marker.' --\}\})([\s\S]*?)(?=\{\{-- (?:ERROR|HINT) --\}\}|<\/fieldset>)/',
                '$1<div id="{{ ($id ?? $uuid) . \'-'.$suffix.'\' }}">$2</div>',
                $template,
            ) ?? $template;
        }

        return $template;
    }

    /** @param array<string, mixed> $attributes */
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
