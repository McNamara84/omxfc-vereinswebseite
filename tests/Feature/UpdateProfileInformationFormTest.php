<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Enums\MaddraxikonAccountLinkStatus;
use App\Enums\Role;
use App\Livewire\Profile\UpdateProfileInformationForm;
use App\Mail\ProfileContactUpdated;
use App\Models\MaddraxikonAccountLink;
use App\Models\Team;
use App\Models\User;
use App\Services\MemberMapCacheService;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(UpdateProfileInformationFormTestFixtures::class);
dataset('UpdateProfileInformationFormTest.memberMapCacheFieldProvider', static fn (): array => ['alias' => ['alias', 'Neuer Nickname'], 'first name' => ['vorname', 'Moritz'], 'last name' => ['nachname', 'Musterfrau'], 'postal code' => ['plz', '54321'], 'city' => ['stadt', 'Neue Stadt'], 'country' => ['land', 'Schweiz']]);
afterEach(function (): void {
    Mockery::close();
});
test('mount populates state from user', function (): void {
    $this->actingAs($user = $this->createUser());
    $component = Livewire::test(UpdateProfileInformationForm::class);
    // Only check the fields that are explicitly set in the component's mount()
    $expectedFields = ['vorname', 'nachname', 'email', 'strasse', 'hausnummer', 'plz', 'stadt', 'land', 'telefon', 'mitgliedsbeitrag', 'alias', 'author_aliases', 'contact_release_email', 'contact_release_phone', 'contact_release_maddraxikon', 'contact_release_nextcloud', 'nextcloud_username'];
    foreach ($expectedFields as $key) {
        $expected = match ($key) {
            'author_aliases' => $user->author_aliases ?: [''],
            'alias', 'nextcloud_username' => $user->{$key} ?? '',
            default => $user->{$key},
        };
        $actual = $component->state[$key] ?? match ($key) {
            'author_aliases' => [''],
            'alias', 'nextcloud_username' => '',
            'contact_release_email', 'contact_release_phone', 'contact_release_maddraxikon', 'contact_release_nextcloud' => false,
            default => null,
        };
        expect($actual)->toEqual($expected);
    }
});
test('profile information is updated without photo', function (): void {
    $this->actingAs($user = $this->createUser());
    $called = false;
    $receivedInput = [];
    $mock = Mockery::mock(UpdatesUserProfileInformation::class);
    $mock->shouldReceive('update')->once()->andReturnUsing(function ($u, array $input) use (&$called, &$receivedInput) {
        $called = true;
        $receivedInput = $input;
    });
    $this->app->instance(UpdatesUserProfileInformation::class, $mock);
    $data = ['vorname' => 'Test', 'nachname' => 'Name', 'email' => 'test@example.com', 'strasse' => 'Straße', 'hausnummer' => '5', 'plz' => '54321', 'stadt' => 'Stadt', 'land' => 'Deutschland', 'telefon' => '9876', 'mitgliedsbeitrag' => 20.0];
    Livewire::test(UpdateProfileInformationForm::class)->set('state', $data)->call('updateProfileInformation')->assertHasNoErrors()->assertDispatched('refresh-navigation-menu');
    expect($called)->toBeTrue()
        ->and($receivedInput)->toEqual($data);
});
test('profile information is updated with photo and resets property', function (): void {
    $this->actingAs($user = $this->createUser());
    $called = false;
    $receivedInput = [];
    $mock = Mockery::mock(UpdatesUserProfileInformation::class);
    $mock->shouldReceive('update')->once()->andReturnUsing(function ($u, array $input) use (&$called, &$receivedInput) {
        $called = true;
        $receivedInput = $input;
    });
    $this->app->instance(UpdatesUserProfileInformation::class, $mock);
    Storage::fake('public');
    $file = UploadedFile::fake()->image('avatar.jpg');
    $data = ['vorname' => 'Test', 'nachname' => 'Name', 'email' => 'test@example.com', 'strasse' => 'Straße', 'hausnummer' => '5', 'plz' => '54321', 'stadt' => 'Stadt', 'land' => 'Deutschland', 'telefon' => '9876', 'mitgliedsbeitrag' => 20.0];
    $component = Livewire::test(UpdateProfileInformationForm::class)->set('state', $data)->set('photo', $file)->call('updateProfileInformation');
    expect($called)->toBeTrue();
    $inputWithoutPhoto = $receivedInput;
    unset($inputWithoutPhoto['photo']);
    expect($inputWithoutPhoto)->toEqual($data)
        ->and($receivedInput)->toHaveKey('photo')
        ->and($receivedInput['photo'])->toBeInstanceOf(TemporaryUploadedFile::class);
});
test('profile photo replacement is committed with profile update', function (): void {
    Mail::fake();
    Storage::fake('public');
    $oldPhotoPath = UploadedFile::fake()->image('old-photo.jpg')->store('profile-photos', 'public');
    $user = $this->createUser();
    $user->forceFill(['profile_photo_path' => $oldPhotoPath])->save();
    app(UpdateUserProfileInformation::class)->update($user, $this->profileFormData(['email' => $user->email, 'photo' => UploadedFile::fake()->image('new-photo.jpg')]));
    $newPhotoPath = $user->refresh()->profile_photo_path;
    expect($newPhotoPath)->not->toBeNull()->not->toBe($oldPhotoPath);
    Storage::disk('public')->assertExists($newPhotoPath);
    Storage::disk('public')->assertMissing($oldPhotoPath);
});
test('failed profile update rolls back photo replacement', function (): void {
    Mail::fake();
    Storage::fake('public');
    $oldPhotoPath = UploadedFile::fake()->image('old-photo.jpg')->store('profile-photos', 'public');
    $user = $this->createUser();
    $user->forceFill(['profile_photo_path' => $oldPhotoPath])->save();
    $conflictingEmail = 'profile-conflict@example.com';
    $conflictCreated = false;
    User::retrieved(function (User $retrievedUser) use ($user, $conflictingEmail, &$conflictCreated): void {
        if ($conflictCreated || $retrievedUser->id !== $user->id) {
            return;
        }
        $conflictCreated = true;
        User::factory()->create(['email' => $conflictingEmail]);
    });
    try {
        app(UpdateUserProfileInformation::class)->update($user, $this->profileFormData(['vorname' => 'Nicht gespeichert', 'email' => $conflictingEmail, 'photo' => UploadedFile::fake()->image('new-photo.jpg')]));
        $this->fail('The profile update should have failed due to the email conflict.');
    } catch (QueryException) {
        // The database failure must leave both the profile and its photo unchanged.
    }
    $user->refresh();
    expect($conflictCreated)->toBeTrue()
        ->and($user->vorname)->toBe('Max')
        ->and($user->profile_photo_path)->toBe($oldPhotoPath);
    Storage::disk('public')->assertExists($oldPhotoPath);
    expect(Storage::disk('public')->allFiles('profile-photos'))->toBe([$oldPhotoPath]);
});
test('member can store alias but not author aliases', function (): void {
    Mail::fake();
    $this->actingAs($user = $this->createMember());
    Livewire::test(UpdateProfileInformationForm::class)->set('state', $this->profileFormData(['alias' => 'Stefan K', 'author_aliases' => ['Soll nicht bleiben']]))->call('updateProfileInformation')->assertHasNoErrors();
    $user->refresh();
    expect($user->alias)->toBe('Stefan K')
        ->and($user->author_aliases)->toBe([]);
    Mail::assertNotQueued(ProfileContactUpdated::class);
});
test('changing member map profile field invalidates member map cache', function (string $field, string $newValue): void {
    Mail::fake();
    $team = Team::membersTeam();
    $this->actingAs($user = $this->createMember(attributes: ['lat' => 50.0, 'lon' => 10.0]));
    app(MemberMapCacheService::class)->getMemberMapData($team);
    $cacheKey = "member_map_data_v2_team_{$team->id}";
    expect(Cache::has($cacheKey))->toBeTrue();
    Livewire::test(UpdateProfileInformationForm::class)->set('state', $this->profileFormData(['alias' => $field === 'alias' ? $newValue : $user->alias, $field => $newValue]))->call('updateProfileInformation')->assertHasNoErrors();
    expect($user->refresh()->{$field})->toBe($newValue)
        ->and(Cache::has($cacheKey))->toBeFalse();
})->with('UpdateProfileInformationFormTest.memberMapCacheFieldProvider');
test('changing unrelated profile field keeps member map cache', function (): void {
    Mail::fake();
    $team = Team::membersTeam();
    $this->actingAs($user = $this->createMember(attributes: ['lat' => 50.0, 'lon' => 10.0]));
    app(MemberMapCacheService::class)->getMemberMapData($team);
    $cacheKey = "member_map_data_v2_team_{$team->id}";
    expect(Cache::has($cacheKey))->toBeTrue();
    Livewire::test(UpdateProfileInformationForm::class)->set('state', $this->profileFormData(['alias' => $user->alias, 'telefon' => '09876']))->call('updateProfileInformation')->assertHasNoErrors();
    expect($user->refresh()->telefon)->toBe('09876')
        ->and(Cache::has($cacheKey))->toBeTrue();
});
test('ehrenmitglied can store multiple author aliases', function (): void {
    Mail::fake();
    $this->actingAs($user = $this->createMember(Role::Ehrenmitglied));
    Livewire::test(UpdateProfileInformationForm::class)->set('state', $this->profileFormData(['alias' => 'Lucy', 'author_aliases' => ['Ian Rolf Hill', '', 'Jo Zybell', 'Ian Rolf Hill']]))->call('updateProfileInformation')->assertHasNoErrors();
    $user->refresh();
    expect($user->alias)->toBe('Lucy')
        ->and($user->author_aliases)->toBe(['Ian Rolf Hill', 'Jo Zybell']);
    Mail::assertNotQueued(ProfileContactUpdated::class);
});
test('author aliases are limited to ten entries', function (): void {
    Mail::fake();
    $this->actingAs($this->createMember(Role::Ehrenmitglied));
    Livewire::test(UpdateProfileInformationForm::class)->set('state', $this->profileFormData(['author_aliases' => collect(range(1, 11))->map(fn (int $index): string => "Autor {$index}")->all()]))->call('updateProfileInformation')->assertHasErrors(['author_aliases']);
    Mail::assertNotQueued(ProfileContactUpdated::class);
});
test('contact release requires values and active maddraxikon link', function (): void {
    Mail::fake();
    $this->actingAs($user = $this->createMember());
    Livewire::test(UpdateProfileInformationForm::class)->set('state', $this->profileFormData(['telefon' => '   ', 'contact_release_phone' => true, 'contact_release_maddraxikon' => true, 'contact_release_nextcloud' => true, 'nextcloud_username' => '   ']))->call('updateProfileInformation')->assertHasErrors(['telefon', 'contact_release_maddraxikon', 'nextcloud_username']);
    $user->refresh();
    expect($user->contact_release_phone)->toBeFalse()
        ->and($user->contact_release_maddraxikon)->toBeFalse()
        ->and($user->contact_release_nextcloud)->toBeFalse()
        ->and($user->telefon)->toBe('0123')
        ->and($user->nextcloud_username)->toBe('MaxCloud');
    Mail::assertNotQueued(ProfileContactUpdated::class);
});
test('verified link allows contact release and is rendered read only', function (): void {
    Mail::fake();
    $user = $this->createMember();
    MaddraxikonAccountLink::factory()->for($user)->create(['wiki_username' => 'Kanonischer Wiki-Name']);
    $this->actingAs($user);
    Livewire::test(UpdateProfileInformationForm::class)->set('state', $this->profileFormData(['email' => $user->email, 'contact_release_maddraxikon' => true]))->call('updateProfileInformation')->assertHasNoErrors()->assertSee('Kanonischer Wiki-Name')->assertSee('Verifiziert')->assertDontSee('id="maddraxikon_username"', escape: false);
    $user->refresh();
    expect($user->contact_release_maddraxikon)->toBeTrue()
        ->and($user->maddraxikonDisplayUsername())->toBe('Kanonischer Wiki-Name');
});
test('verified link is queried only once while rendering profile form', function (): void {
    $user = $this->createMember();
    MaddraxikonAccountLink::factory()->for($user)->create(['wiki_username' => 'Einmal geladener Wiki-Name']);
    $this->actingAs($user);
    DB::flushQueryLog();
    DB::enableQueryLog();
    Livewire::test(UpdateProfileInformationForm::class)->assertSee('Einmal geladener Wiki-Name');
    $linkQueries = collect(DB::getQueryLog())->pluck('query')->filter(fn (string $query): bool => str_contains(strtolower($query), 'from "maddraxikon_account_links"'));
    DB::disableQueryLog();
    expect($linkQueries)->toHaveCount(1);
});
test('unlinked profile disables release and has no username input', function (): void {
    $this->actingAs($this->createMember());
    Livewire::test(UpdateProfileInformationForm::class)->assertSee('Verbinde dein Konto zuerst')->assertSee('disabled', escape: false)->assertDontSee('id="maddraxikon_username"', escape: false);
});
test('write time recheck keeps release private when link becomes inactive', function (): void {
    Mail::fake();
    $user = $this->createMember();
    $link = MaddraxikonAccountLink::factory()->for($user)->create();
    $linkChanged = false;
    User::retrieved(function (User $retrievedUser) use ($user, $link, &$linkChanged): void {
        if ($linkChanged || $retrievedUser->id !== $user->id) {
            return;
        }
        $linkChanged = true;
        DB::table('maddraxikon_account_links')->where('id', $link->id)->update(['status' => MaddraxikonAccountLinkStatus::Disconnected->value, 'disconnected_at' => now(), 'disconnected_at_epoch' => now()->getTimestamp()]);
    });
    app(UpdateUserProfileInformation::class)->update($user, $this->profileFormData(['email' => $user->email, 'contact_release_maddraxikon' => true]));
    expect($linkChanged)->toBeTrue()
        ->and($user->refresh()->contact_release_maddraxikon)->toBeFalse()
        ->and($link->fresh()->isActive())->toBeFalse();
    Mail::assertNotQueued(ProfileContactUpdated::class);
});
test('contact update notifies board roles', function (): void {
    Mail::fake();
    $user = $this->createMember(attributes: ['email' => 'member@example.com', 'telefon' => '0123 456789']);
    MaddraxikonAccountLink::factory()->for($user)->create(['wiki_username' => 'Stefan K']);
    $this->actingAs($user);
    Livewire::test(UpdateProfileInformationForm::class)->set('state', $this->profileFormData(['email' => $user->email, 'telefon' => '0123 456789', 'contact_release_email' => true, 'contact_release_phone' => true, 'contact_release_maddraxikon' => true, 'contact_release_nextcloud' => true, 'nextcloud_username' => 'Holger']))->call('updateProfileInformation')->assertHasNoErrors();
    $user->refresh();
    expect($user->contact_release_email)->toBeTrue()
        ->and($user->contact_release_phone)->toBeTrue()
        ->and($user->contact_release_maddraxikon)->toBeTrue()
        ->and($user->contact_release_nextcloud)->toBeTrue()
        ->and($user->maddraxikonProfileUrl())->toBe('https://de.maddraxikon.com/index.php?title=Benutzer:Stefan_K')
        ->and($user->nextcloudProfileUrl())->toBe('https://cloud.maddrax-fanclub.de/u/Holger')
        ->and($user->contact_released_at)->not->toBeNull();
    Mail::assertQueued(ProfileContactUpdated::class, function (ProfileContactUpdated $mail) {
        $rendered = $mail->render();

        return $mail->hasTo('info@maddraxikon.com') && $mail->changedContactLabels === ['E-Mail', 'Telefon', 'Maddraxikon', 'Nextcloud'] && str_contains($rendered, 'Kontaktdaten aktualisiert') && str_contains($rendered, 'Geänderte Kontaktwege') && str_contains($rendered, 'E-Mail, Telefon, Maddraxikon, Nextcloud');
    });
});
test('contact update mail uses change timestamp instead of render time', function (): void {
    $user = $this->createMember(attributes: ['name' => 'Mail Test']);
    $changedAt = Carbon::parse('2026-06-06 10:15:00');
    Carbon::setTestNow('2026-06-06 12:45:00');
    try {
        $rendered = (new ProfileContactUpdated($user, ['E-Mail'], $changedAt))->render();
    } finally {
        Carbon::setTestNow();
    }
    expect($rendered)->toContain('Geänderte Kontaktwege')
        ->toContain('06.06.2026 10:15')->not->toContain('06.06.2026 12:45');
});
test('delete profile photo removes file and dispatches event', function (): void {
    Storage::fake('public');
    $photoPath = UploadedFile::fake()->image('photo.jpg')->store('profile-photos', 'public');
    $attributes = $this->userAttributes();
    $attributes['profile_photo_path'] = $photoPath;
    $this->actingAs($user = User::factory()->create($attributes));
    $component = Livewire::test(UpdateProfileInformationForm::class)->call('deleteProfilePhoto')->assertDispatched('refresh-navigation-menu');
    expect($user->fresh()->profile_photo_path)->toBeNull();
    Storage::disk('public')->assertMissing($photoPath);
});
test('get user property returns authenticated user', function (): void {
    $this->actingAs($user = $this->createUser());
    $component = Livewire::test(UpdateProfileInformationForm::class);
    expect($component->get('user')->id)->toBe($user->id);
});
