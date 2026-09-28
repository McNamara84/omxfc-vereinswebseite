<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\VeranstaltungsBaxxStatus;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPoint;
use App\Models\Veranstaltung;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VeranstaltungsBaxxService
{
    private const ELIGIBLE_ROLES = [Role::Mitglied, Role::Ehrenmitglied, Role::Kassenwart, Role::Vorstand, Role::Admin];

    public function __construct(private readonly MembersTeamMembershipLock $membershipLock) {}

    public function speichern(Veranstaltung $veranstaltung, array $data, User $actor): Veranstaltung
    {
        Gate::forUser($actor)->authorize('manage', Veranstaltung::class);
        Validator::make($data, [
            'teilnahme_baxx' => ['sometimes', 'required', 'integer', 'min:2', 'max:50'],
        ])->validate();

        return DB::transaction(function () use ($veranstaltung, $data, $actor) {
            $event = $veranstaltung->exists
                ? Veranstaltung::query()->lockForUpdate()->findOrFail($veranstaltung->id)
                : new Veranstaltung;

            $archivieren = ($data['status'] ?? $event->status) === 'archiviert'
                && (! $event->exists || $event->status !== 'archiviert');

            if ($archivieren) {
                Gate::forUser($actor)->authorize('archive', $event);
            }

            if (array_key_exists('teilnahme_baxx', $data)) {
                Gate::forUser($actor)->authorize('setBaxx', $event);

                if ((int) $data['teilnahme_baxx'] !== $event->teilnahme_baxx) {
                    $this->assertOpen($event, 'teilnahme_baxx');
                }
            }

            // Abschlussfelder sind serververwaltet und gehören nicht zu den fillable-Feldern.
            $event->fill($data);
            $event->save();

            if ($archivieren && $event->baxx_status === VeranstaltungsBaxxStatus::Offen) {
                $this->abschliessen($event, $actor);
            }

            if ($event->ist_highlight) {
                Veranstaltung::query()->whereKeyNot($event->id)->update(['ist_highlight' => false]);
            }

            return $event;
        }, 3);
    }

    public function setTeilnahme(Veranstaltung $veranstaltung, int $anmeldungId, bool $teilgenommen, User $actor): void
    {
        Gate::forUser($actor)->authorize('confirmAttendance', $veranstaltung);

        DB::transaction(function () use ($veranstaltung, $anmeldungId, $teilgenommen, $actor) {
            $event = Veranstaltung::query()->lockForUpdate()->findOrFail($veranstaltung->id);
            $this->assertOpen($event);
            $anmeldung = $event->anmeldungen()->findOrFail($anmeldungId);

            if ($teilgenommen && ! $anmeldung->user?->hasAnyMitgliederTeamRole(...self::ELIGIBLE_ROLES)) {
                throw ValidationException::withMessages(['teilnahme' => 'Nur aktuelle Mitglieder mit berechtigter Rolle können bestätigt werden.']);
            }

            if ($anmeldung->teilgenommen === $teilgenommen) {
                return;
            }

            $anmeldung->forceFill([
                'teilgenommen' => $teilgenommen,
                'teilnahme_bestaetigt_am' => $teilgenommen ? now() : null,
                'teilnahme_bestaetigt_von' => $teilgenommen ? $actor->id : null,
            ])->save();
        }, 3);
    }

    public function deleteAnmeldung(Veranstaltung $veranstaltung, int $anmeldungId, User $actor): string
    {
        Gate::forUser($actor)->authorize('manage', $veranstaltung);

        return DB::transaction(function () use ($veranstaltung, $anmeldungId) {
            $event = Veranstaltung::query()->lockForUpdate()->findOrFail($veranstaltung->id);

            if ($event->baxx_status === VeranstaltungsBaxxStatus::Abgeschlossen) {
                throw ValidationException::withMessages(['teilnahme' => 'Anmeldungen einer abgerechneten Veranstaltung können nicht gelöscht werden.']);
            }

            $anmeldung = $event->anmeldungen()->findOrFail($anmeldungId);
            $name = $anmeldung->full_name;
            $anmeldung->delete();

            return $name;
        }, 3);
    }

    /** @return Collection<int, int> */
    public function eligibleUserIds(Veranstaltung $veranstaltung): Collection
    {
        $team = Team::membersTeam();

        if (! $team) {
            return collect();
        }

        $query = DB::table('team_user')
            ->where('team_id', $team->id)
            ->whereIn('user_id', $veranstaltung->anmeldungen()->whereNotNull('user_id')->select('user_id'))
            ->orderBy('user_id');

        return $query->get(['user_id', 'role'])
            ->filter(fn ($membership) => in_array(Role::tryFrom($membership->role ?? ''), self::ELIGIBLE_ROLES, true))
            ->pluck('user_id')->map(fn ($id) => (int) $id)->unique()->values();
    }

    private function abschliessen(Veranstaltung $event, User $actor): void
    {
        $team = Team::membersTeam();

        if (! $team) {
            throw ValidationException::withMessages(['status' => 'Das Mitglieder-Team fehlt. Die Veranstaltung wurde nicht archiviert.']);
        }

        $userIds = $event->anmeldungen()->where('teilgenommen', true)
            ->whereNotNull('user_id')
            ->orderBy('user_id')->pluck('user_id')->unique();

        if ($userIds->isNotEmpty()) {
            // Gleiche Sperrreihenfolge wie die vorhandene Mitgliederverwaltung:
            // Mitglieder-Team, Benutzer, Mitgliedschaftszeilen (jeweils nach ID).
            $this->membershipLock->run($userIds->all(), function (LockedMembersTeamMemberships $memberships) use ($userIds, $event) {
                foreach ($userIds as $userId) {
                    if (! $memberships->hasRole($userId, ...self::ELIGIBLE_ROLES)) {
                        continue;
                    }

                    UserPoint::query()->create([
                        'user_id' => $userId,
                        'team_id' => $memberships->team->id,
                        'veranstaltung_id' => $event->id,
                        'points' => $event->teilnahme_baxx,
                    ]);
                }
            }, attempts: 1);
        }

        $event->forceFill([
            'baxx_status' => VeranstaltungsBaxxStatus::Abgeschlossen,
            'baxx_abgeschlossen_am' => now(),
            'baxx_abgeschlossen_von' => $actor->id,
        ])->save();
    }

    private function assertOpen(Veranstaltung $veranstaltung, string $field = 'teilnahme'): void
    {
        if (! $veranstaltung->canEditTeilnahmeBaxx()) {
            throw ValidationException::withMessages([$field => 'Teilnahme und Baxx-Betrag sind für diese Veranstaltung gesperrt.']);
        }
    }
}
