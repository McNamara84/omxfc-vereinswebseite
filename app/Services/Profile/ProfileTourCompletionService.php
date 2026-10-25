<?php

namespace App\Services\Profile;

use App\Enums\TourAssignmentStatus;
use App\Models\TourAssignment;
use App\Models\TourRewardGrant;
use App\Services\Baxx\BaxxBookingWriter;
use App\Services\LockedMembersTeamMemberships;
use App\Services\MembersTeamMembershipLock;
use App\Services\TourRegistry;
use App\Support\Tours\TourCompletionResult;
use Illuminate\Validation\ValidationException;

final class ProfileTourCompletionService
{
    public const REWARD_KEY = 'profilpflege_seriendaten';

    public const POINTS = 5;

    public function __construct(
        private readonly MembersTeamMembershipLock $membershipLock,
        private readonly SeriesProfileCompletion $completion,
        private readonly BaxxBookingWriter $writer,
        private readonly TourRegistry $registry,
    ) {}

    public function complete(TourAssignment $assignment): TourCompletionResult
    {
        return $this->membershipLock->run([$assignment->user_id], function (LockedMembersTeamMemberships $memberships) use ($assignment): TourCompletionResult {
            abort_unless($memberships->isActiveMember($assignment->user_id), 403);
            $assignment = TourAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
            abort_unless($assignment->tour_key === 'profilpflege', 409);
            abort_unless($assignment->tour_version === $this->registry->definition('profilpflege')->version, 409, 'Diese Tour wurde überarbeitet. Bitte starte die aktuelle Profilpflege-Tour.');

            if ($assignment->status === TourAssignmentStatus::Completed) {
                return $this->withResult($assignment, 0);
            }

            if ($assignment->started_at === null || $assignment->current_step_key !== 'profile-complete') {
                throw ValidationException::withMessages(['tour' => 'Bitte starte die Tour und gehe bis zum Abschlussschritt.']);
            }

            $profile = $this->completion->forUser($memberships->user($assignment->user_id));
            if ($profile['missing'] !== []) {
                throw ValidationException::withMessages(array_map(
                    fn ($label) => $label.': Bitte ergänzen und speichern.',
                    $profile['missing'],
                ));
            }

            $grant = TourRewardGrant::query()->where('user_id', $assignment->user_id)
                ->where('reward_key', self::REWARD_KEY)->lockForUpdate()->first();
            $awarded = 0;
            if (! $grant) {
                $booking = $this->writer->record($assignment->user_id, $memberships->team->id, self::POINTS);
                TourRewardGrant::create([
                    'user_id' => $assignment->user_id,
                    'reward_key' => self::REWARD_KEY,
                    'tour_assignment_id' => $assignment->id,
                    'tour_key' => $assignment->tour_key,
                    'tour_version' => $assignment->tour_version,
                    'user_point_id' => $booking->id,
                    'points' => self::POINTS,
                    'awarded_at' => now(),
                ]);
                $awarded = self::POINTS;
            }

            $assignment->forceFill([
                'status' => TourAssignmentStatus::Completed,
                'completed_at' => now(), 'dismissed_at' => null, 'next_prompt_at' => null,
            ])->save();

            return $this->withResult($assignment, $awarded);
        });
    }

    private function withResult(TourAssignment $assignment, int $points): TourCompletionResult
    {
        return new TourCompletionResult($assignment, [
            'status' => $points > 0 ? 'awarded' : 'already_awarded',
            'points_awarded' => $points,
        ]);
    }
}
