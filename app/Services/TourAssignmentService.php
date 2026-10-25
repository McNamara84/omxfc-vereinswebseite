<?php

namespace App\Services;

use App\Enums\Role;
use App\Enums\TourAssignmentSource;
use App\Enums\TourAssignmentStatus;
use App\Models\TourAssignment;
use App\Models\TourRewardGrant;
use App\Models\User;
use App\Services\Profile\ProfileTourCompletionService;
use App\Services\Profile\SeriesProfileCompletion;
use App\Support\Tours\TourCompletionResult;
use App\Support\Tours\TourDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class TourAssignmentService
{
    public const SESSION_SUPPRESSION_KEY = 'tour.dismissed_assignments';

    public const SESSION_ACTIVE_KEY = 'tour.active_assignment';

    public function __construct(
        private readonly TourRegistry $tourRegistry,
    ) {}

    /**
     * @return Collection<int, TourAssignment>
     */
    public function assignAutoToursForApprovedMember(User $user, ?User $assignedBy = null): Collection
    {
        return collect($this->tourRegistry->autoAssignableOnMemberApproval())
            ->map(fn ($definition) => $this->assignIfMissing(
                user: $user,
                tourKey: $definition->key,
                source: TourAssignmentSource::System,
                assignedBy: $assignedBy,
            ));
    }

    public function assignIfMissing(
        User $user,
        string $tourKey,
        TourAssignmentSource $source,
        ?User $assignedBy = null,
    ): TourAssignment {
        $definition = $this->tourRegistry->definition($tourKey);

        return TourAssignment::query()->firstOrCreate([
            'user_id' => $user->id,
            'tour_key' => $definition->key,
            'tour_version' => $definition->version,
        ], [
            'status' => TourAssignmentStatus::Pending,
            'assigned_via' => $source,
            'assigned_by_user_id' => $assignedBy?->id,
            'assigned_at' => now(),
            'started_at' => null,
            'completed_at' => null,
            'dismissed_at' => null,
            'next_prompt_at' => null,
            'current_step_key' => null,
            'metadata' => [],
        ]);
    }

    public function reassign(
        User $user,
        string $tourKey,
        TourAssignmentSource $source,
        ?User $assignedBy = null,
    ): TourAssignment {
        $definition = $this->tourRegistry->definition($tourKey);

        return DB::transaction(function () use ($user, $definition, $source, $assignedBy) {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            return TourAssignment::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'tour_key' => $definition->key,
                    'tour_version' => $definition->version,
                ],
                [
                    'status' => TourAssignmentStatus::Pending,
                    'assigned_via' => $source,
                    'assigned_by_user_id' => $assignedBy?->id,
                    'assigned_at' => now(),
                    'started_at' => null,
                    'completed_at' => null,
                    'dismissed_at' => null,
                    'next_prompt_at' => null,
                    'current_step_key' => null,
                    'metadata' => [],
                ],
            );
        });
    }

    public function currentPromptableAssignmentForUser(User $user, array $suppressedAssignmentIds = []): ?TourAssignment
    {
        $memberRole = $user->mitgliederTeamRole();
        $assignments = TourAssignment::query()
            ->where('user_id', $user->id)
            ->when($memberRole === null || $memberRole === Role::Anwaerter, fn ($query) => $query->where('tour_key', '!=', 'profilpflege'))
            ->whereIn('status', [
                TourAssignmentStatus::Pending->value,
                TourAssignmentStatus::InProgress->value,
            ])
            ->when($suppressedAssignmentIds !== [], fn ($query) => $query->whereNotIn('id', $suppressedAssignmentIds))
            ->where(function ($query) {
                $query->whereNull('next_prompt_at')
                    ->orWhere('next_prompt_at', '<=', now());
            })
            ->orderByRaw('case when tour_key = ? then 0 else 1 end', ['profilpflege'])
            ->orderByRaw('case when status = ? then 0 else 1 end', [TourAssignmentStatus::InProgress->value])
            ->orderBy('assigned_at')
            ->orderBy('id')
            ->get();

        foreach ($assignments as $assignment) {
            $active = $this->activeAssignment($assignment);
            if ($active->isPromptDue() && ! in_array($active->id, $suppressedAssignmentIds, true)) {
                return $active;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentPromptablePayloadForUser(User $user, array $suppressedAssignmentIds = []): ?array
    {
        $assignment = $this->currentPromptableAssignmentForUser($user, $suppressedAssignmentIds);

        if (! $assignment) {
            return null;
        }

        return $this->payloadForAssignment($assignment);
    }

    public function start(TourAssignment $assignment): TourAssignment
    {
        return $this->mutateOpenAssignment($assignment, fn (TourAssignment $current) => [
            'status' => TourAssignmentStatus::InProgress,
            'started_at' => $current->started_at ?? now(),
        ]);
    }

    public function rememberProgress(TourAssignment $assignment, string $stepKey): TourAssignment
    {
        if ($assignment->tour_key === 'profilpflege') {
            abort_unless($assignment->tour_version === $this->tourRegistry->definition('profilpflege')->version, 409, 'Bitte starte die aktuelle Profilpflege-Tour.');
        }

        return $this->mutateOpenAssignment($assignment, function (TourAssignment $current) use ($stepKey) {
            if ($current->tour_key === 'profilpflege' && $current->started_at === null) {
                throw ValidationException::withMessages(['tour' => 'Bitte starte zuerst die Tour.']);
            }

            return [
                'status' => TourAssignmentStatus::InProgress,
                'started_at' => $current->started_at ?? now(),
                'current_step_key' => $stepKey,
            ];
        });
    }

    public function dismiss(TourAssignment $assignment): TourAssignment
    {
        return $this->mutateOpenAssignment($assignment, fn () => ['dismissed_at' => now()]);
    }

    private function mutateOpenAssignment(TourAssignment $assignment, callable $attributes): TourAssignment
    {
        $assignment = $this->activeAssignment($assignment);

        return DB::transaction(function () use ($assignment, $attributes) {
            $current = TourAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            if ($current->status !== TourAssignmentStatus::Completed) {
                $current->forceFill($attributes($current))->save();
            }

            return $current;
        });
    }

    public function complete(TourAssignment $assignment): TourCompletionResult
    {
        if ($assignment->tour_key === 'profilpflege') {
            return app(ProfileTourCompletionService::class)->complete($assignment);
        }

        $assignment = $this->activeAssignment($assignment);

        if ($assignment->status === TourAssignmentStatus::Completed) {
            return new TourCompletionResult($assignment);
        }

        $assignment->forceFill([
            'status' => TourAssignmentStatus::Completed,
            'completed_at' => now(),
            'dismissed_at' => null,
            'next_prompt_at' => null,
        ])->save();

        return new TourCompletionResult($assignment->fresh());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function selfServiceOverviewForUser(User $user): Collection
    {
        $assignments = TourAssignment::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy(fn (TourAssignment $assignment): string => $this->assignmentMapKey($assignment->tour_key, $assignment->tour_version));

        return collect($this->tourRegistry->selfServiceEnabled())
            ->map(function (TourDefinition $definition) use ($assignments, $user): array {
                $assignment = $assignments->get($this->assignmentMapKey($definition->key, $definition->version));

                return [
                    'definition' => $definition,
                    'assignment' => $assignment,
                    'status' => $assignment?->status,
                    'is_completed' => $assignment?->status === TourAssignmentStatus::Completed,
                    'is_open' => $assignment?->status?->isOpen() ?? false,
                    'reward_received' => $definition->key === 'profilpflege' && $this->profileRewardReceived($user->id),
                ];
            })
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadForAssignment(TourAssignment $assignment): array
    {
        $assignment = $this->activeAssignment($assignment);
        $definition = $this->tourRegistry->definition($assignment->tour_key);
        $stepKeys = collect($definition->steps)
            ->pluck('key')
            ->filter(fn (mixed $value): bool => is_string($value) && $value !== '')
            ->values();
        $currentStepIndex = $stepKeys->search($assignment->current_step_key);

        return [
            'assignment_id' => $assignment->id,
            'status' => $assignment->status->value,
            'current_step_key' => $assignment->current_step_key,
            'current_step_index' => $currentStepIndex === false ? 0 : $currentStepIndex,
            'key' => $definition->key,
            'version' => $definition->version,
            'title' => $definition->title,
            'description' => $definition->description,
            'steps' => $definition->steps,
            'start_mode' => $definition->startMode,
            'entry_url' => $definition->entryRoute ? route($definition->entryRoute) : null,
            ...($definition->key === 'profilpflege' ? [
                'profile' => app(SeriesProfileCompletion::class)->forUser($assignment->user()->firstOrFail()),
                'reward_received' => $this->profileRewardReceived($assignment->user_id),
                'reward_points' => ProfileTourCompletionService::POINTS,
            ] : []),
        ];
    }

    private function profileRewardReceived(int $userId): bool
    {
        return Schema::hasTable('tour_reward_grants') && TourRewardGrant::query()
            ->where('user_id', $userId)->where('reward_key', ProfileTourCompletionService::REWARD_KEY)->exists();
    }

    private function assignmentMapKey(string $tourKey, int $tourVersion): string
    {
        return $tourKey.':'.$tourVersion;
    }

    public function activeAssignment(TourAssignment $assignment): TourAssignment
    {
        $definition = $this->tourRegistry->definition($assignment->tour_key);

        if ($assignment->tour_version >= $definition->version) {
            return $assignment->fresh() ?? $assignment;
        }

        $metadata = $assignment->metadata;
        $metadata['superseded_by_version'] = $definition->version;

        $assignment->forceFill([
            'status' => TourAssignmentStatus::Completed,
            'completed_at' => $assignment->completed_at ?? now(),
            'dismissed_at' => null,
            'next_prompt_at' => null,
            'metadata' => $metadata,
        ])->save();

        return $this->assignIfMissing(
            user: $assignment->user,
            tourKey: $assignment->tour_key,
            source: $assignment->assigned_via,
            assignedBy: $assignment->assignedBy,
        );
    }
}
