<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Tests\TestCase;

class RpgCharEditorPdfTest extends TestCase
{
    use RefreshDatabase;

    private function validPdfPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'player_name' => 'Spieler Eins',
            'character_name' => 'Foo Bar',
            'race' => 'Barbar',
            'culture' => 'Landbewohner',
            'description' => 'Ein erfahrener Charakter aus Wudan.',
            'attributes' => [
                'st' => 2,
                'ge' => 1,
            ],
            'skills' => [
                ['name' => 'Athletik', 'value' => 1],
                ['name' => 'Nahkampf', 'value' => 1],
            ],
            'advantages' => ['Zäh'],
            'disadvantages' => ['Auffällig'],
            'equipment' => 'Messer, Seil, Feldflasche',
        ], $overrides);
    }

    private function createMember(Role|string $role = Role::Mitglied): User
    {
        $team = Team::membersTeam();

        if (! $team) {
            $team = Team::factory()->create(['name' => 'Mitglieder', 'personal_team' => false]);
        }

        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role instanceof Role ? $role->value : $role]);

        return $user->refresh();
    }

    private function addAgRollenspielMembership(User $user): User
    {
        $owner = User::factory()->create();

        $team = Team::factory()->create([
            'user_id' => $owner->id,
            'name' => 'AG Rollenspiel',
            'personal_team' => false,
        ]);
        $team->users()->attach($user, ['role' => Role::Mitglied->value]);

        return $user->refresh();
    }

    private function createManagementUserWithDifferentCurrentTeam(Role $role): User
    {
        $managementTeam = Team::membersTeam() ?? Team::factory()->create([
            'name' => 'Mitglieder',
            'personal_team' => false,
        ]);

        $user = User::factory()->create(['current_team_id' => $managementTeam->id]);
        $managementTeam->users()->attach($user, ['role' => $role->value]);

        $otherTeam = Team::factory()->create([
            'user_id' => $user->id,
            'name' => 'Nebenverein',
            'personal_team' => false,
        ]);
        $otherTeam->users()->attach($user, ['role' => Role::Mitglied->value]);

        $user->forceFill(['current_team_id' => $otherTeam->id])->save();

        return $user->refresh();
    }

    public function test_pdf_downloads_with_sanitized_filename_for_ag_rollenspiel_member(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::type('array'))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', [
            'character_name' => 'Foo/Bar',
            'portrait' => UploadedFile::fake()->image('avatar.jpg'),
        ]);

        $this->assertStringContainsString('foobar.pdf', $response->headers->get('content-disposition'));
    }

    public function test_rejects_non_image_portrait_for_ag_rollenspiel_member(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', [
            'portrait' => UploadedFile::fake()->create('bad.exe', 10, 'application/octet-stream'),
        ]);

        $response->assertSessionHasErrors('portrait');
    }

    public function test_pdf_endpoint_renders_real_pdf_for_ag_rollenspiel_member(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', $this->validPdfPayload());

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('foo-bar.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_pdf_generates_without_portrait_for_global_admin_with_different_current_team(): void
    {
        $admin = $this->createManagementUserWithDifferentCurrentTeam(Role::Admin);

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(fn ($data) => array_key_exists('portrait', $data) && is_null($data['portrait'])))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->actingAs($admin)->post('/rpg/char-editor/pdf', [
            'character_name' => 'Foo',
        ]);

        $response->assertOk();
    }

    public function test_pdf_endpoint_renders_real_pdf_with_uploaded_portrait(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', [
            ...$this->validPdfPayload(['character_name' => 'Mit Portrait']),
            'portrait' => UploadedFile::fake()->image('avatar.png', 120, 120),
        ]);

        $response->assertOk();
        $this->assertStringContainsString('mit-portrait.pdf', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_pdf_includes_base64_portrait_when_uploaded_for_ag_rollenspiel_member(): void
    {
        $member = $this->addAgRollenspielMembership($this->createMember());

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(fn ($data) => str_starts_with($data['portrait'] ?? '', 'data:image')))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $response = $this->actingAs($member)->post('/rpg/char-editor/pdf', [
            'character_name' => 'Foo',
            'portrait' => UploadedFile::fake()->image('avatar.png'),
        ]);

        $response->assertOk();
    }

    public function test_member_without_ag_rollenspiel_is_forbidden_from_pdf_endpoint(): void
    {
        $member = $this->createMember();

        $this->actingAs($member)
            ->post('/rpg/char-editor/pdf', ['character_name' => 'Foo'])
            ->assertForbidden();
    }

    public function test_dompdf_dependency_is_installed(): void
    {
        $this->assertTrue(class_exists(\Dompdf\Dompdf::class));
    }
}
