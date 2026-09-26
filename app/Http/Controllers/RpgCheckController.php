<?php

namespace App\Http\Controllers;

use App\Models\RpgCharacter;
use App\Models\RpgCheck;
use App\Models\RpgCheckParticipant;
use App\Services\RpgAccess;
use App\Services\RpgCheckQuery;
use App\Services\RpgCheckService;
use App\Support\RpgCheckRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RpgCheckController extends Controller
{
    public function __construct(private RpgCheckService $service, private RpgCheckQuery $query, private RpgAccess $access) {}

    public function index(Request $request)
    {
        $filters = $request->validate(['tab' => ['sometimes', Rule::in(['open', 'history'])],
            'mode' => ['nullable', Rule::in(['fixed', 'opposed'])], 'visibility' => ['nullable', Rule::in(['open', 'hidden'])],
            'character_id' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1']]);
        $data = $this->query->listing($request->user(), $filters);
        if ($request->expectsJson()) {
            return $this->json($data);
        }

        return $this->page('rpg.checks.index', ['data' => $data, 'filters' => $filters, 'characters' => $this->characters($request)]);
    }

    public function create(Request $request)
    {
        $this->authorize('manage-rpg-checks');

        return $this->page('rpg.checks.create', ['config' => [
            'characters' => $this->characters($request, true), 'attributes' => RpgCheckRules::attributes(),
            'difficulties' => RpgCheckRules::DIFFICULTIES, 'skills' => RpgCheckRules::skills(),
            'submission_key' => (string) Str::uuid(), 'preview_url' => route('rpg.checks.preview'), 'save_url' => route('rpg.checks.store'),
        ]]);
    }

    private function characters(Request $request, bool $create = false): array
    {
        $team = $this->access->team();
        $leader = $this->access->isLeader($request->user());

        return RpgCharacter::with('user')->whereIn('user_id', $team->activeUsers()->select('users.id'))
            ->when($create, fn ($q) => $q->where('user_id', '!=', $request->user()->id))
            ->when(! $leader, fn ($q) => $q->where('user_id', $request->user()->id))
            ->orderBy('character_name')->get()->map(fn ($character) => [
                'id' => $character->id, 'label' => $character->displayName().' ('.$character->user->nicknameOrName().')',
                ...($create ? ['skills' => RpgCheckRules::skills($character->payload)] : []),
            ])->all();
    }

    public function preview(Request $request)
    {
        return $this->json($this->service->preview($request->user(), $request->except('_token')));
    }

    public function store(Request $request)
    {
        $batch = $this->service->create($request->user(), $request->except('_token'));

        return $this->json(['redirect' => route('rpg.checks.index'), 'check_ids' => $batch->checks()->pluck('id')]);
    }

    public function show(Request $request, RpgCheck $check)
    {
        $data = $this->query->detail($request->user(), $check);

        return $request->expectsJson() ? $this->json($data) : $this->page('rpg.checks.show', ['data' => $data]);
    }

    public function hints(Request $request)
    {
        return $this->json($this->query->listing($request->user(), hints: true));
    }

    public function roll(Request $request, RpgCheck $check, RpgCheckParticipant $participant)
    {
        abort_unless($participant->rpg_check_id === $check->id, 404);
        $this->input($request, [], []);
        $updated = $this->service->roll($request->user(), $check->id, $participant->id);

        return $this->json($this->query->detail($request->user(), $updated));
    }

    public function cancel(Request $request, RpgCheck $check)
    {
        $data = $this->input($request, ['reason'], ['reason' => ['required', 'string', 'max:2000']]);
        $updated = $this->service->cancel($request->user(), $check->id, $data['reason']);

        return $this->json($this->query->detail($request->user(), $updated));
    }

    public function resolve(Request $request, RpgCheck $check)
    {
        $data = $this->input($request, ['reason', 'resolution'], [
            'reason' => ['required', 'string', 'max:2000'], 'resolution' => ['required', Rule::in(['side_1', 'side_2', 'neutral'])],
        ]);
        $updated = $this->service->resolve($request->user(), $check->id, $data['resolution'], $data['reason']);

        return $this->json($this->query->detail($request->user(), $updated));
    }

    private function input(Request $request, array $keys, array $rules): array
    {
        $input = $request->except('_token');
        abort_if(array_diff(array_keys($input), $keys) !== [], 422, 'Unerlaubte Eingaben.');

        return Validator::make($input, $rules)->validate();
    }

    private function json(array $data)
    {
        return response()->json($data)->header('Cache-Control', 'private, no-store')->header('Vary', 'Accept');
    }

    private function page(string $view, array $data)
    {
        return response()->view($view, $data)->header('Cache-Control', 'private, no-store')->header('Vary', 'Accept');
    }
}
