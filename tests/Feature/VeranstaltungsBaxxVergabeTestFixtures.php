<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Livewire\FantreffenAdminDashboard;
use App\Models\FantreffenAnmeldung;
use App\Models\Team;
use App\Models\User;
use App\Models\Veranstaltung;
use App\Services\VeranstaltungsBaxxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

trait VeranstaltungsBaxxVergabeTestFixtures
{
    use RefreshDatabase;

    private function member(Role $role = Role::Mitglied): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $user->teams()->attach($team->id, ['role' => $role->value]);

        return $user;
    }

    private function event(array $data = []): Veranstaltung
    {
        return Veranstaltung::create(array_merge(['titel' => 'Baxx-Testveranstaltung', 'slug' => 'baxx-'.Str::uuid(), 'status' => 'veroeffentlicht', 'anmeldung_aktiv' => true], $data));
    }

    private function registration(Veranstaltung $event, ?User $member = null, bool $confirmed = false): FantreffenAnmeldung
    {
        $anmeldung = $event->anmeldungen()->create(['user_id' => $member?->id, 'vorname' => $member?->vorname ?? 'Gast', 'nachname' => $member?->nachname ?? 'Test', 'email' => $member?->email ?? Str::uuid().'@example.com', 'ist_mitglied' => $member !== null, 'payment_status' => 'free', 'payment_amount' => 0]);
        $anmeldung->forceFill(['teilgenommen' => $confirmed])->save();

        return $anmeldung;
    }

    private function archive(Veranstaltung $event, User $actor, array $data = []): Veranstaltung
    {
        return app(VeranstaltungsBaxxService::class)->speichern($event, array_merge(['status' => 'archiviert'], $data), $actor);
    }

    private function payload(Veranstaltung $event, array $data = []): array
    {
        return array_merge(['titel' => $event->titel, 'slug' => $event->slug, 'status' => $event->status], $data);
    }

    private function csv(Veranstaltung $event): array
    {
        $component = new FantreffenAdminDashboard;
        $component->mount($event->fresh());
        $response = $component->exportCsv();
        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        return array_map(fn ($row) => str_getcsv($row, ',', '"', ''), explode("\n", trim(ltrim($csv, '﻿'))));
    }
}
