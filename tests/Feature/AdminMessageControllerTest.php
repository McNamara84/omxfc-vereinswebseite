<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Team;
use App\Models\User;
use App\Models\AdminMessage;
use App\Models\Activity;

class AdminMessageControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => 'Admin']);
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
}

