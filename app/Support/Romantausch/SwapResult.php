<?php

namespace App\Support\Romantausch;

use App\Models\BookSwap;

final readonly class SwapResult
{
    /** @param array<string, int> $awards */
    public function __construct(public BookSwap $swap, public bool $completed, public bool $alreadyCompleted = false, public array $awards = []) {}

    public function message(): string
    {
        if ($this->alreadyCompleted) {
            return 'Dieser Tausch wurde bereits abgeschlossen.';
        }
        if (! $this->completed) {
            return 'Bestätigung gespeichert.';
        }
        $offer = (int) ($this->awards['offer_user_points'] ?? 0);
        $request = (int) ($this->awards['request_user_points'] ?? 0);

        return $offer === $request && $offer > 0
            ? "Tausch abgeschlossen! Beide erhalten {$offer} Baxx."
            : ($offer + $request > 0 ? "Tausch abgeschlossen! Baxx: Angebot {$offer}, Gesuch {$request}." : 'Tausch abgeschlossen. Es wurden keine Baxx vergeben.');
    }
}
