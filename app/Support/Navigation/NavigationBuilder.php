<?php

namespace App\Support\Navigation;

use App\Enums\Role;
use App\Models\User;

class NavigationBuilder
{
    public function build(?User $user, array $context = []): array
    {
        $entries = $user ? config('navigation.auth', []) : config('navigation.guest', []);
        $visibilityState = $this->buildVisibilityState($user);

        $resolvedEntries = $this->resolveEntries($entries, $user, $context, $visibilityState);

        return [
            'featured' => array_values(array_filter(
                $resolvedEntries,
                fn (array $entry): bool => ($entry['layout'] ?? 'section') === 'featured'
            )),
            'sections' => array_values(array_filter(
                $resolvedEntries,
                fn (array $entry): bool => ($entry['layout'] ?? 'section') === 'section'
            )),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $visibilityState
     * @return array<int, array<string, mixed>>
     */
    private function resolveEntries(array $entries, ?User $user, array $context, array $visibilityState): array
    {
        return array_values(array_filter(array_map(
            fn (array $entry): ?array => $this->resolveEntry($entry, $user, $context, $visibilityState),
            $entries,
        )));
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $visibilityState
     * @return array<string, mixed>|null
     */
    private function resolveEntry(array $entry, ?User $user, array $context, array $visibilityState): ?array
    {
        if (! $this->passesVisibility($entry, $user, $context, $visibilityState)) {
            return null;
        }

        if (isset($entry['label_context_key'])) {
            $label = (string) data_get($context, $entry['label_context_key'], '');

            if ($label === '') {
                return null;
            }

            $entry['title'] = $label;
        }

        if (isset($entry['items'])) {
            $items = $this->resolveEntries($entry['items'], $user, $context, $visibilityState);

            if ($items === []) {
                return null;
            }

            return [
                'layout' => $entry['layout'] ?? 'section',
                'title' => $entry['title'],
                'icon' => $entry['icon'] ?? null,
                'items' => $items,
                'active' => collect($items)->contains(fn (array $item): bool => (bool) ($item['active'] ?? false)),
            ];
        }

        return [
            'layout' => $entry['layout'] ?? 'section',
            'title' => $entry['title'],
            'icon' => $entry['icon'] ?? null,
            'href' => isset($entry['href']) ? $entry['href'] : route($entry['route']),
            'active' => $this->isActive($entry['active_patterns'] ?? [$entry['route']]),
            'accent' => (bool) ($entry['accent'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $visibilityState
     */
    private function passesVisibility(array $definition, ?User $user, array $context, array $visibilityState): bool
    {
        if (isset($definition['visible_any'])) {
            $matchesAny = collect($definition['visible_any'])
                ->contains(fn (array $predicate): bool => $this->matchesPredicate($predicate, $user, $context, $visibilityState));

            if (! $matchesAny) {
                return false;
            }
        }

        if (isset($definition['visible_all'])) {
            $matchesAll = collect($definition['visible_all'])
                ->every(fn (array $predicate): bool => $this->matchesPredicate($predicate, $user, $context, $visibilityState));

            if (! $matchesAll) {
                return false;
            }
        }

        return $this->matchesPredicate($definition, $user, $context, $visibilityState);
    }

    /**
     * @param  array<string, mixed>  $predicate
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $visibilityState
     */
    private function matchesPredicate(array $predicate, ?User $user, array $context, array $visibilityState): bool
    {
        if (isset($predicate['visibility_flag']) && ! (bool) data_get($context, $predicate['visibility_flag'], false)) {
            return false;
        }

        if (($predicate['requires_auth'] ?? false) && ! $user) {
            return false;
        }

        if (($predicate['requires_guest'] ?? false) && $user) {
            return false;
        }

        if (($predicate['vorstand'] ?? false) && ! ($visibilityState['has_vorstand_role'] ?? false)) {
            return false;
        }

        if (isset($predicate['roles_any'])) {
            $roles = array_values(array_filter(
                $predicate['roles_any'],
                fn (mixed $role): bool => $role instanceof Role,
            ));
            $currentRole = $visibilityState['current_role'] ?? null;

            if ($roles === [] || ! $currentRole instanceof Role || ! in_array($currentRole, $roles, true)) {
                return false;
            }
        }

        if (isset($predicate['team_any'])) {
            $teamNames = array_filter($predicate['team_any'], 'is_string');
            $memberTeamNames = $visibilityState['team_names'] ?? [];

            if (! $user || $teamNames === [] || ! collect($teamNames)->contains(fn (string $teamName): bool => isset($memberTeamNames[$teamName]))) {
                return false;
            }
        }

        if (($predicate['has_non_personal_team'] ?? false) && ! ($visibilityState['has_non_personal_team'] ?? false)) {
            return false;
        }

        if (($predicate['has_non_personal_owned_team'] ?? false) && ! ($visibilityState['has_non_personal_owned_team'] ?? false)) {
            return false;
        }

        if (isset($predicate['can'])) {
            [$ability, $arguments] = $predicate['can'];

            if (! $user || ! $user->can($ability, $arguments)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildVisibilityState(?User $user): array
    {
        if (! $user) {
            return [
                'current_role' => null,
                'has_vorstand_role' => false,
                'team_names' => [],
                'has_non_personal_team' => false,
                'has_non_personal_owned_team' => false,
            ];
        }

        $user->loadMissing(['teams', 'ownedTeams']);

        $currentRole = Role::tryFrom(
            $user->teams->firstWhere('id', $user->current_team_id)?->membership?->role ?? null,
        );
        $teamNames = array_values(array_filter($user->teams->pluck('name')->all(), 'is_string'));

        return [
            'current_role' => $currentRole,
            'has_vorstand_role' => $currentRole instanceof Role
                && in_array($currentRole, [Role::Admin, Role::Vorstand, Role::Kassenwart], true),
            'team_names' => array_fill_keys($teamNames, true),
            'has_non_personal_team' => $user->teams->contains(fn ($team): bool => ! $team->personal_team),
            'has_non_personal_owned_team' => $user->ownedTeams->contains(fn ($team): bool => ! $team->personal_team),
        ];
    }

    /**
     * @param  array<int, string>  $patterns
     */
    private function isActive(array $patterns): bool
    {
        return request()->routeIs(...$patterns);
    }
}