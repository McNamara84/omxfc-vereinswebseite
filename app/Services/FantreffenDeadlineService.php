<?php

namespace App\Services;

use App\Models\Veranstaltung;
use Carbon\Carbon;

/**
 * Service für die Berechnung und Verwaltung der Fantreffen T-Shirt-Deadline.
 *
 * Diese Klasse kapselt die gesamte Deadline-Logik an einer zentralen Stelle,
 * um Duplizierung zwischen Controller und Livewire-Komponenten zu vermeiden.
 */
class FantreffenDeadlineService
{
    private function resolveDeadline(?Veranstaltung $veranstaltung = null): ?Carbon
    {
        $value = $veranstaltung?->merch_deadline
            ?: $veranstaltung?->tshirt_deadline
            ?: config('services.fantreffen.tshirt_deadline');

        if (! $value) {
            return null;
        }

        return Carbon::parse($value);
    }

    /**
     * Prüft, ob die Deadline abgelaufen ist.
     */
    public function isPassed(?Veranstaltung $veranstaltung = null): bool
    {
        $deadline = $this->resolveDeadline($veranstaltung);

        return $deadline ? Carbon::now()->isAfter($deadline) : false;
    }

    /**
     * Gibt die verbleibenden Tage bis zur Deadline zurück.
     * Gibt 0 zurück, wenn die Deadline abgelaufen ist.
     */
    public function getDaysRemaining(?Veranstaltung $veranstaltung = null): int
    {
        $deadline = $this->resolveDeadline($veranstaltung);

        if (! $deadline) {
            return 0;
        }

        $now = Carbon::now();

        return $now->isAfter($deadline) ? 0 : (int) $now->diffInDays($deadline, false);
    }

    /**
     * Gibt das formatierte Datum der Deadline zurück (z.B. "28. Februar 2026").
     */
    public function getFormattedDate(?Veranstaltung $veranstaltung = null): ?string
    {
        $deadline = $this->resolveDeadline($veranstaltung);

        return $deadline?->locale('de')->isoFormat('D. MMMM YYYY');
    }

    /**
     * Gibt das Carbon-Objekt der Deadline zurück.
     */
    public function getDeadline(?Veranstaltung $veranstaltung = null): ?Carbon
    {
        return $this->resolveDeadline($veranstaltung);
    }

    /**
     * Prüft, ob ein ARIA-Alert angezeigt werden soll (wenn <= 7 Tage verbleiben).
     */
    public function shouldShowAlert(?Veranstaltung $veranstaltung = null): bool
    {
        if ($this->resolveDeadline($veranstaltung) === null) {
            return false;
        }

        return ! $this->isPassed($veranstaltung) && $this->getDaysRemaining($veranstaltung) <= 7;
    }

    /**
     * Gibt alle Deadline-Daten als Array zurück (nützlich für Views).
     */
    public function toArray(?Veranstaltung $veranstaltung = null): array
    {
        $formattedDate = $this->getFormattedDate($veranstaltung);
        $deadlinePassed = $this->isPassed($veranstaltung);
        $daysRemaining = $this->getDaysRemaining($veranstaltung);

        return [
            'merchDeadlinePassed' => $deadlinePassed,
            'daysUntilMerchDeadline' => $daysRemaining,
            'merchDeadlineFormatted' => $formattedDate,
            'tshirtDeadlinePassed' => $deadlinePassed,
            'daysUntilDeadline' => $daysRemaining,
            'tshirtDeadlineFormatted' => $formattedDate,
        ];
    }
}
