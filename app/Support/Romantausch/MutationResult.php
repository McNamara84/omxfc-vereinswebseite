<?php

namespace App\Support\Romantausch;

use Illuminate\Database\Eloquent\Model;

final class MutationResult
{
    public bool $matchingFailed = false;

    /** @param array<string, int|string> $details */
    public function __construct(public readonly Model $model, public readonly array $details = []) {}

    public function warning(): ?string
    {
        return $this->matchingFailed ? 'Gespeichert. Die automatische Tauschzuordnung ist aktuell nicht verfügbar. Bitte lege den Eintrag nicht erneut an.' : null;
    }
}
