<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use App\Support\Navigation\NavigationContext;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class NavigationContextTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    #[TestWith([false, false, true])]
    #[TestWith([false, true, true])]
    #[TestWith([true, false, false])]
    #[TestWith([true, true, true])]
    public function test_navigation_and_dashboard_agree_on_email_verification(bool $requiresVerification, bool $verified, bool $allowed): void
    {
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

        $this->assertSame($allowed, app(NavigationContext::class)->canAccessMembers($member));
        $response = $this->actingAs($member)->get(route('dashboard'));

        if ($allowed) {
            $response->assertOk()->assertSee('data-tour-key="dashboard"', false);
        } else {
            $response->assertRedirect(route('verification.notice'));
        }
    }
}
