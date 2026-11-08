<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

pest()->extend(TestCase::class)->use(ProfileSettingsPageTestFixtures::class);

test('profile settings page uses modernized shell sections', function (): void {
    $user = $this->createMember();
    $this->actingAs($user);
    $response = $this->get('/user/profile');
    $response->assertOk();
    $response->assertSee('Profil & Einstellungen');
    $response->assertSee('Persönliche Daten');
    $response->assertSee('Serienspezifische Daten');
    $response->assertSee('Browser-Sitzungen');
    $response->assertSee('Öffentliches Profil ansehen');
    $response->assertSee(route('profile.view.self'), false);
});
