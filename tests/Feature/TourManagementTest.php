<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TourAssignmentSource;
use App\Enums\TourAssignmentStatus;
use App\Models\TourAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

class TourManagementTest extends TestCase
{
    use CreatesUserWithRole;
    use RefreshDatabase;

    public function test_profile_page_shows_tour_selfservice_panel(): void
    {
        $member = $this->createUserWithRole(Role::Mitglied);

        TourAssignment::query()->create([
            'user_id' => $member->id,
            'tour_key' => 'hauptmenue',
            'tour_version' => 1,
            'status' => TourAssignmentStatus::Completed,
            'assigned_via' => TourAssignmentSource::System,
            'assigned_at' => now()->subDay(),
            'completed_at' => now()->subHours(12),
            'metadata' => [],
        ]);

        $this->actingAs($member)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSeeText('Touren & Hilfestart')
            ->assertSeeText('Hauptmenü entdecken')
            ->assertSeeText('Profil pflegen')
            ->assertSee(route('touren.restart', 'hauptmenue'));
    }

    public function test_profile_page_renders_without_tour_assignments_table(): void
    {
        $member = $this->createUserWithRole(Role::Mitglied);

        Schema::drop('tour_assignments');

        $this->actingAs($member)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertDontSeeText('Touren & Hilfestart');
    }

    public function test_vorstand_can_view_tour_admin_page(): void
    {
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $member = $this->createUserWithRole(Role::Mitglied);

        $this->actingAs($vorstand)
            ->get(route('admin.touren.index'))
            ->assertOk()
            ->assertSeeText('Touren verwalten')
            ->assertSeeText($member->name)
            ->assertSeeText('Hauptmenü entdecken')
            ->assertSeeText('Profil pflegen');
    }

    public function test_vorstand_can_reassign_tour_for_member(): void
    {
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $member = $this->createUserWithRole(Role::Mitglied);

        $assignment = TourAssignment::query()->create([
            'user_id' => $member->id,
            'tour_key' => 'hauptmenue',
            'tour_version' => 1,
            'status' => TourAssignmentStatus::Completed,
            'assigned_via' => TourAssignmentSource::System,
            'assigned_at' => now()->subDay(),
            'started_at' => now()->subDay(),
            'completed_at' => now()->subHours(12),
            'current_step_key' => 'profile-settings',
            'metadata' => [],
        ]);

        $this->actingAs($vorstand)
            ->post(route('admin.touren.assign'), [
                'user_id' => $member->id,
                'tour_key' => 'hauptmenue',
            ])
            ->assertRedirect(route('admin.touren.index'));

        $this->assertDatabaseHas('tour_assignments', [
            'id' => $assignment->id,
            'status' => TourAssignmentStatus::Pending->value,
            'assigned_via' => TourAssignmentSource::Manual->value,
            'assigned_by_user_id' => $vorstand->id,
            'current_step_key' => null,
        ]);
    }

    public function test_vorstand_can_assign_profile_tour_for_member(): void
    {
        $vorstand = $this->createUserWithRole(Role::Vorstand);
        $member = $this->createUserWithRole(Role::Mitglied);

        $this->actingAs($vorstand)
            ->post(route('admin.touren.assign'), [
                'user_id' => $member->id,
                'tour_key' => 'profilpflege',
            ])
            ->assertRedirect(route('admin.touren.index'));

        $this->assertDatabaseHas('tour_assignments', [
            'user_id' => $member->id,
            'tour_key' => 'profilpflege',
            'tour_version' => 1,
            'status' => TourAssignmentStatus::Pending->value,
            'assigned_via' => TourAssignmentSource::Manual->value,
            'assigned_by_user_id' => $vorstand->id,
        ]);
    }

    public function test_regular_member_cannot_access_tour_admin_page(): void
    {
        $member = $this->createUserWithRole(Role::Mitglied);

        $this->actingAs($member)
            ->get(route('admin.touren.index'))
            ->assertForbidden();
    }
}