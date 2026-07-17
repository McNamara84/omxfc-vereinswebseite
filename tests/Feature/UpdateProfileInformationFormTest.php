<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\Profile\UpdateProfileInformationForm;
use App\Mail\ProfileContactUpdated;
use App\Models\Team;
use App\Models\User;
use App\Services\MemberMapCacheService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class UpdateProfileInformationFormTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function createUser(): User
    {
        return User::factory()->create($this->userAttributes());
    }

    private function createMember(Role $role = Role::Mitglied, array $attributes = []): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(array_merge($this->userAttributes(), [
            'current_team_id' => $team->id,
        ], $attributes));
        $team->users()->attach($user, ['role' => $role->value]);

        return $user->refresh();
    }

    private function userAttributes(): array
    {
        return [
            'vorname' => 'Max',
            'nachname' => 'Mustermann',
            'email' => 'max@example.com',
            'strasse' => 'Teststr',
            'hausnummer' => '1',
            'plz' => '12345',
            'stadt' => 'Teststadt',
            'land' => 'Deutschland',
            'telefon' => '0123',
            'mitgliedsbeitrag' => 12.00,
            'alias' => 'Maxi',
            'author_aliases' => ['Max Power'],
            'maddraxikon_username' => 'Max Muster',
            'nextcloud_username' => 'MaxCloud',
        ];
    }

    private function profileFormData(array $overrides = []): array
    {
        return array_merge($this->userAttributes(), [
            'alias' => null,
            'author_aliases' => [''],
            'contact_release_email' => false,
            'contact_release_phone' => false,
            'contact_release_maddraxikon' => false,
            'contact_release_nextcloud' => false,
            'maddraxikon_username' => null,
            'nextcloud_username' => null,
        ], $overrides);
    }

    public function test_mount_populates_state_from_user(): void
    {
        $this->actingAs($user = $this->createUser());

        $component = Livewire::test(UpdateProfileInformationForm::class);

        // Only check the fields that are explicitly set in the component's mount()
        $expectedFields = [
            'vorname',
            'nachname',
            'email',
            'strasse',
            'hausnummer',
            'plz',
            'stadt',
            'land',
            'telefon',
            'mitgliedsbeitrag',
            'alias',
            'author_aliases',
            'contact_release_email',
            'contact_release_phone',
            'contact_release_maddraxikon',
            'contact_release_nextcloud',
            'maddraxikon_username',
            'nextcloud_username',
        ];

        foreach ($expectedFields as $key) {
            $expected = match ($key) {
                'author_aliases' => $user->author_aliases ?: [''],
                'alias', 'maddraxikon_username', 'nextcloud_username' => $user->{$key} ?? '',
                default => $user->{$key},
            };
            $actual = $component->state[$key] ?? match ($key) {
                'author_aliases' => [''],
                'alias', 'maddraxikon_username', 'nextcloud_username' => '',
                'contact_release_email',
                'contact_release_phone',
                'contact_release_maddraxikon',
                'contact_release_nextcloud' => false,
                default => null,
            };

            $this->assertEquals($expected, $actual);
        }
    }

    public function test_profile_information_is_updated_without_photo(): void
    {
        $this->actingAs($user = $this->createUser());

        $called = false;
        $receivedInput = [];
        $mock = Mockery::mock(UpdatesUserProfileInformation::class);
        $mock->shouldReceive('update')
            ->once()
            ->andReturnUsing(function ($u, array $input) use (&$called, &$receivedInput) {
                $called = true;
                $receivedInput = $input;
            });
        $this->app->instance(UpdatesUserProfileInformation::class, $mock);

        $data = [
            'vorname' => 'Test',
            'nachname' => 'Name',
            'email' => 'test@example.com',
            'strasse' => 'Straße',
            'hausnummer' => '5',
            'plz' => '54321',
            'stadt' => 'Stadt',
            'land' => 'Deutschland',
            'telefon' => '9876',
            'mitgliedsbeitrag' => 20.00,
        ];

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state', $data)
            ->call('updateProfileInformation')
            ->assertHasNoErrors()
            ->assertDispatched('refresh-navigation-menu');

        $this->assertTrue($called);
        $this->assertEquals($data, $receivedInput);
    }

    public function test_profile_information_is_updated_with_photo_and_resets_property(): void
    {
        $this->actingAs($user = $this->createUser());

        $called = false;
        $receivedInput = [];
        $mock = Mockery::mock(UpdatesUserProfileInformation::class);
        $mock->shouldReceive('update')
            ->once()
            ->andReturnUsing(function ($u, array $input) use (&$called, &$receivedInput) {
                $called = true;
                $receivedInput = $input;
            });
        $this->app->instance(UpdatesUserProfileInformation::class, $mock);

        Storage::fake('public');
        $file = UploadedFile::fake()->image('avatar.jpg');

        $data = [
            'vorname' => 'Test',
            'nachname' => 'Name',
            'email' => 'test@example.com',
            'strasse' => 'Straße',
            'hausnummer' => '5',
            'plz' => '54321',
            'stadt' => 'Stadt',
            'land' => 'Deutschland',
            'telefon' => '9876',
            'mitgliedsbeitrag' => 20.00,
        ];

        $component = Livewire::test(UpdateProfileInformationForm::class)
            ->set('state', $data)
            ->set('photo', $file)
            ->call('updateProfileInformation');

        $this->assertTrue($called);
        $inputWithoutPhoto = $receivedInput;
        unset($inputWithoutPhoto['photo']);
        $this->assertEquals($data, $inputWithoutPhoto);
        $this->assertArrayHasKey('photo', $receivedInput);
        $this->assertInstanceOf(TemporaryUploadedFile::class, $receivedInput['photo']);
    }

    public function test_member_can_store_alias_but_not_author_aliases(): void
    {
        Mail::fake();
        $this->actingAs($user = $this->createMember());

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state', $this->profileFormData([
                'alias' => 'Stefan K',
                'author_aliases' => ['Soll nicht bleiben'],
            ]))
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame('Stefan K', $user->alias);
        $this->assertSame([], $user->author_aliases);
        Mail::assertNotQueued(ProfileContactUpdated::class);
    }

    public function test_changing_alias_invalidates_member_map_cache(): void
    {
        Mail::fake();
        $team = Team::membersTeam();
        $this->actingAs($user = $this->createMember());
        app(MemberMapCacheService::class)->getMemberMapData($team);
        $cacheKey = "member_map_data_v2_team_{$team->id}";

        $this->assertTrue(Cache::has($cacheKey));

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state', $this->profileFormData(['alias' => 'Neuer Nickname']))
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $this->assertSame('Neuer Nickname', $user->refresh()->alias);
        $this->assertFalse(Cache::has($cacheKey));
    }

    public function test_ehrenmitglied_can_store_multiple_author_aliases(): void
    {
        Mail::fake();
        $this->actingAs($user = $this->createMember(Role::Ehrenmitglied));

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state', $this->profileFormData([
                'alias' => 'Lucy',
                'author_aliases' => ['Ian Rolf Hill', '', 'Jo Zybell', 'Ian Rolf Hill'],
            ]))
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame('Lucy', $user->alias);
        $this->assertSame(['Ian Rolf Hill', 'Jo Zybell'], $user->author_aliases);
        Mail::assertNotQueued(ProfileContactUpdated::class);
    }

    public function test_author_aliases_are_limited_to_ten_entries(): void
    {
        Mail::fake();
        $this->actingAs($this->createMember(Role::Ehrenmitglied));

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state', $this->profileFormData([
                'author_aliases' => collect(range(1, 11))
                    ->map(fn (int $index): string => "Autor {$index}")
                    ->all(),
            ]))
            ->call('updateProfileInformation')
            ->assertHasErrors(['author_aliases']);

        Mail::assertNotQueued(ProfileContactUpdated::class);
    }

    public function test_contact_release_requires_non_blank_matching_contact_values(): void
    {
        Mail::fake();
        $this->actingAs($user = $this->createMember());

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state', $this->profileFormData([
                'telefon' => '   ',
                'contact_release_phone' => true,
                'contact_release_maddraxikon' => true,
                'maddraxikon_username' => '   ',
                'contact_release_nextcloud' => true,
                'nextcloud_username' => '   ',
            ]))
            ->call('updateProfileInformation')
            ->assertHasErrors([
                'telefon',
                'maddraxikon_username',
                'nextcloud_username',
            ]);

        $user->refresh();

        $this->assertFalse($user->contact_release_phone);
        $this->assertFalse($user->contact_release_maddraxikon);
        $this->assertFalse($user->contact_release_nextcloud);
        $this->assertSame('0123', $user->telefon);
        $this->assertSame('Max Muster', $user->maddraxikon_username);
        $this->assertSame('MaxCloud', $user->nextcloud_username);

        Mail::assertNotQueued(ProfileContactUpdated::class);
    }

    public function test_contact_update_notifies_board_roles(): void
    {
        Mail::fake();
        $user = $this->createMember(attributes: [
            'email' => 'member@example.com',
            'telefon' => '0123 456789',
        ]);
        $this->actingAs($user);

        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state', $this->profileFormData([
                'email' => $user->email,
                'telefon' => '0123 456789',
                'contact_release_email' => true,
                'contact_release_phone' => true,
                'contact_release_maddraxikon' => true,
                'contact_release_nextcloud' => true,
                'maddraxikon_username' => 'Stefan K',
                'nextcloud_username' => 'Holger',
            ]))
            ->call('updateProfileInformation')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertTrue($user->contact_release_email);
        $this->assertTrue($user->contact_release_phone);
        $this->assertTrue($user->contact_release_maddraxikon);
        $this->assertTrue($user->contact_release_nextcloud);
        $this->assertSame('https://de.maddraxikon.com/index.php?title=Benutzer:Stefan_K', $user->maddraxikonProfileUrl());
        $this->assertSame('https://cloud.maddrax-fanclub.de/u/Holger', $user->nextcloudProfileUrl());
        $this->assertNotNull($user->contact_released_at);

        Mail::assertQueued(ProfileContactUpdated::class, function (ProfileContactUpdated $mail) {
            $rendered = $mail->render();

            return $mail->hasTo('info@maddraxikon.com')
                && $mail->changedContactLabels === ['E-Mail', 'Telefon', 'Maddraxikon', 'Nextcloud']
                && str_contains($rendered, 'Kontaktdaten aktualisiert')
                && str_contains($rendered, 'Geänderte Kontaktwege')
                && str_contains($rendered, 'E-Mail, Telefon, Maddraxikon, Nextcloud');
        });
    }

    public function test_contact_update_mail_uses_change_timestamp_instead_of_render_time(): void
    {
        $user = $this->createMember(attributes: ['name' => 'Mail Test']);
        $changedAt = Carbon::parse('2026-06-06 10:15:00');

        Carbon::setTestNow('2026-06-06 12:45:00');

        try {
            $rendered = (new ProfileContactUpdated($user, ['E-Mail'], $changedAt))->render();
        } finally {
            Carbon::setTestNow();
        }

        $this->assertStringContainsString('Geänderte Kontaktwege', $rendered);
        $this->assertStringContainsString('06.06.2026 10:15', $rendered);
        $this->assertStringNotContainsString('06.06.2026 12:45', $rendered);
    }

    public function test_delete_profile_photo_removes_file_and_dispatches_event(): void
    {
        Storage::fake('public');
        $photoPath = UploadedFile::fake()->image('photo.jpg')->store('profile-photos', 'public');

        $attributes = $this->userAttributes();
        $attributes['profile_photo_path'] = $photoPath;
        $this->actingAs($user = User::factory()->create($attributes));

        $component = Livewire::test(UpdateProfileInformationForm::class)
            ->call('deleteProfilePhoto')
            ->assertDispatched('refresh-navigation-menu');

        $this->assertNull($user->fresh()->profile_photo_path);
        Storage::disk('public')->assertMissing($photoPath);
    }

    public function test_get_user_property_returns_authenticated_user(): void
    {
        $this->actingAs($user = $this->createUser());

        $component = Livewire::test(UpdateProfileInformationForm::class);
        $this->assertSame($user->id, $component->get('user')->id);
    }
}
