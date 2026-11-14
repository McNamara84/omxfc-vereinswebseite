<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Reward;
use App\Models\RewardPurchase;
use App\Models\RpgCharacter;
use App\Models\RpgCharacterRename;
use App\Models\Team;
use App\Models\UserPoint;
use App\Services\RewardService;
use App\Services\RpgCharacterAdvancementService;
use App\Services\RpgCharacterRenameService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\RpgCharacterRenameFixtures;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class, RpgCharacterRenameFixtures::class);

beforeEach(function (): void {
    $this->progressionFixtures();
    $this->player->incrementTeamPoints(25);
});

test('rename atomically spends exactly 25 baxx and preserves the character', function (): void {
    $before = $this->character->getAttributes();
    $payload = $this->character->payload;
    $this->actingAs($this->player)->patch(route('rpg.characters.rename', $this->character), $this->renameInput('  Birka  '))
        ->assertRedirect(route('rpg.characters.index'))->assertSessionHas('success')->assertSessionHasNoErrors();
    $this->character->refresh();
    $payload['character']['character_name'] = 'Birka';
    expect($this->character->character_name)->toBe('Birka')
        ->and($this->character->payload)->toBe($payload)
        ->and($this->character->initial_payload)->toBe(json_decode($before['initial_payload'], true))
        ->and($this->character->revision)->toBe(1)
        ->and($this->character->only(['user_id', 'portrait_path', 'portrait_mime', 'portrait_original_name', 'created_at']))
        ->toEqual((new RpgCharacter)->setRawAttributes($before)->only(['user_id', 'portrait_path', 'portrait_mime', 'portrait_original_name', 'created_at']))
        ->and(app(RewardService::class)->getAvailableBaxx($this->player))->toBe(0)
        ->and($this->character->experienceBalance())->toBe(0)
        ->and(UserPoint::where('user_id', $this->player->id)->sum('points'))->toBe(25);
    $rename = RpgCharacterRename::sole();
    expect($rename->old_name)->toBe('Arkon')->and($rename->new_name)->toBe('Birka')
        ->and($rename->revision_before)->toBe(0)->and($rename->revision_after)->toBe(1)
        ->and($rename->character->id)->toBe($this->character->id)->and($rename->user->id)->toBe($this->player->id)
        ->and($rename->purchase->cost_baxx)->toBe(25)
        ->and($rename->purchase->wallet_team_id)->toBe(Team::membersTeam()->id)
        ->and($rename->purchase->reward->slug)->toBe(RpgCharacterRenameService::REWARD_SLUG);
    $this->assertDatabaseCount('reward_purchases', 1);
    $this->assertDatabaseCount('rpg_experience_entries', 0);
    $this->assertDatabaseCount('rpg_inventory_entries', 0);
});

test('multiple deliberate changes including duplicates are separately charged', function (): void {
    $this->player->incrementTeamPoints(50);
    RpgCharacter::factory()->create(['user_id' => $this->player->id, 'character_name' => 'Birka']);
    RpgCharacter::factory()->create(['user_id' => $this->leader->id, 'character_name' => 'Birka']);
    $this->renameCharacter();
    $this->renameCharacter('Arkon');
    $this->renameCharacter('Birka');
    expect($this->character->fresh()->revision)->toBe(3)
        ->and(app(RewardService::class)->getAvailableBaxx($this->player))->toBe(0);
    $this->assertDatabaseCount('reward_purchases', 3);
    $this->assertDatabaseCount('rpg_character_renames', 3);
});

test('unchanged trimmed name is free even without remaining balance', function (string $name): void {
    $this->renameCharacter();
    $this->actingAs($this->player)->patch(route('rpg.characters.rename', $this->character), $this->renameInput($name))
        ->assertSessionHas('info')->assertSessionHasNoErrors();
    expect($this->character->fresh()->revision)->toBe(1);
    $this->assertDatabaseCount('reward_purchases', 1);
    $this->assertDatabaseCount('rpg_character_renames', 1);
})->with(['identical' => 'Birka', 'outer whitespace' => '  Birka  ', 'unicode whitespace' => "\u{00a0}Birka\u{00a0}"]);

