<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Mail;
use App\Mail\Newsletter;

class NewsletterControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingMember(string $role = 'Mitglied'): User
    {
        $team = Team::where('name', 'Mitglieder')->first();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role]);
        return $user;
    }

    public function test_admin_can_view_newsletter_form(): void
    {
        $user = $this->actingMember('Admin');

        $this->actingAs($user)
            ->get(route('newsletter.create'))
            ->assertOk();
    }

    public function test_vorstand_cannot_view_newsletter_form(): void
    {
        $user = $this->actingMember('Vorstand');

        $this->actingAs($user)
            ->get(route('newsletter.create'))
            ->assertForbidden();
    }

    public function test_admin_can_send_newsletter_to_selected_roles(): void
    {
        Mail::fake();

        $admin = $this->actingMember('Admin');
        $member = $this->actingMember('Mitglied');
        $board = $this->actingMember('Vorstand');

        $data = [
            'roles' => ['Mitglied', 'Vorstand'],
            'subject' => 'Info',
            'topics' => [
                ['title' => 'A', 'content' => 'B'],
            ],
        ];

        $response = $this->actingAs($admin)->post(route('newsletter.send'), $data);

        $response->assertRedirect(route('newsletter.create'));

        Mail::assertQueued(Newsletter::class, function (Newsletter $mail) use ($member) {
            return $mail->hasTo($member->email) && $mail->subjectLine === 'Info';
        });
        Mail::assertQueued(Newsletter::class, function (Newsletter $mail) use ($board) {
            return $mail->hasTo($board->email);
        });
        Mail::assertQueuedCount(2);
    }

    public function test_send_validation_errors(): void
    {
        $admin = $this->actingMember('Admin');

        $response = $this->actingAs($admin)
            ->from(route('newsletter.create'))
            ->post(route('newsletter.send'), []);

        $response->assertRedirect(route('newsletter.create'));
        $response->assertSessionHasErrors(['roles', 'subject', 'topics']);
    }

    public function test_non_admin_cannot_send_newsletter(): void
    {
        $member = $this->actingMember();

        $this->actingAs($member)
            ->post(route('newsletter.send'), [])
            ->assertForbidden();
    }

    public function test_test_mode_sends_only_to_admins(): void
    {
        Mail::fake();

        $admin = $this->actingMember('Admin');
        $otherAdmin = $this->actingMember('Admin');
        $member = $this->actingMember();

        $data = [
            'roles' => ['Mitglied'],
            'subject' => 'Test',
            'topics' => [
                ['title' => 'T', 'content' => 'C'],
            ],
            'test' => true,
        ];

        $this->actingAs($admin)->post(route('newsletter.send'), $data)
            ->assertRedirect(route('newsletter.create'));

        Mail::assertQueued(Newsletter::class, function (Newsletter $mail) use ($admin) {
            return $mail->hasTo($admin->email);
        });
        Mail::assertQueued(Newsletter::class, function (Newsletter $mail) use ($otherAdmin) {
            return $mail->hasTo($otherAdmin->email);
        });
        Mail::assertNotQueued(Newsletter::class, function (Newsletter $mail) use ($member) {
            return $mail->hasTo($member->email);
        });
    }
}
