<?php

namespace App\Http\Controllers;

use App\Enums\TourAssignmentSource;
use App\Models\TourAssignment;
use App\Services\TourAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TourController extends Controller
{
    public function __construct(
        private readonly TourAssignmentService $tourAssignmentService,
    ) {}

    public function current(Request $request): JsonResponse
    {
        return response()->json([
            'tour' => $this->tourAssignmentService->currentPromptablePayloadForUser(
                $request->user(),
                $this->suppressedAssignmentIds($request),
            ),
        ]);
    }

    public function start(Request $request, TourAssignment $tourAssignment): JsonResponse
    {
        $this->ensureOwnAssignment($request, $tourAssignment);

        return response()->json([
            'tour' => $this->tourAssignmentService->payloadForAssignment(
                $this->tourAssignmentService->start($tourAssignment),
            ),
        ]);
    }

    public function progress(Request $request, TourAssignment $tourAssignment): JsonResponse
    {
        $this->ensureOwnAssignment($request, $tourAssignment);

        $validated = $request->validate([
            'step_key' => ['required', 'string', 'max:255'],
        ]);

        return response()->json([
            'tour' => $this->tourAssignmentService->payloadForAssignment(
                $this->tourAssignmentService->rememberProgress($tourAssignment, $validated['step_key']),
            ),
        ]);
    }

    public function dismiss(Request $request, TourAssignment $tourAssignment): JsonResponse
    {
        $this->ensureOwnAssignment($request, $tourAssignment);

        $assignment = $this->tourAssignmentService->dismiss($tourAssignment);
        $suppressedAssignmentIds = $this->suppressedAssignmentIds($request);

        if (! in_array($assignment->id, $suppressedAssignmentIds, true)) {
            $suppressedAssignmentIds[] = $assignment->id;
        }

        $request->session()->put(TourAssignmentService::SESSION_SUPPRESSION_KEY, $suppressedAssignmentIds);

        return response()->json([
            'suppressed' => true,
        ]);
    }

    public function complete(Request $request, TourAssignment $tourAssignment): JsonResponse
    {
        $this->ensureOwnAssignment($request, $tourAssignment);

        $assignment = $this->tourAssignmentService->complete($tourAssignment);
        $suppressedAssignmentIds = array_values(array_filter(
            $this->suppressedAssignmentIds($request),
            static fn (int $assignmentId): bool => $assignmentId !== $assignment->id,
        ));

        $request->session()->put(TourAssignmentService::SESSION_SUPPRESSION_KEY, $suppressedAssignmentIds);

        return response()->json([
            'completed' => true,
        ]);
    }

    public function restart(Request $request, string $tourKey): RedirectResponse
    {
        $assignment = $this->tourAssignmentService->reassign(
            user: $request->user(),
            tourKey: $tourKey,
            source: TourAssignmentSource::SelfService,
        );

        $suppressedAssignmentIds = array_values(array_filter(
            $this->suppressedAssignmentIds($request),
            static fn (int $assignmentId): bool => $assignmentId !== $assignment->id,
        ));

        $request->session()->put(TourAssignmentService::SESSION_SUPPRESSION_KEY, $suppressedAssignmentIds);

        return back()->with('status', 'Tour neu zugewiesen.');
    }

    private function ensureOwnAssignment(Request $request, TourAssignment $tourAssignment): void
    {
        abort_unless($request->user()->id === $tourAssignment->user_id, 403);
    }

    /**
     * @return array<int, int>
     */
    private function suppressedAssignmentIds(Request $request): array
    {
        return array_values(array_filter(
            $request->session()->get(TourAssignmentService::SESSION_SUPPRESSION_KEY, []),
            static fn (mixed $assignmentId): bool => is_int($assignmentId),
        ));
    }
}