test('valid names preserve casing unicode and inner spacing', function (string $name): void {
    $this->renameCharacter($name);
    expect($this->character->fresh()->character_name)->toBe($name);
    $this->assertDatabaseCount('reward_purchases', 1);
})->with(['case only' => 'ARKON', 'unicode' => 'Äquatoria – Küstenwächter', 'inner spaces' => 'Birka  von Wudan', 'limit' => str_repeat('ä', 255)]);

test('invalid requests neither charge nor rename', function (array $changes, string $field): void {
    $this->actingAs($this->player)->patch(route('rpg.characters.rename', $this->character), array_replace($this->renameInput(), $changes))
        ->assertSessionHasErrors([$field], errorBag: 'rename');
    expect($this->character->fresh()->character_name)->toBe('Arkon');
    $this->assertDatabaseCount('reward_purchases', 0);
    $this->assertDatabaseCount('rpg_character_renames', 0);
})->with([
    'empty' => [['character_name' => ''], 'character_name'],
    'whitespace' => [['character_name' => " \t\n"], 'character_name'],
    'unicode whitespace' => [['character_name' => "\u{00a0}"], 'character_name'],
    'array' => [['character_name' => ['x']], 'character_name'],
    'number' => [['character_name' => 42], 'character_name'],
    'too long' => [['character_name' => str_repeat('ä', 256)], 'character_name'],
    'missing key' => [['submission_key' => null], 'submission_key'],
    'invalid key' => [['submission_key' => 'wrong'], 'submission_key'],
    'negative revision' => [['revision' => -1], 'revision'],
    'missing revision' => [['revision' => null], 'revision'],
    'extra price' => [['cost_baxx' => 0], 'character_name'],
    'extra owner' => [['user_id' => 1], 'character_name'],
    'extra payload' => [['payload' => []], 'character_name'],
]);

test('guests outsiders and foreign owners cannot rename through http or service', function (): void {
    $url = route('rpg.characters.rename', $this->character);
    $input = $this->renameInput();
    $this->patch($url, $input)->assertRedirect(route('login'));
    $outsider = $this->progressionMember();
    foreach ([$outsider, $this->leader, $this->progressionMember(Role::Admin)] as $actor) {
        $this->actingAs($actor)->patch($url, $input)->assertForbidden();
        expect(fn () => app(RpgCharacterRenameService::class)->rename($actor, $this->character->id, $input))
            ->toThrow(AuthorizationException::class);
    }
    $this->assertDatabaseCount('reward_purchases', 0);
});

test('admin can rename own character without an rpg membership or rpg team', function (): void {
    $admin = $this->progressionMember(Role::Admin);
    $admin->incrementTeamPoints(25);
    $this->character->update(['user_id' => $admin->id]);
    $this->rpgTeam->delete();
    $this->actingAs($admin)->patch(route('rpg.characters.rename', $this->character), $this->renameInput())
        ->assertSessionHasNoErrors()->assertSessionHas('success');
    expect(RewardPurchase::sole()->user_id)->toBe($admin->id);
});

test('access revoked after rendering is rechecked with fresh membership data', function (): void {
    $this->actingAs($this->player)->get(route('rpg.characters.index'))->assertOk();
    expect(Gate::forUser($this->player)->allows('rename', $this->character))->toBeTrue();
    $this->rpgTeam->users()->detach($this->player);
    expect(fn () => $this->renameCharacter())->toThrow(AuthorizationException::class);
    $this->patch(route('rpg.characters.rename', $this->character), $this->renameInput())->assertForbidden();
    $this->assertDatabaseCount('reward_purchases', 0);
});

