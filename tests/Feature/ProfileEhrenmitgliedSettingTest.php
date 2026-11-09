<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(ProfileEhrenmitgliedSettingTestFixtures::class);

test('ehrenmitglied sees review notification setting', function (): void {
    $user = $this->createEhrenmitglied();
    $this->actingAs($user);
    $response = $this->get('/user/profile');
    $response->assertOk();
    $user = $user->fresh();
    expect($user->hasRole(Role::Ehrenmitglied))->toBeTrue();
    $this->assertDatabaseHas('team_user', ['user_id' => $user->id, 'role' => Role::Ehrenmitglied->value]);
    $response->assertSee('E-Mail bei neuer Rezension erhalten');
});

test('regular member does not see review notification setting', function (): void {
    $user = $this->createMitglied();
    $this->actingAs($user);
    $response = $this->get('/user/profile');
    $response->assertOk();
    $response->assertDontSee('E-Mail bei neuer Rezension erhalten');
});
