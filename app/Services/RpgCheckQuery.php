<?php

namespace App\Services;

use App\Models\RpgCheck;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class RpgCheckQuery
{
    public function __construct(private RpgAccess $access, private RpgCheckPresenter $presenter) {}

    public function forUser(User $user): Builder
    {
        $team = $this->access->team();
        abort_unless($team && $this->access->isMember($user->id, $team), 403);

        return RpgCheck::query()->whereHas('batch', fn ($q) => $q->where('team_id', $team->id))
            ->when($team->user_id !== $user->id, fn ($q) => $q->whereHas('participants', fn ($p) => $p->where('owner_id', $user->id)))
            ->with(['batch', 'participants.character']);
    }

    public function listing(User $user, array $filters = [], bool $hints = false): array
    {
        $query = $this->forUser($user);
        $team = $this->access->team();
        $leader = $team->user_id === $user->id;
        $tab = $filters['tab'] ?? 'open';
        if ($tab === 'open' || $hints) {
            if ($leader) {
                $query->whereIn('status', ['pending', 'awaiting_decision']);
            } else {
                // Do not expose a hidden tie through list membership or counts.
                $query->where('status', '!=', 'cancelled')->whereHas('participants', fn ($q) => $q->where('owner_id', $user->id)->whereNull('rolled_at'));
            }
        } elseif (! $leader) {
            $query->where(function ($q) use ($user): void {
                $q->where('status', 'cancelled')->orWhereDoesntHave('participants', fn ($p) => $p->where('owner_id', $user->id)->whereNull('rolled_at'));
            });
        } else {
            $query->whereIn('status', ['completed', 'cancelled']);
        }
        foreach (['mode', 'visibility', 'character_id'] as $key) {
            if (filled($filters[$key] ?? null)) {
                match ($key) {
                    'visibility' => $query->whereHas('batch', fn ($q) => $q->where('visibility', $filters[$key])),
                    'character_id' => $query->whereHas('participants', fn ($q) => $q->where('rpg_character_id', $filters[$key])->when(! $leader, fn ($p) => $p->where('owner_id', $user->id))),
                    default => $query->where($key, $filters[$key]),
                };
            }
        }
        $members = $team->users()->pluck('users.id')->all();
        $page = $query->latest('id')->paginate($hints ? 5 : 15, ['*'], 'page', max(1, (int) ($filters['page'] ?? 1)));
        $present = fn ($check) => $this->presenter->present($check, $user, $team, $members);
        $result = ['checks' => $page->getCollection()->map($present)->all(), 'page' => $page->currentPage(),
            'last_page' => $page->lastPage(), 'total' => $page->total(), 'leader' => $leader];
        if ($hints && $leader) {
            $result['recent'] = $this->forUser($user)->where('status', 'completed')->orderByDesc('completed_at')->orderByDesc('id')->limit(5)->get()->map($present)->all();
        }

        return $result;
    }

    public function detail(User $user, RpgCheck $check): array
    {
        $check = $this->forUser($user)->findOrFail($check->id);

        return $this->presenter->present($check, $user, $this->access->team());
    }
}
