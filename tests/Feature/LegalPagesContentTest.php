<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

test('impressum page shows register information', function (): void {
    $this->get('/impressum')->assertOk()->assertSee('Registernummer: 9677')->assertSee('Vereinsregister');
});

test('datenschutz page highlights legal basis and rights', function (): void {
    $this->get('/datenschutz')->assertOk()->assertSee('Rechtsgrundlage der Verarbeitung')->assertSee('Art. 6 Abs. 1 lit. b DSGVO', false)->assertSee('Beschwerderecht bei einer Aufsichtsbehörde')->assertSee('Datensicherheit');
});
