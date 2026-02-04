<?php

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

class CreateNewUserActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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
    }

    public function test_user_is_created_with_personal_team_when_terms_feature_disabled(): void
    {
        $action = new CreateNewUser;

        /** @var User $user */
        $user = $action->create([
            'name' => 'Alice Example',
            'email' => 'alice@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'alice@example.com',
        ]);
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertDatabaseHas('teams', [
            'user_id' => $user->id,
            'personal_team' => true,
            'name' => "Alice's Team",
        ]);
    }

    public function test_user_is_created_when_terms_feature_enabled_and_accepted(): void
    {
        config(['jetstream.features' => array_merge(config('jetstream.features', []), [Features::termsAndPrivacyPolicy()])]);

        $action = new CreateNewUser;

        /** @var User $user */
        $user = $action->create([
            'name' => 'Bob Example',
            'email' => 'bob@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'terms' => true,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'bob@example.com',
        ]);
        $this->assertDatabaseHas('teams', [
            'user_id' => $user->id,
            'personal_team' => true,
            'name' => "Bob's Team",
        ]);
    }

    public function test_terms_are_required_when_feature_is_enabled(): void
    {
        config(['jetstream.features' => array_merge(config('jetstream.features', []), [Features::termsAndPrivacyPolicy()])]);

        $this->expectException(ValidationException::class);

        (new CreateNewUser)->create([
            'name' => 'Carol Example',
            'email' => 'carol@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);
    }
}
