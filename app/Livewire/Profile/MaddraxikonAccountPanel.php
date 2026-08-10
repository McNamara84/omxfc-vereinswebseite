<?php

namespace App\Livewire\Profile;

use App\Enums\MaddraxikonContributionStatus;
use App\Models\BaxxEarningRule;
use App\Models\MaddraxikonAccountLink;
use App\Models\MaddraxikonContribution;
use App\Models\MaddraxikonRewardEvent;
use App\Models\User;
use App\Services\Maddraxikon\AccountEligibility;
use App\Services\Maddraxikon\MaddraxikonRewardPolicyResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

final class MaddraxikonAccountPanel extends Component
{
    public function render(
        AccountEligibility $eligibility,
        MaddraxikonRewardPolicyResolver $policyResolver,
    ): View {
        /** @var User $user */
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $link = MaddraxikonAccountLink::query()
            ->where('user_id', $user->getKey())
            ->first();

        $contributions = MaddraxikonContribution::query()
            ->where('user_id', $user->getKey())
            ->with(['rewardEvents' => fn ($query) => $query
                ->select([
                    'id',
                    'source_contribution_id',
                    'measured_added_bytes',
                    'matched_minimum_added_bytes',
                    'awarded_points',
                    'status_reason',
                ])
                ->latest('id')])
            ->latest('occurred_at_epoch')
            ->latest('occurred_at')
            ->latest('revision_id')
            ->limit(20)
            ->get();

        $counts = MaddraxikonContribution::query()
            ->where('user_id', $user->getKey())
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn (mixed $count): int => (int) $count);

        $rules = BaxxEarningRule::query()
            ->whereIn('action_key', [
                MaddraxikonRewardEvent::ACTION_EDIT_SESSION,
                MaddraxikonRewardEvent::ACTION_NEW_ARTICLE,
            ])
            ->get()
            ->keyBy('action_key');
        $editRule = $rules->get(MaddraxikonRewardEvent::ACTION_EDIT_SESSION);
        $newArticleRule = $rules->get(MaddraxikonRewardEvent::ACTION_NEW_ARTICLE);
        $currentPolicy = $policyResolver->current();
        $nextPolicy = $policyResolver->next();

        return view('profile.maddraxikon-account-panel', [
            'link' => $link,
            'contributions' => $contributions,
            'counts' => $counts,
            'eligible' => $eligibility->isEligible($user),
            'linkingEnabled' => (bool) config('maddraxikon.features.linking_enabled'),
            'rewardPolicy' => [
                'mode' => $currentPolicy ? 'byte_tier' : 'legacy',
                'evaluation_delay_hours' => max(1, (int) config('maddraxikon.evaluation_delay_hours', 24)),
                'minimum_article_bytes' => $currentPolicy
                    ? max(0, (int) ($currentPolicy->new_article_minimum_bytes ?? 0))
                    : max(0, (int) config('maddraxikon.minimum_article_bytes', 500)),
                'daily_point_cap' => max(0, (int) config('maddraxikon.daily_point_cap', 10)),
                'edit' => [
                    'is_active' => $currentPolicy
                        ? $currentPolicy->edit_sessions_enabled
                        : (bool) $editRule?->is_active,
                    'points' => max(0, (int) ($editRule?->points ?? 0)),
                    'every_count' => max(1, (int) ($editRule?->every_count ?? 1)),
                    'tiers' => $currentPolicy?->tiers
                        ->map(fn ($tier): array => [
                            'minimum_added_bytes' => $tier->minimum_added_bytes,
                            'points' => $tier->points,
                        ])
                        ->values()
                        ->all() ?? [],
                ],
                'new_article' => [
                    'is_active' => $currentPolicy
                        ? $currentPolicy->new_articles_enabled
                        : (bool) $newArticleRule?->is_active,
                    'points' => $currentPolicy
                        ? max(0, (int) ($currentPolicy->new_article_points ?? 0))
                        : max(0, (int) ($newArticleRule?->points ?? 0)),
                    'every_count' => max(1, (int) ($newArticleRule?->every_count ?? 1)),
                ],
                'next' => $nextPolicy ? [
                    'name' => $nextPolicy->name,
                    'effective_from' => $nextPolicy->effective_from,
                ] : null,
            ],
            'statusLabels' => [
                MaddraxikonContributionStatus::Pending->value => 'Ausstehend',
                MaddraxikonContributionStatus::Qualified->value => 'Qualifiziert',
                MaddraxikonContributionStatus::Rejected->value => 'Abgelehnt',
                MaddraxikonContributionStatus::Awarded->value => 'Gutgeschrieben',
            ],
            'statusClasses' => [
                MaddraxikonContributionStatus::Pending->value => 'badge-warning',
                MaddraxikonContributionStatus::Qualified->value => 'badge-info',
                MaddraxikonContributionStatus::Rejected->value => 'badge-error',
                MaddraxikonContributionStatus::Awarded->value => 'badge-success',
            ],
        ]);
    }
}
