<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\AdminMessage;
use Illuminate\Support\Str;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(AdminMessageControllerTestFixtures::class);

test('index displays existing messages', function (): void {
    $admin = $this->actingAdmin();
    AdminMessage::create(['user_id' => $admin->id, 'message' => 'Hallo Welt']);
    $response = $this->actingAs($admin)->get(route('admin.messages.index'));
    $response->assertOk();
    $response->assertSee('Hallo Welt');
});

test('index links submit button to message form', function (): void {
    $admin = $this->actingAdmin();
    $response = $this->actingAs($admin)->get(route('admin.messages.index'));
    $response->assertOk();
    $response->assertSee('id="admin-message-form"', false);
    $response->assertSee('form="admin-message-form"', false);
});

test('store creates message and activity', function (): void {
    $admin = $this->actingAdmin();
    $response = $this->actingAs($admin)->post(route('admin.messages.store'), ['message' => 'Neue Nachricht']);
    $response->assertRedirect(route('admin.messages.index'));
    $this->assertDatabaseHas('admin_messages', ['user_id' => $admin->id, 'message' => 'Neue Nachricht']);
    $message = AdminMessage::first();
    $this->assertDatabaseHas('activities', ['user_id' => $admin->id, 'subject_type' => AdminMessage::class, 'subject_id' => $message->id, 'action' => 'admin_message']);
});

test('store validates message', function (): void {
    $admin = $this->actingAdmin();
    $response = $this->actingAs($admin)->from(route('admin.messages.index'))->post(route('admin.messages.store'), ['message' => '']);
    $response->assertRedirect(route('admin.messages.index'));
    $response->assertSessionHasErrors('message');
    $this->assertDatabaseCount('admin_messages', 0);
});

test('store rejects message longer than 140 characters', function (): void {
    $admin = $this->actingAdmin();
    $longMessage = Str::random(141);
    $response = $this->actingAs($admin)->from(route('admin.messages.index'))->post(route('admin.messages.store'), ['message' => $longMessage]);
    $response->assertRedirect(route('admin.messages.index'));
    $response->assertSessionHasErrors('message');
    $this->assertDatabaseCount('admin_messages', 0);
});

test('destroy deletes message and activity', function (): void {
    $admin = $this->actingAdmin();
    $message = AdminMessage::create(['user_id' => $admin->id, 'message' => 'Löschen']);
    $activity = Activity::create(['user_id' => $admin->id, 'subject_type' => AdminMessage::class, 'subject_id' => $message->id, 'action' => 'admin_message']);
    $response = $this->actingAs($admin)->delete(route('admin.messages.destroy', $message));
    $response->assertRedirect();
    $this->assertDatabaseMissing('admin_messages', ['id' => $message->id]);
    $this->assertDatabaseMissing('activities', ['id' => $activity->id]);
});

test('non admin cannot access admin message routes', function (): void {
    $member = $this->actingMember();
    $message = AdminMessage::create(['user_id' => $member->id, 'message' => 'Secret']);
    $this->actingAs($member)->get(route('admin.messages.index'))->assertForbidden();
    $this->actingAs($member)->post(route('admin.messages.store'), ['message' => 'Hallo'])->assertForbidden();
    $this->actingAs($member)->delete(route('admin.messages.destroy', $message))->assertForbidden();
});

test('index orders messages by latest', function (): void {
    $admin = $this->actingAdmin();
    $oldMessage = AdminMessage::create(['user_id' => $admin->id, 'message' => 'Old']);
    $oldMessage->forceFill(['created_at' => now()->subDay(), 'updated_at' => now()->subDay()])->save();
    AdminMessage::create(['user_id' => $admin->id, 'message' => 'New']);
    $response = $this->actingAs($admin)->get(route('admin.messages.index'));
    $response->assertViewHas('messages', fn ($messages): bool => $messages->pluck('message')->values()->all() === ['New', 'Old']);
});
