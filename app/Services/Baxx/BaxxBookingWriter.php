<?php

namespace App\Services\Baxx;

use App\Models\Todo;
use App\Models\UserPoint;
use Carbon\CarbonInterface;

final class BaxxBookingWriter
{
    /** Source services own eligibility, idempotency and the surrounding transaction. */
    public function record(int $userId, int $teamId, int $points, array $references = [], ?CarbonInterface $occurredAt = null): UserPoint
    {
        $booking = new UserPoint([
            ...array_intersect_key($references, array_flip(['todo_id', 'veranstaltung_id'])),
            'user_id' => $userId,
            'team_id' => $teamId,
            'points' => $points,
        ]);

        if ($occurredAt !== null) {
            $booking->created_at = $occurredAt;
            $booking->updated_at = $occurredAt;
        }

        $booking->save();

        return $booking;
    }

    public function forTodo(Todo $todo): UserPoint
    {
        return UserPoint::query()->updateOrCreate(['todo_id' => $todo->id], [
            'user_id' => $todo->assigned_to,
            'team_id' => $todo->team_id,
            'points' => $todo->points,
        ]);
    }
}
