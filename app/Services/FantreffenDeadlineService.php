<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Service für die Berechnung und Verwaltung der Fantreffen T-Shirt-Deadline.
 * 
 * Diese Klasse kapselt die gesamte Deadline-Logik an einer zentralen Stelle,
 * um Duplizierung zwischen Controller und Livewire-Komponenten zu vermeiden.
 */
class FantreffenDeadlineService
{
    private Carbon $deadline;
    private bool $isPassed;
    private int $daysRemaining;
    private string $formattedDate;

    public function __construct()
    {
        $this->deadline = Carbon::parse(config('services.fantreffen.tshirt_deadline'));
        $this->isPassed = Carbon::now()->isAfter($this->deadline);
        $this->daysRemaining = $this->isPassed ? 0 : (int) Carbon::now()->diffInDays($this->deadline, false);
        $this->formattedDate = $this->deadline->locale('de')->isoFormat('D. MMMM YYYY');
    }

    /**
     * Prüft, ob die Deadline abgelaufen ist.
     */
    public function isPassed(): bool
    {
        return $this->isPassed;
    }

    /**
     * Gibt die verbleibenden Tage bis zur Deadline zurück.
     * Gibt 0 zurück, wenn die Deadline abgelaufen ist.
     */
    public function getDaysRemaining(): int
    {
        return $this->daysRemaining;
    }

    /**
     * Gibt das formatierte Datum der Deadline zurück (z.B. "28. Februar 2026").
     */
    public function getFormattedDate(): string
    {
        return $this->formattedDate;
    }

    /**
     * Gibt das Carbon-Objekt der Deadline zurück.
     */
    public function getDeadline(): Carbon
    {
        return $this->deadline;
    }

    /**
     * Prüft, ob ein ARIA-Alert angezeigt werden soll (wenn <= 7 Tage verbleiben).
     */
    public function shouldShowAlert(): bool
    {
        return !$this->isPassed && $this->daysRemaining <= 7;
    }

    /**
     * Gibt alle Deadline-Daten als Array zurück (nützlich für Views).
     */
    public function toArray(): array
    {
        return [
            'tshirtDeadlinePassed' => $this->isPassed,
            'daysUntilDeadline' => $this->daysRemaining,
            'tshirtDeadlineFormatted' => $this->formattedDate,
        ];
    }
}