test('selected ag does not change the members wallet used for payment', function (): void {
    $this->player->forceFill(['current_team_id' => $this->rpgTeam->id])->save();
    $this->player->incrementTeamPoints(200);
    $this->renameCharacter();
    expect(RewardPurchase::sole()->wallet_team_id)->toBe(Team::membersTeam()->id)
        ->and(app(RewardService::class)->getAvailableBaxx($this->player))->toBe(0)
        ->and($this->player->totalPointsForTeam($this->rpgTeam))->toBe(200);
});

test('insufficient or unavailable wallet refuses all writes', function (string $condition): void {
    match ($condition) {
        'balance' => UserPoint::where('user_id', $this->player->id)->update(['points' => 24]),
        'missing team' => Team::membersTeam()->update(['name' => 'Anderes Team']),
        'legacy' => RewardPurchase::factory()->create(['user_id' => $this->player->id, 'wallet_team_id' => null]),
    };
    $before = RewardPurchase::count();
    expect(fn () => $this->renameCharacter())->toThrow(ValidationException::class);
    expect($this->character->fresh()->revision)->toBe(0)->and(RewardPurchase::count())->toBe($before);
    $this->assertDatabaseCount('rpg_character_renames', 0);
})->with(['balance', 'missing team', 'legacy']);

test('missing system reward fails before charging', function (): void {
    Reward::where('slug', RpgCharacterRenameService::REWARD_SLUG)->delete();
    expect(fn () => $this->renameCharacter())->toThrow(ValidationException::class);
    $this->assertDatabaseCount('reward_purchases', 0);
    expect($this->character->fresh()->revision)->toBe(0);
});

test('configured price is shared by the form and recorded purchase', function (): void {
    config(['rewards.rpg_character_rename_cost_baxx' => 20]);
    $this->actingAs($this->player)->get(route('rpg.characters.index'))->assertSee('Für 20 Baxx umbenennen');
    $rename = $this->renameCharacter();
    config(['rewards.rpg_character_rename_cost_baxx' => 30]);
    expect($rename->purchase->cost_baxx)->toBe(20);
    $this->get(route('rpg.characters.history', $this->character))->assertSee('20 Baxx');
});

test('pending advancement blocks rename and preserves the request', function (): void {
    $this->credit();
    $request = app(RpgCharacterAdvancementService::class)->submit($this->player, $this->character->id, $this->advancementInput());
    $original = $request->fresh()->getAttributes();
    expect(fn () => $this->renameCharacter())->toThrow(ValidationException::class);
    expect($request->fresh()->getAttributes())->toBe($original);
    $this->assertDatabaseCount('reward_purchases', 0);
    $this->actingAs($this->player)->get(route('rpg.characters.index'))->assertSee('offener Verbesserungsantrag');
});

test('decided advancement no longer blocks rename', function (string $decision): void {
    $this->credit();
    $service = app(RpgCharacterAdvancementService::class);
    $request = $service->submit($this->player, $this->character->id, $this->advancementInput());
    $service->decide($decision === 'withdrawn' ? $this->player : $this->leader, $request->id, $decision, 'Begründete Ablehnung');
    $original = $request->fresh()->getAttributes();
    $this->renameCharacter();
    expect($request->fresh()->getAttributes())->toBe($original)->and($this->character->fresh()->character_name)->toBe('Birka');
})->with(['approved', 'rejected', 'withdrawn']);

test('stale form cannot overwrite a newer name', function (): void {
    $input = $this->renameInput('Veraltet');
    $this->renameCharacter();
    expect(fn () => app(RpgCharacterRenameService::class)->rename($this->player, $this->character->id, $input))->toThrow(ValidationException::class);
    expect($this->character->fresh()->character_name)->toBe('Birka');
    $this->assertDatabaseCount('reward_purchases', 1);
});

