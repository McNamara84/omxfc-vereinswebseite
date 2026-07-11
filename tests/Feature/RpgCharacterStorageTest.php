<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\RewardPurchase;
use App\Models\RpgCharacter;
use App\Models\Team;
use App\Models\User;
use App\Services\RpgCharacterSlotService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Tests\TestCase;

class RpgCharacterStorageTest extends TestCase
{
    use RefreshDatabase;

    private function validCharacterPayload(array $overrides = []): array
    {
        $payload = array_replace_recursive([
            'player_name' => 'Spieler Eins',
            'character_name' => 'Foo Bar',
            'gender' => 'maennlich',
            'race' => 'Barbar',
            'culture' => 'Landbewohner',
            'description' => 'Ein erfahrener Charakter aus Wudan.',
            'attributes' => [
                'st' => 2,
                'ge' => 1,
            ],
            'barbar_attribute_bonus' => 'st',
            'skills' => [
                ['name' => 'Nahkampf', 'value' => 1],
                ['name' => 'Ueberleben', 'value' => 1],
                ['name' => 'Athletik', 'value' => 1],
                ['name' => 'Intuition', 'value' => 1],
                ['name' => 'Beruf: Viehzuechter', 'value' => 2],
                ['name' => 'Kunde: Wetter', 'value' => 1],
            ],
            'advantages' => ['Zaeh'],
            'disadvantages' => ['Auffaellig'],
            'clothing' => 'kleidung-einfach',
            'equipment_items' => [
                ['id' => 'messer-dolch', 'quantity' => 1],
                ['id' => 'seil', 'quantity' => 1],
                ['id' => 'rucksack', 'quantity' => 1],
                ['id' => 'wasserschlauch', 'quantity' => 1],
                ['id' => 'wochenration', 'quantity' => 1],
                ['id' => 'bogen', 'quantity' => 1],
            ],
            'equipment' => 'Messer, Seil, Feldflasche',
        ], $overrides);

        foreach (['attributes', 'skills', 'advantages', 'disadvantages', 'equipment_items'] as $listKey) {
            if (array_key_exists($listKey, $overrides)) {
                $payload[$listKey] = $overrides[$listKey];
            }
        }

        return $payload;
    }

    private function createMember(Role|string $role = Role::Mitglied): User
    {
        $team = Team::membersTeam() ?? Team::factory()->create([
            'name' => 'Mitglieder',
            'personal_team' => false,
        ]);

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

    private function actingAgMember(int $points = 0): User
    {
        $user = $this->addAgRollenspielMembership($this->createMember());

        if ($points > 0) {
            $user->incrementTeamPoints($points);
        }

        $this->actingAs($user->refresh());

        return $user->refresh();
    }

    public function test_ag_rollenspiel_member_can_store_first_character_for_free(): void
    {
        Storage::fake('private');
        $member = $this->actingAgMember();

        $response = $this->post(route('rpg.characters.store'), $this->validCharacterPayload([
            'portrait' => UploadedFile::fake()->image('avatar.png', 10, 10),
        ]));

        $response
            ->assertRedirect(route('rpg.characters.index'))
            ->assertSessionHas('success');

        $character = RpgCharacter::query()->firstOrFail();

        $this->assertSame($member->id, $character->user_id);
        $this->assertSame('Foo Bar', $character->character_name);
        $this->assertSame('Barbar', $character->payload['character']['race']);
        $this->assertNotNull($character->portrait_path);
        Storage::disk('private')->assertExists($character->portrait_path);
        $this->assertSame(0, RewardPurchase::query()->count());
    }

    public function test_non_editor_member_cannot_use_character_storage_routes(): void
    {
        $owner = $this->actingAgMember();
        $character = RpgCharacter::factory()->create(['user_id' => $owner->id]);
        $member = $this->createMember();

        $this->actingAs($member)
            ->get(route('rpg.characters.index'))
            ->assertForbidden();

        $this->actingAs($member)
            ->post(route('rpg.characters.store'), $this->validCharacterPayload())
            ->assertForbidden();

        Pdf::shouldReceive('view')->never();

        $this->actingAs($member)
            ->get(route('rpg.characters.pdf', $character))
            ->assertForbidden();

        $this->actingAs($member)
            ->delete(route('rpg.characters.destroy', $character))
            ->assertForbidden();
    }

    public function test_character_list_only_shows_current_users_characters(): void
    {
        $member = $this->actingAgMember();
        $otherMember = $this->addAgRollenspielMembership($this->createMember());

        RpgCharacter::factory()->create([
            'user_id' => $member->id,
            'character_name' => 'Eigener Held',
        ]);
        RpgCharacter::factory()->create([
            'user_id' => $otherMember->id,
            'character_name' => 'Fremder Held',
        ]);

        $this->get(route('rpg.characters.index'))
            ->assertOk()
            ->assertSee('Eigener Held')
            ->assertDontSee('Fremder Held')
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false);
    }

