<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use Illuminate\Support\Arr;
use Illuminate\Support\Js;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RpgCharEditorAccessTestFixtures::class);

test('ag rollenspiel member can access editor', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    $this->actingAs($member)->get('/rpg/char-editor')->assertOk()->assertSee('Charakter-Editor')->assertSee('data-testid="char-editor-form"', false)->assertSee('action="'.route('rpg.characters.store').'"', false)->assertSee('formaction="'.route('rpg.char-editor.pdf').'"', false);
});

test('editor exposes special rule descriptions to assistive technology', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    $this->actingAs($member)->get('/rpg/char-editor')->assertOk()->assertSee('window.rpgCharEditorRules', false)->assertSee('data-testid="attribute-help-st"', false)->assertSee('aria-describedby="attribute-description-st"', false)->assertSee('id="attribute-description-st"', false)->assertSee('x-text="attributeTooltip(', false)->assertSee('aria-describedby="advantage-description-0"', false)->assertSee('id="advantage-description-0"', false)->assertSee('x-text="advantageTooltip(', false)->assertSee('aria-describedby="disadvantage-description-0"', false)->assertSee('id="disadvantage-description-0"', false)->assertSee('x-text="disadvantageTooltip(', false);
});

test('editor base text inputs have accessible names', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    $response = $this->actingAs($member)->get('/rpg/char-editor');
    $response->assertOk();
    $crawler = new Crawler($response->getContent());
    expect($crawler->filter('input[name="player_name"]:not([type="hidden"])[aria-label="Spielername"]'))->toHaveCount(1)
        ->and($crawler->filter('input[name="character_name"]:not([type="hidden"])[aria-label="Charaktername"]'))->toHaveCount(1);
});

test('editor only exposes whitelisted old input to javascript', function (): void {
    $member = $this->addAgRollenspielMembership($this->createMember());
    $oldInput = ['player_name' => 'Geretteter Spieler', 'attributes' => ['st' => 2], 'rule_sources' => ['expansion-1' => '0'], 'marsianer_replacement_skill' => 'Heiler', 'marsianer_bonus_skill' => 'Unterhalten'];
    $this->withSession(['_old_input' => $oldInput + ['email' => 'privat@example.test', 'password' => 'super-secret-old-input']])->actingAs($member)->get('/rpg/char-editor')->assertOk()->assertSee('window.rpgCharEditorOldInput', false)->assertSee('window.rpgCharEditorOldInput = '.Js::from(Arr::only($oldInput, ['rule_sources', 'marsianer_replacement_skill', 'marsianer_bonus_skill', 'player_name', 'attributes']))->toHtml().';', false)->assertSee('Geretteter Spieler')->assertDontSee('privat@example.test')->assertDontSee('super-secret-old-input');
});

test('global admin can access editor with different current team', function (): void {
    $admin = $this->createManagementUserWithDifferentCurrentTeam(Role::Admin);
    $this->actingAs($admin)->get('/rpg/char-editor')->assertOk()->assertSee('Charakter-Editor');
});

test('member without ag rollenspiel is forbidden from editor', function (): void {
    $user = $this->createMember();
    $this->actingAs($user)->get('/rpg/char-editor')->assertForbidden();
});
