<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Veranstaltung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFantreffenFormToken;

trait FantreffenTshirtDeadlineTestFixtures
{
    use CreatesFantreffenFormToken;
    use RefreshDatabase;

    protected Veranstaltung $veranstaltung;

    protected function showUrl(): string
    {
        return route('veranstaltungen.show', ['veranstaltung' => $this->veranstaltung]);
    }

    protected function storeUrl(): string
    {
        return route('veranstaltungen.anmeldung.store', ['veranstaltung' => $this->veranstaltung]);
    }
}
