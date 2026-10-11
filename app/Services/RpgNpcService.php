<?php

namespace App\Services;

use App\Models\RpgCombat;
use App\Models\RpgNpc;
use App\Models\User;
use App\Services\RpgCombat\NpcCombatSnapshotFactory;
use App\Support\CombatHash;
use App\Support\RpgCombatRules;
use App\Support\RpgNpcCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class RpgNpcService
{
    public function __construct(private RpgAccess $access, private NpcCombatSnapshotFactory $snapshots) {}

    public function preview(User $user, array $input): array
    {
        $this->access->requireLeader($user);
        $allowed = ['template_key', 'custom_name', 'rank', 'profession', 'skill_increases', 'reason', 'weapon', 'submission_key'];
        $this->ensure(array_diff(array_keys($input), $allowed) === [], 'Nur die vorgegebenen Vorlagenoptionen sind erlaubt.');
        $data = Validator::make($input, [
            'template_key' => ['required', Rule::in(array_keys(RpgNpcCatalog::all()))], 'custom_name' => 'nullable|string|max:255',
            'rank' => ['sometimes', Rule::in(array_keys(RpgNpcCatalog::ranks()))], 'profession' => ['sometimes', Rule::in(['Wissenschaftler', 'Techniker'])],
            'skill_increases' => 'sometimes|array', 'skill_increases.*' => 'integer|min:0|max:100', 'reason' => 'nullable|string|max:2000',
            'weapon' => ['sometimes', Rule::in(['schwert', 'messer-dolch', 'zwille', 'bogen'])],
        ])->validate();
        $row = RpgNpcCatalog::all()[$data['template_key']];
        $name = trim($data['custom_name'] ?? '');
        $this->ensure(! $row['special'] || $name === '', 'Besondere Charaktere haben einen festen Namen.');
        $configuration = [];
        if ($row['key'] === 'daamure') {
            $configuration = ['rank' => $data['rank'] ?? 'Leq', 'profession' => $data['profession'] ?? 'Techniker',
                'skill_increases' => array_map('intval', $data['skill_increases'] ?? []), 'reason' => trim($data['reason'] ?? '')];
            $budget = RpgNpcCatalog::ranks()[$configuration['rank']]['fp'];
            $this->ensure(array_diff(array_keys($configuration['skill_increases']), RpgNpcCatalog::SKILLS) === [], 'Unbekannte oder nicht freigeschaltete Fertigkeit.');
            $this->ensure(array_sum($configuration['skill_increases']) === $budget, 'Die zusätzlichen '.$budget.' FP müssen vollständig verteilt werden.');
            $this->ensure($budget === 0 || mb_strlen($configuration['reason']) >= 3, 'Die FP-Verteilung kurz begründen.');
            $skills = $row['skills'] + [$configuration['profession'] => 3];
            foreach ($configuration['skill_increases'] as $skill => $increase) {
                $skills[$skill] = ($skills[$skill] ?? 0) + $increase;
                $this->ensure($skills[$skill] <= 100, 'Fertigkeitswert außerhalb des unterstützten Bereichs.');
            }
            $this->ensure(($skills['Intuition'] ?? 0) === 0, 'Daa’muren mit Bildung können keine Intuition erwerben.');
            $this->ensure(($skills['Wissenschaftler'] ?? 0) <= $skills['Bildung'], 'Wissenschaftler darf Bildung nicht übersteigen.');
            $this->ensure(! isset($data['weapon']), 'Diese Vorlage erlaubt keine Waffenauswahl.');
        } else {
            $this->ensure(array_intersect(array_keys($data), ['rank', 'profession', 'skill_increases', 'reason']) === [], 'Rang und FP-Verteilung sind nur für Daa’muren erlaubt.');
            if ($row['key'] === 'bandit') {
                $configuration = ['weapon' => $data['weapon'] ?? 'schwert'];
            } else {
                $this->ensure(! isset($data['weapon']), 'Diese Vorlage erlaubt keine Waffenauswahl.');
            }
        }

        return ['row' => $row, 'custom_name' => $name !== '' ? $name : null, 'configuration' => $configuration,
            'profile' => $this->snapshots->build($row, $configuration)];
    }

    public function create(User $user, array $input): RpgNpc
    {
        Validator::make($input, ['submission_key' => 'required|uuid'])->validate();

        return DB::transaction(function () use ($user, $input) {
            $team = $this->access->requireLeader($user, lock: true);
            $hash = CombatHash::make($input);
            if ($existing = RpgNpc::where('submission_key', $input['submission_key'])->first()) {
                abort_unless($existing->team_id === $team->id && $existing->submission_hash === $hash, 409);

                return $existing;
            }
            $data = $this->preview($user, $input);
            $row = $data['row'];
            $this->ensure(! $row['special'] || ! RpgNpc::where('team_id', $team->id)->where('special_template_key', $row['key'])->exists(), 'Dieser besondere Charakter ist bereits im AG-Bestand.');

            return RpgNpc::create([
                'team_id' => $team->id, 'created_by' => $user->id, 'submission_key' => $input['submission_key'], 'submission_hash' => $hash,
                'template_key' => $row['key'], 'special_template_key' => $row['special'] ? $row['key'] : null,
                'template_version' => RpgNpcCatalog::VERSION, 'rulebook_sha256' => RpgCombatRules::RULEBOOK_SHA256, 'source_page' => $row['page'],
                'custom_name' => $data['custom_name'], 'configuration' => $data['configuration'], 'profile' => $data['profile'], 'profile_hash' => CombatHash::make($data['profile']),
            ]);
        }, 3);
    }

    public function rename(User $user, RpgNpc $npc, array $input): void
    {
        $data = Validator::make($input, ['revision' => 'required|integer|min:0', 'custom_name' => 'nullable|string|max:255'])->validate();
        DB::transaction(function () use ($user, $npc, $data) {
            $this->access->requireLeader($user, lock: true);
            $npc = RpgNpc::lockForUpdate()->findOrFail($npc->id);
            Gate::forUser($user)->authorize('update', $npc);
            $this->ensure($npc->revision === (int) $data['revision'], 'Der NSC wurde zwischenzeitlich geändert.');
            $this->ensure(! DB::table('rpg_combat_npc_locks')->where('rpg_npc_id', $npc->id)->exists(), 'Der NSC befindet sich in einem laufenden Kampf.');
            $npc->update(['custom_name' => trim($data['custom_name'] ?? '') ?: null, 'revision' => $npc->revision + 1]);
        }, 3);
    }

    public function delete(User $user, RpgNpc $npc): void
    {
        DB::transaction(function () use ($user, $npc) {
            $this->access->requireLeader($user, lock: true);
            $npc = RpgNpc::lockForUpdate()->findOrFail($npc->id);
            Gate::forUser($user)->authorize('delete', $npc);
            $busy = RpgCombat::whereIn('status', RpgCombat::OPEN_STATUSES)->whereHas('participants', fn ($q) => $q->where('rpg_npc_id', $npc->id))->exists();
            $this->ensure(! $busy, 'Offene Einladungen zurückziehen beziehungsweise den laufenden Kampf zuerst beenden.');
            $npc->delete();
        }, 3);
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['npc' => $message]);
        }
    }
}
