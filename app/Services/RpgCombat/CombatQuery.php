<?php

namespace App\Services\RpgCombat;

use App\Models\RpgCombat;
use App\Models\RpgCombatDecision;
use App\Models\User;
use App\Services\RpgAccess;
use App\Support\RpgCombatRules;
use Illuminate\Support\Facades\Gate;

final class CombatQuery
{
    public function listing(User $user)
    {
        $access = app(RpgAccess::class);
        $team = $access->team();
        abort_unless($team && $access->isMember($user->id, $team), 403);

        return RpgCombat::with('participants')->where('team_id', $team->id)
            ->when(! $access->isLeader($user), fn ($q) => $q->whereHas('participants', fn ($p) => $p->where('owner_id', $user->id)));
    }

    public function detail(User $user, RpgCombat $combat, int $before = 0): array
    {
        Gate::forUser($user)->authorize('view', $combat);
        $combat->loadMissing('participants');
        $state = $combat->state;
        $authority = app(CombatAuthority::class);
        $sides = $authority->sides($combat, $user);
        $side = count($sides) === 1 ? $sides[0] : null;
        $decisions = $combat->decisions()->whereIn('status', ['pending', 'paused'])->orderBy('id')->get()->map(function ($d) use ($user, $combat) {
            $allowed = app(CombatAuthority::class)->canDecide($combat, $d, $user);
            $context = $d->context;
            if (! $allowed) {
                unset($context['request']);
            }

            return ['id' => $d->id, 'type' => $d->type, 'side' => $d->side, 'controller' => $d->controller_side, 'status' => $d->status,
                'due_at' => $d->due_at, 'reminder_at' => $d->reminder_at, 'timeout_policy' => $d->timeout_policy,
                'allowed' => $allowed && $d->status === 'pending' && ! $combat->suspension
                    && ($combat->kind !== 'npc_vs_player' || app(CombatAuthority::class)->leader($combat) !== null), 'context' => $context];
        });
        // Never serialize continuation inputs or sealed simultaneous declarations.
        $actors = $state['actors'] ?? [];
        foreach ($actors as &$actor) {
            foreach ($actor['effects'] as &$effect) {
                unset($effect['attack']);
            }
        }
        $events = $combat->events()->when($before > 0, fn ($q) => $q->where('sequence', '<', $before))->orderByDesc('sequence')->limit(100)->get();

        return compact('combat', 'side', 'sides', 'decisions', 'actors', 'events') + ['round' => $state['round'] ?? 0, 'seconds' => $state['seconds'] ?? 0,
            'abort_offer' => $state['abort_offer'] ?? null, 'rules' => $state['rules'] ?? [], 'rulings' => RpgCombatRules::rulings()];
    }

    public function hints(User $user): array
    {
        return $this->listing($user)->whereIn('status', ['challenged', 'preparing', 'active', 'awaiting_ruling'])
            ->where(fn ($q) => $q->whereHas('participants', fn ($p) => $p->where('owner_id', $user->id))
                ->orWhereHas('decisions', fn ($d) => $d->where('type', 'ruling')->where('status', 'pending'))
                ->when(app(RpgAccess::class)->isLeader($user), fn ($q) => $q->orWhere('kind', 'npc_vs_player')))
            ->with(['decisions' => fn ($q) => $q->whereIn('status', ['pending', 'paused'])])
            ->latest('id')->limit(20)->get()->map(function ($combat) use ($user) {
                $sides = app(CombatAuthority::class)->sides($combat, $user);
                $side = in_array(2, $sides, true) ? 2 : ($sides[0] ?? null);
                $tasks = $combat->decisions->filter(fn ($d) => app(CombatAuthority::class)->canDecide($combat, $d, $user))
                    ->map(fn ($d) => ['label' => RpgCombatDecision::typeLabel($d->type), 'due_at' => $d->due_at])->values()->all();
                if ($combat->status === 'challenged') {
                    $tasks[] = ['label' => $side === 2 ? 'Herausforderung annehmen oder ablehnen' : 'Annahme der Herausforderung ausstehend', 'due_at' => $combat->expires_at];
                }

                return ['url' => route('rpg.combats.show', $combat), 'label' => $combat->participants->pluck('character_name')->join(' gegen '),
                    'status' => $combat->status, 'tasks' => $tasks];
            })->all();
    }
}
