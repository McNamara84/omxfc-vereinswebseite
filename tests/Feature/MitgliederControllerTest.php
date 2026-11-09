<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesUserWithRole;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(CreatesUserWithRole::class, RefreshDatabase::class);

test('export csv requires proper role', function (): void {
    $this->actingMember(Role::Mitglied);
    $response = $this->from('/mitglieder')->post('/mitglieder/export-csv', ['export_fields' => ['name', 'email']]);
    $response->assertStatus(403);
});

test('export csv returns csv for kassenwart', function (): void {
    $this->actingKassenwart();
    Team::membersTeam()->users()->attach(User::factory()->create(), ['role' => Role::Mitglied->value]);
    $response = $this->post('/mitglieder/export-csv', ['export_fields' => ['name', 'email']]);
    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();
    expect($csv)->toContain('Name');
});

test('csv export roundtrips quotes backslashes multiline text and empty fields', function (): void {
    $this->actingKassenwart();
    $member = User::factory()->create(['vorname' => 'Jörg, \"Leser"\\', 'nachname' => "Müller\nZweite Zeile", 'strasse' => 'C:\Verein\\', 'hausnummer' => '']);
    Team::membersTeam()->users()->attach($member, ['role' => Role::Mitglied->value]);
    $response = $this->post('/mitglieder/export-csv', ['export_fields' => ['name', 'email', 'adresse']]);
    $response->assertOk();
    $csv = $response->streamedContent();
    expect($csv)->toStartWith('﻿');
    $stream = fopen('php://temp', 'w+');
    fwrite($stream, substr($csv, 3));
    rewind($stream);
    $headers = fgetcsv($stream, escape: '');
    $matched = null;
    while (($row = fgetcsv($stream, escape: '')) !== false) {
        if (($row[3] ?? null) === $member->email) {
            $matched = array_combine($headers, $row);
        }
    }
    fclose($stream);
    expect($matched)->not->toBeNull()
        ->and($matched['Vorname'])->toBe($member->vorname)
        ->and($matched['Nachname'])->toBe($member->nachname)
        ->and($matched['Straße'])->toBe($member->strasse)
        ->and($matched['Hausnummer'])->toBe('');
});

test('get all emails returns only for privileged roles', function (): void {
    $team = Team::membersTeam();
    $team->users()->attach(User::factory()->create(['email' => 'a@a.de']), ['role' => Role::Mitglied->value]);
    $team->users()->attach(User::factory()->create(['email' => 'b@a.de']), ['role' => Role::Mitglied->value]);
    $this->actingAs($this->actingMember('Kassenwart'));
    $response = $this->getJson('/mitglieder/all-emails');
    $response->assertOk();
    $data = $response->json('emails');
    expect($data)->toContain('a@a.de')
        ->toContain('b@a.de');
    collect(explode('; ', $data))->each(fn (string $email) => expect($email)->toBeEmail());
    $this->actingAs($this->actingMember('Mitglied'));
    $this->getJson('/mitglieder/all-emails')->assertStatus(403);
});

test('index renders copy email button only for privileged roles', function (): void {
    $team = Team::membersTeam();
    $team->users()->attach(User::factory()->create(['email' => 'copyme@example.test']), ['role' => Role::Mitglied->value]);
    $this->actingAs($this->actingMember('Kassenwart'));
    $this->get('/mitglieder')->assertOk()->assertSee('data-copy-email', false);
    $this->actingAs($this->actingMember('Mitglied'));
    $this->get('/mitglieder')->assertOk()->assertDontSee('data-copy-email', false);
});

test('higher rank user can change member role', function (): void {
    $team = Team::membersTeam();
    $board = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($board, ['role' => Role::Vorstand->value]);
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    $this->actingAs($board);
    $response = $this->from('/mitglieder')->put("/mitglieder/{$member->id}/role", ['role' => Role::Ehrenmitglied->value]);
    $response->assertRedirect('/mitglieder');
    $this->assertDatabaseHas('team_user', ['user_id' => $member->id, 'role' => Role::Ehrenmitglied->value]);
});

test('member cannot change member role', function (): void {
    $team = Team::membersTeam();
    $acting = $this->actingMember('Mitglied');
    $this->actingAs($acting);
    $target = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($target, ['role' => Role::Mitglied->value]);
    $response = $this->from('/mitglieder')->put("/mitglieder/{$target->id}/role", ['role' => Role::Ehrenmitglied->value]);
    $response->assertStatus(403);
});

test('cannot assign role higher than own', function (): void {
    $team = Team::membersTeam();
    $board = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($board, ['role' => Role::Vorstand->value]);
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    $this->actingAs($board);
    $response = $this->from('/mitglieder')->put("/mitglieder/{$member->id}/role", ['role' => Role::Admin->value]);
    $response->assertRedirect('/mitglieder');
    $response->assertSessionHas('error');
    $this->assertDatabaseHas('team_user', ['user_id' => $member->id, 'role' => Role::Mitglied->value]);
});

test('user cannot remove self', function (): void {
    $team = Team::membersTeam();
    $board = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($board, ['role' => Role::Vorstand->value]);
    $this->actingAs($board);
    $response = $this->from('/mitglieder')->delete("/mitglieder/{$board->id}");
    $response->assertRedirect('/mitglieder');
    $response->assertSessionHas('error');
    $this->assertDatabaseHas('users', ['id' => $board->id]);
});

test('member cannot remove member', function (): void {
    $team = Team::membersTeam();
    $acting = $this->actingMember('Mitglied');
    $this->actingAs($acting);
    $target = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($target, ['role' => Role::Mitglied->value]);
    $response = $this->from('/mitglieder')->delete("/mitglieder/{$target->id}");
    $response->assertStatus(403);
    $this->assertDatabaseHas('users', ['id' => $target->id]);
});

test('higher rank user can remove member', function (): void {
    $team = Team::membersTeam();
    $board = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($board, ['role' => Role::Vorstand->value]);
    $member = User::factory()->create(['current_team_id' => $team->id]);
    $team->users()->attach($member, ['role' => Role::Mitglied->value]);
    $this->actingAs($board);
    $response = $this->from('/mitglieder')->delete("/mitglieder/{$member->id}");
    $response->assertRedirect('/mitglieder');
    $this->assertDatabaseMissing('users', ['id' => $member->id]);
    $this->assertDatabaseMissing('team_user', ['user_id' => $member->id]);
});
