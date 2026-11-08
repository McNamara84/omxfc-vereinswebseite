<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('protokolle page shows context panels and archive heading', function (): void {
    $this->actingAs($this->actingMember());
    $response = $this->withoutVite()->get('/protokolle');
    $response->assertOk();
    $response->assertSeeText('Protokolle');
    $response->assertSeeText('Archiv nach Jahren');
    $response->assertSeeText('Was hier abgelegt ist');
    $response->assertSeeText('Download-Hinweis');
});