test('replaying a successful request after another rename never charges or restores its name', function (): void {
    $this->player->incrementTeamPoints(25);
    $service = app(RpgCharacterRenameService::class);
    $input = $this->renameInput();
    $first = $service->rename($this->player, $this->character->id, $input);
    $this->renameCharacter('Mira');
    expect($service->rename($this->player, $this->character->id, $input)->id)->toBe($first->id)
        ->and($this->character->fresh()->character_name)->toBe('Mira');
    // HTTP form strings, key casing and trimmed input describe the same operation.
    $input['revision'] = (string) $input['revision'];
    $input['submission_key'] = strtoupper($input['submission_key']);
    $input['character_name'] = ' Birka ';
    $this->actingAs($this->player)->patch(route('rpg.characters.rename', $this->character), $input)->assertSessionHasNoErrors();
    $this->assertDatabaseCount('reward_purchases', 2);
});

test('submission keys cannot be reused for changed content or another character', function (): void {
    $input = $this->renameInput();
    $service = app(RpgCharacterRenameService::class);
    $service->rename($this->player, $this->character->id, $input);
    expect(fn () => $service->rename($this->player, $this->character->id, array_replace($input, ['character_name' => 'Andere'])))
        ->toThrow(ValidationException::class);
    $other = RpgCharacter::factory()->create(['user_id' => $this->player->id]);
    expect(fn () => $service->rename($this->player, $other->id, $input))->toThrow(HttpException::class);
    $other->update(['user_id' => $this->leader->id]);
    expect(fn () => $service->rename($this->leader, $other->id, $input))->toThrow(HttpException::class);
    $this->assertDatabaseCount('reward_purchases', 1);
});

test('any persistence failure rolls back payment name revision and history', function (string $model): void {
    $original = $this->character->getAttributes();
    $model::saving(fn () => throw new \RuntimeException('Simulated write failure'));
    try {
        expect(fn () => $this->renameCharacter())->toThrow(\RuntimeException::class, 'Simulated write failure');
        expect($this->character->fresh()->getAttributes())->toBe($original)
            ->and(app(RewardService::class)->getAvailableBaxx($this->player))->toBe(25);
        $this->assertDatabaseCount('reward_purchases', 0);
        $this->assertDatabaseCount('rpg_character_renames', 0);
    } finally {
        $model::flushEventListeners();
        $model::clearBootedModels();
    }
})->with([RewardPurchase::class, RpgCharacter::class, RpgCharacterRename::class]);

test('errors reopen only the submitted character form and escape its preserved value', function (): void {
    RpgCharacter::factory()->create(['user_id' => $this->player->id]);
    $name = '<script>alert(1)</script>'.str_repeat('x', 256);
    $url = route('rpg.characters.rename', $this->character);
    $response = $this->actingAs($this->player)->followingRedirects()->patch($url, $this->renameInput($name))->assertOk();
    $response->assertSee($name)->assertDontSee($name, false);
    expect(substr_count($response->getContent(), 'data-testid="rpg-character-rename"  open'))->toBe(1);
});

test('malformed array name still renders the validation page safely', function (): void {
    $this->actingAs($this->player)->followingRedirects()->patch(route('rpg.characters.rename', $this->character), array_replace($this->renameInput(), ['character_name' => []]))
        ->assertOk()->assertSee('aria-invalid="true"', false);
});