    public function test_empty_character_name_fallback_is_persisted_to_payload(): void
    {
        $this->actingAgMember();

        $this->post(route('rpg.characters.store'), $this->validCharacterPayload([
            'character_name' => '   ',
        ]))->assertRedirect(route('rpg.characters.index'));

        $character = RpgCharacter::query()->firstOrFail();

        $this->assertSame('Charakter', $character->character_name);
        $this->assertSame('Charakter', $character->payload['character']['character_name']);
    }

    public function test_zero_character_name_is_persisted_without_fallback(): void
    {
        $this->actingAgMember();

        $this->post(route('rpg.characters.store'), $this->validCharacterPayload([
            'character_name' => '0',
        ]))->assertRedirect(route('rpg.characters.index'));

        $character = RpgCharacter::query()->firstOrFail();

        $this->assertSame('0', $character->character_name);
        $this->assertSame('0', $character->payload['character']['character_name']);
    }

    public function test_character_name_above_database_column_length_is_rejected(): void
    {
        $this->actingAgMember();

        $this->post(route('rpg.characters.store'), $this->validCharacterPayload([
            'character_name' => str_repeat('A', 256),
        ]))->assertSessionHasErrors('character_name');

        $this->assertSame(0, RpgCharacter::query()->count());
    }

    public function test_second_character_without_free_slot_is_rejected(): void
    {
        $this->actingAgMember();

        $this->post(route('rpg.characters.store'), $this->validCharacterPayload([
            'character_name' => 'Erster Held',
        ]))->assertRedirect(route('rpg.characters.index'));

        config(['rewards.rpg_character_slot_cost_baxx' => 7]);

        $this->post(route('rpg.characters.store'), $this->validCharacterPayload([
            'character_name' => 'Zweiter Held',
        ]))->assertSessionHasErrors('slot');

        $slotError = session('errors')->first('slot');

        $this->assertStringContainsString('7 Baxx', $slotError);
        $this->assertStringNotContainsString('5 Baxx', $slotError);
        $this->assertSame(1, RpgCharacter::query()->count());
    }

    public function test_rejected_store_without_free_slot_does_not_write_portrait(): void
    {
        $this->actingAgMember();

        $this->post(route('rpg.characters.store'), $this->validCharacterPayload([
            'character_name' => 'Erster Held',
        ]))->assertRedirect(route('rpg.characters.index'));

        $image = UploadedFile::fake()->image('avatar.png', 1, 1);
        $dataUrl = 'data:image/png;base64,'.base64_encode($image->get());

        Storage::shouldReceive('disk')->never();

        $this->post(route('rpg.characters.store'), $this->validCharacterPayload([
            'character_name' => 'Zweiter Held',
            'portrait_data_url' => $dataUrl,
        ]))->assertSessionHasErrors('slot');

        $this->assertSame(1, RpgCharacter::query()->count());
    }

