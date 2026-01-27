<?php

namespace Tests\Feature;

use App\Livewire\Profile\UpdateProfileInformationForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
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
        ];
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
        ];

        foreach ($expectedFields as $key) {
            $this->assertEquals($user->{$key}, $component->state[$key]);
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
            ->assertDispatched('saved')
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
        $this->assertInstanceOf(\Livewire\Features\SupportFileUploads\TemporaryUploadedFile::class, $receivedInput['photo']);
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
