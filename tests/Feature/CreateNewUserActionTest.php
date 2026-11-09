<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Features;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class);

beforeEach(function (): void {
    Schema::disableForeignKeyConstraints();
    Schema::dropIfExists('users');
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->string('password');
        $table->timestamps();
    });
    Schema::enableForeignKeyConstraints();
});

test('user is created with personal team when terms feature disabled', function (): void {
    $action = new CreateNewUser;
    /** @var User $user */
    $user = $action->create(['name' => 'Alice Example', 'email' => 'alice@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123']);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'alice@example.com']);
    expect(Hash::check('secret123', $user->password))->toBeTrue();
    $this->assertDatabaseHas('teams', ['user_id' => $user->id, 'personal_team' => true, 'name' => "Alice's Team"]);
});

test('user is created when terms feature enabled and accepted', function (): void {
    config(['jetstream.features' => array_merge(config('jetstream.features', []), [Features::termsAndPrivacyPolicy()])]);
    $action = new CreateNewUser;
    /** @var User $user */
    $user = $action->create(['name' => 'Bob Example', 'email' => 'bob@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'terms' => true]);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'bob@example.com']);
    $this->assertDatabaseHas('teams', ['user_id' => $user->id, 'personal_team' => true, 'name' => "Bob's Team"]);
});

test('terms are required when feature is enabled', function (): void {
    config(['jetstream.features' => array_merge(config('jetstream.features', []), [Features::termsAndPrivacyPolicy()])]);
    expect(fn () => (new CreateNewUser)->create(['name' => 'Carol Example', 'email' => 'carol@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123']))->toThrow(ValidationException::class);
});