    public function test_slot_purchase_costs_five_baxx_and_is_repeatable(): void
    {
        $member = $this->actingAgMember(15);

        $this->post(route('rpg.characters.slots.purchase'))
            ->assertRedirect(route('rpg.characters.index'))
            ->assertSessionHas('success');

        $this->post(route('rpg.characters.slots.purchase'))
            ->assertRedirect(route('rpg.characters.index'))
            ->assertSessionHas('success');

        $this->assertSame(2, RewardPurchase::query()->count());
        $this->assertSame(10, (int) RewardPurchase::query()->sum('cost_baxx'));
        $this->assertSame(3, app(RpgCharacterSlotService::class)->totalSlots($member));
        $this->assertSame(5, $member->fresh()->getAvailableBaxx());
    }

    public function test_slot_purchase_with_insufficient_baxx_is_rejected(): void
    {
        $this->actingAgMember(4);

        $this->post(route('rpg.characters.slots.purchase'))
            ->assertRedirect(route('rpg.characters.index'))
            ->assertSessionHasErrors('slot');

        $this->assertSame(0, RewardPurchase::query()->count());
    }

    public function test_store_can_confirm_slot_purchase_when_storage_is_full(): void
    {
        $member = $this->actingAgMember(5);

        $this->post(route('rpg.characters.store'), $this->validCharacterPayload([
            'character_name' => 'Erster Held',
        ]))->assertRedirect(route('rpg.characters.index'));

        $this->post(route('rpg.characters.store'), $this->validCharacterPayload([
            'character_name' => 'Zweiter Held',
            'purchase_slot_if_needed' => '1',
        ]))->assertRedirect(route('rpg.characters.index'));

        $this->assertSame(2, RpgCharacter::query()->count());
        $this->assertSame(1, RewardPurchase::query()->count());
        $this->assertSame(0, $member->fresh()->getAvailableBaxx());
    }

    public function test_failed_store_redirect_keeps_editor_input_for_recovery(): void
    {
        $this->actingAgMember();

        $response = $this
            ->from(route('rpg.char-editor'))
            ->post(route('rpg.characters.store'), $this->validCharacterPayload([
                'character_name' => 'Kept Store Character',
                'portrait_data_url' => 'data:image/png;base64,'.base64_encode('not an image'),
            ]));

        $response
            ->assertRedirect(route('rpg.char-editor'))
            ->assertSessionHasErrors('portrait_data_url')
            ->assertSessionHasInput('character_name', 'Kept Store Character')
            ->assertSessionHasInput('race', 'Barbar')
            ->assertSessionHasInput('clothing', 'kleidung-einfach');

        $this->assertSame(0, RpgCharacter::query()->count());
    }

    public function test_failed_portrait_validation_does_not_expose_old_portrait_payload_to_editor(): void
    {
        $this->actingAgMember();

        foreach (['portrait_data_url', 'portrait'] as $errorField) {
            $payloadMarker = 'OVERSIZEDPORTRAITPAYLOAD'.strtoupper($errorField);
            $overrides = [
                'character_name' => 'Kept Portrait Character',
                'portrait_data_url' => 'data:image/png;base64,'.$payloadMarker,
            ];

            if ($errorField === 'portrait') {
                $overrides['portrait'] = UploadedFile::fake()->create('avatar.txt', 1, 'text/plain');
            }

            $response = $this
                ->followingRedirects()
                ->from(route('rpg.char-editor'))
                ->post(route('rpg.characters.store'), $this->validCharacterPayload($overrides));

            $response
                ->assertOk()
                ->assertSee('Kept Portrait Character')
                ->assertDontSee($payloadMarker);
        }
    }

