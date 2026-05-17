<?php

namespace App\Services;

use App\Enums\TourAssignmentSource;
use App\Enums\TourAssignmentStatus;
use App\Models\TourAssignment;
use App\Models\User;
use App\Support\Tours\TourDefinition;
use Illuminate\Support\Collection;

class TourAssignmentService
{
    public const SESSION_SUPPRESSION_KEY = 'tour.dismissed_assignments';

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

        $existingAssignment = TourAssignment::query()
            ->where('user_id', $user->id)
            ->where('tour_key', $definition->key)
            ->where('tour_version', $definition->version)
            ->first();

        if ($existingAssignment) {
            return $existingAssignment;
        }

        return TourAssignment::query()->create([
            'user_id' => $user->id,
            'tour_key' => $definition->key,
            'tour_version' => $definition->version,
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
    }

    public function currentPromptableAssignmentForUser(User $user, array $suppressedAssignmentIds = []): ?TourAssignment
    {
        return TourAssignment::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                TourAssignmentStatus::Pending->value,
                TourAssignmentStatus::InProgress->value,
            ])
            ->when($suppressedAssignmentIds !== [], fn ($query) => $query->whereNotIn('id', $suppressedAssignmentIds))
            ->where(function ($query) {
                $query->whereNull('next_prompt_at')
                    ->orWhere('next_prompt_at', '<=', now());
            })
            ->orderByRaw("case when status = ? then 0 else 1 end", [TourAssignmentStatus::InProgress->value])
            ->orderBy('assigned_at')
            ->first();
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
        if ($assignment->status === TourAssignmentStatus::Completed) {
            return $assignment;
        }

        $assignment->forceFill([
            'status' => TourAssignmentStatus::InProgress,
            'started_at' => $assignment->started_at ?? now(),
        ])->save();

        return $assignment->fresh();
    }

    public function rememberProgress(TourAssignment $assignment, string $stepKey): TourAssignment
    {
        if ($assignment->status === TourAssignmentStatus::Completed) {
            return $assignment;
        }

        $assignment->forceFill([
            'status' => TourAssignmentStatus::InProgress,
            'started_at' => $assignment->started_at ?? now(),
            'current_step_key' => $stepKey,
        ])->save();

        return $assignment->fresh();
    }

    public function dismiss(TourAssignment $assignment): TourAssignment
    {
        if ($assignment->status === TourAssignmentStatus::Completed) {
            return $assignment;
        }

        $assignment->forceFill([
            'dismissed_at' => now(),
        ])->save();

        return $assignment->fresh();
    }

    public function complete(TourAssignment $assignment): TourAssignment
    {
        $assignment->forceFill([
            'status' => TourAssignmentStatus::Completed,
            'completed_at' => now(),
            'dismissed_at' => null,
            'next_prompt_at' => null,
        ])->save();

        return $assignment->fresh();
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
            ->map(function (TourDefinition $definition) use ($assignments): array {
                $assignment = $assignments->get($this->assignmentMapKey($definition->key, $definition->version));

                return [
                    'definition' => $definition,
                    'assignment' => $assignment,
                    'status' => $assignment?->status,
                    'is_completed' => $assignment?->status === TourAssignmentStatus::Completed,
                    'is_open' => $assignment?->status?->isOpen() ?? false,
                ];
            })
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function payloadForAssignment(TourAssignment $assignment): array
    {
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
        ];
    }

    private function assignmentMapKey(string $tourKey, int $tourVersion): string
    {
        return $tourKey.':'.$tourVersion;
    }
}