<?php

namespace App\Services;

use App\Models\RpgCheck;
use App\Models\Team;
use App\Models\User;
use App\Support\RpgCheckRules;
use Illuminate\Support\Facades\Gate;

class RpgCheckPresenter
{
    public function __construct(private RpgCheckService $service) {}

    public function present(RpgCheck $check, User $viewer, Team $team, ?array $members = null): array
    {
        Gate::forUser($viewer)->authorize('view', $check);
        $leader = $team->user_id === $viewer->id;
        $secret = ! $leader && $check->batch->visibility === 'hidden';
        $own = $check->participants->where('owner_id', $viewer->id);
        $executable = $this->service->executable($check, $team, $members);
        $status = $secret
            ? ($check->status === 'cancelled' ? 'cancelled' : ($own->every(fn ($p) => $p->rolled_at !== null) ? 'rolled' : 'pending'))
            : $check->status;
        $labels = ['pending' => 'Wurf ausstehend', 'rolled' => 'Gewürfelt. Das Ergebnis wurde der Spielleitung übermittelt.',
            'completed' => 'Abgeschlossen', 'awaiting_decision' => 'Gleichstand – Entscheidung der Leitung ausstehend', 'cancelled' => 'Storniert'];
        $data = [
            'id' => $check->id, 'url' => route('rpg.checks.show', $check), 'description' => $check->batch->description,
            'visibility' => $check->batch->visibility, 'mode' => $check->mode, 'status' => $status, 'status_label' => $labels[$status],
            'created_at' => $check->created_at->format('d.m.Y H:i'), 'can_cancel' => $leader && $check->isOpen(),
            'can_resolve' => $leader && $check->status === 'awaiting_decision' && $executable,
            'cancellation_reason' => $check->cancellation_reason,
            'participants' => $check->participants->filter(fn ($p) => ! $secret || $p->owner_id === $viewer->id)->map(function ($p) use ($check, $viewer, $leader, $secret, $executable): array {
                $mine = $p->owner_id === $viewer->id;
                $row = ['id' => $p->id, 'position' => $p->position, 'character_name' => $p->character_name];
                if ($leader || $mine) {
                    $row += ['attribute' => RpgCheckRules::attributes()[$p->attribute_key], 'skill_name' => $p->skill_name,
                        'check_type' => $p->check_type, 'rolled' => $p->rolled_at !== null,
                        'can_roll' => ! $leader && $mine && ! $p->rolled_at && $check->status === 'pending' && $executable,
                        'roll_url' => route('rpg.checks.roll', [$check, $p])];
                    if (! $secret) {
                        $row += ['attribute_value' => $p->attribute_value, 'skill_value' => $p->skill_value,
                            'modifiers' => $p->modifiers, 'modifier_total' => $p->modifier_total, 'base_total' => $p->base_total,
                            'character_revision' => $p->character_revision];
                        if ($p->rolled_at) {
                            $row += ['dice' => [$p->die_one, $p->die_two], 'raw_total' => $p->raw_total, 'total' => $p->total,
                                'result_kind' => $p->result_kind];
                            if ($check->mode === 'fixed' || $leader) {
                                $row['margin'] = $p->margin;
                            }
                        }
                    }
                }

                return $row;
            })->values()->all(),
        ];
        if (! $secret) {
            $data['difficulty'] = $check->difficulty;
            $data['winner_position'] = $check->winner_position;
            $data['resolution_reason'] = $check->resolution_reason;
        }
        if ($leader) {
            $data += ['executable' => $executable, 'comparison_margin' => $check->comparison_margin,
                'rule_version' => $check->batch->rule_version,
                'cancel_url' => route('rpg.checks.cancel', $check), 'resolve_url' => route('rpg.checks.resolve', $check)];
        }

        return $data;
    }
}
