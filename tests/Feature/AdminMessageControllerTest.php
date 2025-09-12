<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Models\AdminMessage;
use App\Models\Activity;
use Illuminate\Support\Str;

class AdminMessageControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Admin->value]);
        return $user;
    }

    private function actingMember(): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => \App\Enums\Role::Mitglied->value]);
        return $user;
    }

    public function test_index_displays_existing_messages(): void
    {
        $admin = $this->actingAdmin();
        AdminMessage::create(['user_id' => $admin->id, 'message' => 'Hallo Welt']);

        $response = $this->actingAs($admin)->get(route('admin.messages.index'));

        $response->assertOk();
        $response->assertSee('Hallo Welt');
    }

    public function test_store_creates_message_and_activity(): void
    {
        $admin = $this->actingAdmin();
        $response = $this->actingAs($admin)->post(route('admin.messages.store'), [
            'message' => 'Neue Nachricht',
        ]);

        $response->assertRedirect(route('admin.messages.index'));
        $this->assertDatabaseHas('admin_messages', [
            'user_id' => $admin->id,
            'message' => 'Neue Nachricht',
        ]);
        $message = AdminMessage::first();
        $this->assertDatabaseHas('activities', [
            'user_id' => $admin->id,
            'subject_type' => AdminMessage::class,
            'subject_id' => $message->id,
            'action' => 'admin_message',
        ]);
    }

    public function test_store_validates_message(): void
    {
        $admin = $this->actingAdmin();
        $response = $this->actingAs($admin)
            ->from(route('admin.messages.index'))
            ->post(route('admin.messages.store'), ['message' => '']);

        $response->assertRedirect(route('admin.messages.index'));
        $response->assertSessionHasErrors('message');
        $this->assertDatabaseCount('admin_messages', 0);
    }

    public function test_store_rejects_message_longer_than_140_characters(): void
    {
        $admin = $this->actingAdmin();
        $longMessage = Str::random(141);

        $response = $this->actingAs($admin)
            ->from(route('admin.messages.index'))
            ->post(route('admin.messages.store'), ['message' => $longMessage]);

        $response->assertRedirect(route('admin.messages.index'));
        $response->assertSessionHasErrors('message');
        $this->assertDatabaseCount('admin_messages', 0);
    }

    public function test_destroy_deletes_message_and_activity(): void
    {
        $admin = $this->actingAdmin();
        $message = AdminMessage::create(['user_id' => $admin->id, 'message' => 'Löschen']);
        $activity = Activity::create([
            'user_id' => $admin->id,
            'subject_type' => AdminMessage::class,
            'subject_id' => $message->id,
            'action' => 'admin_message',
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.messages.destroy', $message));

        $response->assertRedirect();
        $this->assertDatabaseMissing('admin_messages', ['id' => $message->id]);
        $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
    }

    public function test_non_admin_cannot_access_admin_message_routes(): void
    {
        $member = $this->actingMember();
        $message = AdminMessage::create(['user_id' => $member->id, 'message' => 'Secret']);

        $this->actingAs($member)
            ->get(route('admin.messages.index'))
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('admin.messages.store'), ['message' => 'Hallo'])
            ->assertForbidden();

        $this->actingAs($member)
            ->delete(route('admin.messages.destroy', $message))
            ->assertForbidden();
    }

    public function test_index_orders_messages_by_latest(): void
    {
        $admin = $this->actingAdmin();
        AdminMessage::create([
            'user_id' => $admin->id,
            'message' => 'Old',
            'created_at' => now()->subDay(),
        ]);
        AdminMessage::create(['user_id' => $admin->id, 'message' => 'New']);

        $response = $this->actingAs($admin)->get(route('admin.messages.index'));

        $response->assertSeeInOrder(['New', 'Old']);
    }
}

