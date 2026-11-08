<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Support\Navigation\NavigationContext;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('navigation and dashboard agree on email verification', function (bool $requiresVerification, bool $verified, bool $allowed): void {
    $member = $this->createUserWithRole(Role::Mitglied);
    $member->forceFill(['email_verified_at' => $verified ? now() : null])->save();
    if ($requiresVerification) {
        $model = new class extends User implements MustVerifyEmail
        {
            protected $table = 'users';

            public function getForeignKey(): string
            {
                return 'user_id';
            }
        };
        $member = $model->newFromBuilder($member->getAttributes());
    }
    expect(app(NavigationContext::class)->canAccessMembers($member))->toBe($allowed);
    $response = $this->actingAs($member)->get(route('dashboard'));
    if ($allowed) {
        $response->assertOk()->assertSee('data-tour-key="dashboard"', false);
    } else {
        $response->assertRedirect(route('verification.notice'));
    }
})->with([[false, false, true], [false, true, true], [true, false, false], [true, true, true]]);
