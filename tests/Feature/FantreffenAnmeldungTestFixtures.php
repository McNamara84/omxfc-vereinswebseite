<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\FantreffenAnmeldung;
use App\Models\Veranstaltung;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\CreatesFantreffenFormToken;

trait FantreffenAnmeldungTestFixtures
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

    protected function confirmationUrl(FantreffenAnmeldung $anmeldung): string
    {
        return URL::signedRoute('veranstaltungen.bestaetigung', ['veranstaltung' => $this->veranstaltung, 'id' => $anmeldung->id]);
    }
}
