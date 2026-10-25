<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Enums\TourAssignmentSource;
use App\Models\TourAssignment;
use App\Services\TourAssignmentService;
use App\Services\TourRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TourController extends Controller
{
    public function __construct(
        private readonly TourAssignmentService $tourAssignmentService,
        private readonly TourRegistry $tourRegistry,
    ) {}

    public function current(Request $request): JsonResponse
    {
        $payload = $this->tourAssignmentService->currentPromptablePayloadForUser(
            $request->user(), $this->suppressedAssignmentIds($request),
        );
        if ($payload !== null) {
            $payload['active_in_session'] = $request->session()->get(TourAssignmentService::SESSION_ACTIVE_KEY) === $payload['assignment_id'];
        }

        return response()->json([
            'tour' => $payload,
        ]);
    }

    public function start(Request $request, TourAssignment $tourAssignment): JsonResponse
    {
        $this->ensureOwnAssignment($request, $tourAssignment);

        $assignment = $this->tourAssignmentService->start($tourAssignment);
        $this->activateInSession($request, $assignment);

        return response()->json([
            'tour' => $this->tourAssignmentService->payloadForAssignment(
                $assignment,
            ),
        ]);
    }

    public function progress(Request $request, TourAssignment $tourAssignment): JsonResponse
    {
        $this->ensureOwnAssignment($request, $tourAssignment);

        $validated = $request->validate([
            'step_key' => ['required', 'string', 'max:255', Rule::in($this->stepKeysForAssignment($tourAssignment))],
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
        $request->session()->forget(TourAssignmentService::SESSION_ACTIVE_KEY);

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
        $request->session()->forget(TourAssignmentService::SESSION_ACTIVE_KEY);

        return response()->json([
            'completed' => true,
            'reward' => $assignment->getAttribute('reward_result'),
        ]);
    }

    public function resume(Request $request, TourAssignment $tourAssignment): RedirectResponse
    {
        $this->ensureOwnAssignment($request, $tourAssignment);
        $assignment = $this->tourAssignmentService->start($tourAssignment);
        $this->activateInSession($request, $assignment);

        return redirect()->route('profile.show');
    }

    private function activateInSession(Request $request, TourAssignment $assignment): void
    {
        $request->session()->put(TourAssignmentService::SESSION_ACTIVE_KEY, $assignment->id);
        $request->session()->put(TourAssignmentService::SESSION_SUPPRESSION_KEY, array_values(array_filter(
            $this->suppressedAssignmentIds($request), fn (int $id) => $id !== $assignment->id,
        )));
    }

    public function restart(Request $request, string $tourKey): RedirectResponse
    {
        $validated = Validator::make(
            ['tour_key' => $tourKey],
            ['tour_key' => ['required', 'string', Rule::in($this->selfServiceTourKeys())]],
        )->validate();

        $assignment = $this->tourAssignmentService->reassign(
            user: $request->user(),
            tourKey: $validated['tour_key'],
            source: TourAssignmentSource::SelfService,
        );

        $suppressedAssignmentIds = array_values(array_filter(
            $this->suppressedAssignmentIds($request),
            static fn (int $assignmentId): bool => $assignmentId !== $assignment->id,
        ));

        $request->session()->put(TourAssignmentService::SESSION_SUPPRESSION_KEY, $suppressedAssignmentIds);
        $request->session()->forget(TourAssignmentService::SESSION_ACTIVE_KEY);

        return back()->with('status', 'Tour neu zugewiesen.');
    }

    private function ensureOwnAssignment(Request $request, TourAssignment $tourAssignment): void
    {
        abort_unless($request->user()->id === $tourAssignment->user_id, 403);
        if ($tourAssignment->tour_key === 'profilpflege') {
            $role = $request->user()->mitgliederTeamRole();
            abort_unless($role !== null && $role !== Role::Anwaerter, 403);
        }
    }

    /**
     * @return array<int, string>
     */
    private function selfServiceTourKeys(): array
    {
        return collect($this->tourRegistry->selfServiceEnabled())
            ->pluck('key')
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function stepKeysForAssignment(TourAssignment $tourAssignment): array
    {
        return collect($this->tourRegistry->definition($tourAssignment->tour_key)->steps)
            ->pluck('key')
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();
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