    public function test_failed_portrait_storage_rejects_character_without_persisting_path(): void
    {
        $this->actingAgMember();
        $image = UploadedFile::fake()->image('avatar.png', 1, 1);
        $dataUrl = 'data:image/png;base64,'.base64_encode($image->get());

        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')
            ->once()
            ->with(
                \Mockery::on(static fn (string $path): bool => str_starts_with($path, 'rpg-characters/') && str_ends_with($path, '.png')),
                \Mockery::type('string'),
            )
            ->andReturn(false);

        Storage::shouldReceive('disk')
            ->with('private')
            ->once()
            ->andReturn($disk);

        $this->post(route('rpg.characters.store'), $this->validCharacterPayload([
            'portrait_data_url' => $dataUrl,
        ]))->assertSessionHasErrors('portrait_data_url');

        $this->assertSame(0, RpgCharacter::query()->count());
    }

    public function test_failed_uploaded_portrait_storage_attaches_error_to_upload_field(): void
    {
        $this->actingAgMember();

        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')
            ->once()
            ->with(
                \Mockery::on(static fn (string $path): bool => str_starts_with($path, 'rpg-characters/') && str_ends_with($path, '.png')),
                \Mockery::type('string'),
            )
            ->andReturn(false);

        Storage::shouldReceive('disk')
            ->with('private')
            ->once()
            ->andReturn($disk);

        $this->post(route('rpg.characters.store'), $this->validCharacterPayload([
            'portrait' => UploadedFile::fake()->image('avatar.png', 1, 1),
        ]))->assertSessionHasErrors('portrait');

        $this->assertFalse(session('errors')->has('portrait_data_url'));
        $this->assertSame(0, RpgCharacter::query()->count());
    }

    public function test_deleting_character_frees_slot_and_removes_portrait_file(): void
    {
        Storage::fake('private');
        $this->actingAgMember();

        $this->post(route('rpg.characters.store'), $this->validCharacterPayload([
            'portrait' => UploadedFile::fake()->image('avatar.png', 10, 10),
        ]))->assertRedirect(route('rpg.characters.index'));

        $character = RpgCharacter::query()->firstOrFail();
        $path = $character->portrait_path;

        Storage::disk('private')->assertExists($path);

        $this->delete(route('rpg.characters.destroy', $character))
            ->assertRedirect(route('rpg.characters.index'))
            ->assertSessionHas('success');

        $this->assertSame(0, RpgCharacter::query()->count());
        Storage::disk('private')->assertMissing($path);
    }

    public function test_saved_character_can_be_rendered_as_pdf_with_stored_portrait(): void
    {
        Storage::fake('private');
        $this->actingAgMember();

        $this->post(route('rpg.characters.store'), $this->validCharacterPayload([
            'character_name' => 'PDF Held',
            'portrait' => UploadedFile::fake()->image('avatar.png', 10, 10),
        ]))->assertRedirect(route('rpg.characters.index'));

        $character = RpgCharacter::query()->firstOrFail();

        Pdf::shouldReceive('view')
            ->once()
            ->with('rpg.char-sheet', \Mockery::on(fn (array $data): bool => $data['character']['character_name'] === 'PDF Held'
                && str_starts_with($data['portrait'] ?? '', 'data:image/png;base64,')))
            ->andReturn(new class extends PdfBuilder
            {
                public function toResponse($request): Response
                {
                    return response('PDF', 200, $this->responseHeaders);
                }
            });

        $this->get(route('rpg.characters.pdf', $character))
            ->assertOk();
    }

    public function test_saved_character_pdf_is_owner_scoped(): void
    {
        $owner = $this->actingAgMember();
        $otherMember = $this->addAgRollenspielMembership($this->createMember());
        $character = RpgCharacter::factory()->create(['user_id' => $owner->id]);

        Pdf::shouldReceive('view')->never();

        $this->actingAs($otherMember)
            ->get(route('rpg.characters.pdf', $character))
            ->assertForbidden();
    }

    public function test_saved_character_delete_is_owner_scoped(): void
    {
        $owner = $this->actingAgMember();
        $otherMember = $this->addAgRollenspielMembership($this->createMember());
        $character = RpgCharacter::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($otherMember)
            ->delete(route('rpg.characters.destroy', $character))
            ->assertForbidden();

        $this->assertModelExists($character);
    }
}
