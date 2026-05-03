<?php

namespace App\Console\Commands;

use App\Models\RewardPurchase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RepairLegacyRewardWallets extends Command
{
    protected $signature = 'rewards:repair-legacy-wallets
        {--apply : Persistiert Änderungen statt nur einen Bericht auszugeben}
        {--user=* : User-ID oder E-Mail-Adresse für eine gezielte Zuordnung nach manueller Prüfung}';

    protected $description = 'Analysiert Legacy-Reward-Käufe ohne Wallet-Zuordnung und weist sie dem Mitglieder-Team zu, wenn der Fall sicher oder manuell geprüft ist.';

    public function handle(): int
    {
        $walletTeam = Team::membersTeam();

        if (! $walletTeam) {
            $this->error('Mitglieder-Team nicht gefunden. Legacy-Käufe können nicht zugeordnet werden.');

            return self::FAILURE;
        }

        $identifiers = collect($this->option('user'))
            ->map(fn (mixed $identifier) => is_scalar($identifier) ? trim((string) $identifier) : '')
            ->filter()
            ->values();

        if ($identifiers->isNotEmpty()) {
            return $this->handleTargetUsers($walletTeam, $identifiers);
        }

        return $this->handleSafeUsers($walletTeam);
    }

    private function handleSafeUsers(Team $walletTeam): int
    {
        $safeUserIds = $this->resolvableLegacyUserIds($walletTeam);
        $safePurchaseCount = $safeUserIds->isEmpty()
            ? 0
            : $this->legacyPurchasesQuery()->whereIn('user_id', $safeUserIds)->count();

        if ($safePurchaseCount === 0) {
            $this->info('Keine automatisch reparierbaren Legacy-Käufe gefunden.');
        } elseif ($this->option('apply')) {
            $updatedPurchases = $this->assignWalletTeam($walletTeam, $safeUserIds);
            $this->info("{$updatedPurchases} Legacy-Käufe wurden dem Mitglieder-Team zugeordnet.");
        } else {
            $this->info("{$safePurchaseCount} Legacy-Käufe können automatisch dem Mitglieder-Team zugeordnet werden.");
            $this->line('Mit --apply werden diese sicheren Fälle direkt repariert.');
        }

        $ambiguousUsers = $this->ambiguousLegacyUsers($safeUserIds);

        if ($ambiguousUsers->isNotEmpty()) {
            $legacyCounts = $this->legacyPurchaseCountsForUsers($ambiguousUsers->pluck('id'));

            $this->table(
                ['User-ID', 'E-Mail', 'Legacy-Käufe', 'Nicht-persönliche Teams'],
                $ambiguousUsers->map(fn (User $user) => [
                    $user->id,
                    $user->email,
                    $legacyCounts[$user->id] ?? 0,
                    $this->formatTeamNames($user),
                ])->all(),
            );

            $this->warn('Mehrdeutige Legacy-Fälle bleiben absichtlich unverändert. Nach manueller Prüfung kannst du sie mit --user=<id|email> --apply dem Mitglieder-Team zuordnen.');
        }

        return self::SUCCESS;
    }

    private function handleTargetUsers(Team $walletTeam, Collection $identifiers): int
    {
        $resolvedUsers = collect();
        $unresolvedIdentifiers = [];

        foreach ($identifiers as $identifier) {
            $user = ctype_digit($identifier)
                ? User::query()->with('teams')->find((int) $identifier)
                : User::query()->with('teams')->where('email', $identifier)->first();

            if ($user) {
                $resolvedUsers->put($user->id, $user);

                continue;
            }

            $unresolvedIdentifiers[] = $identifier;
        }

        foreach ($unresolvedIdentifiers as $identifier) {
            $this->warn("Kein User für '{$identifier}' gefunden.");
        }

        if ($resolvedUsers->isEmpty()) {
            $this->info('Keine gültigen Ziel-User ausgewählt.');

            return self::SUCCESS;
        }

        $eligibleUsers = $resolvedUsers->filter(
            fn (User $user) => $user->teams->contains('id', $walletTeam->id)
        );

        foreach ($resolvedUsers->except($eligibleUsers->keys()) as $user) {
            $this->warn("User {$user->id} ({$user->email}) ist nicht Mitglied im Mitglieder-Team und wird übersprungen.");
        }

        if ($eligibleUsers->isEmpty()) {
            $this->info('Keine ausgewählten User können dem Mitglieder-Team zugeordnet werden.');

            return self::SUCCESS;
        }

        $legacyCounts = $this->legacyPurchaseCountsForUsers($eligibleUsers->pluck('id'));

        $this->table(
            ['User-ID', 'E-Mail', 'Legacy-Käufe', 'Teams'],
            $eligibleUsers->map(fn (User $user) => [
                $user->id,
                $user->email,
                $legacyCounts[$user->id] ?? 0,
                $this->formatTeamNames($user),
            ])->values()->all(),
        );

        if (! $this->option('apply')) {
            $this->line('Mit --apply wird die Zuordnung für diese User gespeichert.');

            return self::SUCCESS;
        }

        $updatedPurchases = $this->assignWalletTeam($walletTeam, $eligibleUsers->pluck('id'));

        $this->info("{$updatedPurchases} Legacy-Käufe wurden dem Mitglieder-Team zugeordnet.");

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, int>
     */
    private function resolvableLegacyUserIds(Team $walletTeam): Collection
    {
        return DB::table('team_user')
            ->join('teams', 'teams.id', '=', 'team_user.team_id')
            ->where('teams.personal_team', false)
            ->groupBy('team_user.user_id')
            ->havingRaw('COUNT(*) = 1')
            ->havingRaw('MIN(team_user.team_id) = ?', [$walletTeam->id])
            ->pluck('team_user.user_id')
            ->map(fn ($userId) => (int) $userId);
    }

    private function ambiguousLegacyUsers(Collection $safeUserIds): Collection
    {
        $legacyUserIds = $this->legacyPurchasesQuery()
            ->select('user_id')
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($userId) => (int) $userId);

        if ($legacyUserIds->isEmpty()) {
            return collect();
        }

        return User::query()
            ->with(['teams' => fn ($query) => $query->where('personal_team', false)->orderBy('name')])
            ->whereIn('id', $legacyUserIds)
            ->when($safeUserIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $safeUserIds))
            ->orderBy('id')
            ->get();
    }

    private function legacyPurchasesQuery()
    {
        return RewardPurchase::query()
            ->active()
            ->whereNull('wallet_team_id');
    }

    private function assignWalletTeam(Team $walletTeam, Collection $userIds): int
    {
        if ($userIds->isEmpty()) {
            return 0;
        }

        $timestamp = now();

        return $this->legacyPurchasesQuery()
            ->whereIn('user_id', $userIds)
            ->update([
                'wallet_team_id' => $walletTeam->id,
                'updated_at' => $timestamp,
            ]);
    }

    /**
     * @return Collection<int, int>
     */
    private function legacyPurchaseCountsForUsers(Collection $userIds): Collection
    {
        if ($userIds->isEmpty()) {
            return collect();
        }

        return $this->legacyPurchasesQuery()
            ->whereIn('user_id', $userIds)
            ->selectRaw('user_id, COUNT(*) as purchase_count')
            ->groupBy('user_id')
            ->pluck('purchase_count', 'user_id')
            ->map(fn ($count) => (int) $count);
    }

    private function formatTeamNames(User $user): string
    {
        $teamNames = $user->teams
            ->filter(fn (Team $team) => ! $team->personal_team)
            ->pluck('name')
            ->unique()
            ->values();

        return $teamNames->isEmpty() ? '—' : $teamNames->implode(', ');
    }
}