test('history is private escaped immutable and reflects refunds', function (): void {
    $rename = $this->renameCharacter('<b>Birka</b>');
    $url = route('rpg.characters.history', $this->character);
    $this->actingAs($this->player)->get($url)->assertOk()->assertSee('<b>Birka</b>')->assertDontSee('<b>Birka</b>', false)->assertSee('25 Baxx');
    $this->actingAs($this->leader)->get($url)->assertOk()->assertSee('Namensänderungen');
    $outsider = $this->progressionMember();
    $this->rpgTeam->users()->attach($outsider, ['role' => Role::Mitglied->value]);
    $this->actingAs($outsider)->get($url)->assertForbidden();
    $admin = $this->progressionMember(Role::Admin);
    $this->actingAs($admin)->get($url)->assertForbidden();
    app(RewardService::class)->refundPurchase($rename->purchase, $admin);
    $this->actingAs($this->player)->get($url)->assertSee('Erstattet am');
    expect($this->character->fresh()->character_name)->toBe('<b>Birka</b>')
        ->and(app(RewardService::class)->getAvailableBaxx($this->player))->toBe(25)
        ->and(fn () => $rename->update(['new_name' => 'Manipuliert']))->toThrow(\LogicException::class);
});

test('name history paginates independently of advancement requests', function (): void {
    $this->player->incrementTeamPoints(500);
    for ($i = 1; $i <= 21; $i++) {
        $this->renameCharacter('Name '.$i);
    }
    $url = route('rpg.characters.history', $this->character);
    $this->actingAs($this->player)->get($url.'?renames_page=2&page=1')->assertOk()
        ->assertViewHas('renames', fn ($page) => $page->currentPage() === 2 && $page->count() === 1)
        ->assertViewHas('requests', fn ($page) => $page->currentPage() === 1 && str_contains($page->url(2), 'renames_page=2'));
});

test('new pdf uses the renamed character and new download filename', function (): void {
    $this->renameCharacter('Birka von Wudan');
    Pdf::shouldReceive('view')->once()->with('rpg.char-sheet', \Mockery::on(fn (array $data): bool => $data['character']['character_name'] === 'Birka von Wudan'))
        ->andReturn(new class extends PdfBuilder
        {
            public function toResponse($request): Response
            {
                return response('PDF', 200, $this->responseHeaders);
            }
        });
    $this->actingAs($this->player)->get(route('rpg.characters.pdf', $this->character))->assertOk()
        ->assertHeader('Content-Disposition', 'inline; filename="birka-von-wudan.pdf"');
});

test('character deletion keeps purchase and audit while account deletion follows existing cascades', function (): void {
    $rename = $this->renameCharacter();
    $this->actingAs($this->player)->delete(route('rpg.characters.destroy', $this->character))->assertRedirect();
    expect($rename->fresh()->rpg_character_id)->toBeNull()->and($rename->fresh()->purchase->isRefunded())->toBeFalse();
    $this->patch(route('rpg.characters.rename', $this->character), ['character_name' => 'Birka', 'revision' => 0, 'submission_key' => (string) Str::uuid()])->assertNotFound();
    $this->player->delete();
    $this->assertDatabaseCount('reward_purchases', 0);
    $this->assertDatabaseCount('rpg_character_renames', 0);
});

test('migration rollback preserves even refunded purchases and detached character histories', function (): void {
    $rename = $this->renameCharacter();
    app(RewardService::class)->refundPurchase($rename->purchase, $this->leader);
    $this->character->delete();
    $migration = require database_path('migrations/2026_11_14_120000_create_rpg_character_renames_table.php');
    expect(fn () => $migration->down())->toThrow(\RuntimeException::class);
    $this->assertDatabaseCount('rpg_character_renames', 1);
    RpgCharacterRename::query()->delete();
    expect(fn () => $migration->down())->toThrow(\RuntimeException::class);
    $this->assertDatabaseCount('reward_purchases', 1);
});

test('empty migration can be rolled back and reapplied without changing existing characters', function (): void {
    $before = $this->character->getAttributes();
    $migration = require database_path('migrations/2026_11_14_120000_create_rpg_character_renames_table.php');
    $migration->down();
    $migration->up();
    expect(Reward::where('slug', RpgCharacterRenameService::REWARD_SLUG)->sole()->is_active)->toBeFalse()
        ->and($this->character->fresh()->getAttributes())->toBe($before);
    $this->assertDatabaseCount('reward_purchases', 0);
});
