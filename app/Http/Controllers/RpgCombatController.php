<?php

namespace App\Http\Controllers;

use App\Models\RpgCharacter;
use App\Models\RpgCombat;
use App\Models\RpgNpc;
use App\Services\RpgAccess;
use App\Services\RpgCombat\CombatQuery;
use App\Services\RpgCombat\CombatService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RpgCombatController extends Controller
{
    public function __construct(private CombatService $service, private CombatQuery $query, private RpgAccess $access) {}

    public function index(Request $request)
    {
        return response()->view('rpg.combats.index', ['combats' => $this->query->listing($request->user())->latest('id')->paginate(20)])->header('Cache-Control', 'private, no-store');
    }

    public function create(Request $request)
    {
        $team = $this->access->team();
        $characters = RpgCharacter::with('user')->whereIn('user_id', $team->activeUsers()->select('users.id'))->orderBy('character_name')->get();

        return response()->view('rpg.combats.create', ['characters' => $characters, 'submissionKey' => (string) Str::uuid(),
            'npcs' => $this->access->isLeader($request->user()) ? RpgNpc::where('team_id', $team->id)->get() : collect(),
            'selectedNpc' => (int) $request->query('npc_id', 0)])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        $combat = $this->service->challenge($request->user(), $request->except('_token'));

        return redirect()->route('rpg.combats.show', $combat);
    }

    public function show(Request $request, RpgCombat $combat)
    {
        $input = $request->validate(['before' => 'sometimes|integer|min:1']);
        $data = $this->query->detail($request->user(), $combat, (int) ($input['before'] ?? 0));
        if ($request->expectsJson()) {
            return response()->json(['revision' => $combat->revision, 'html' => view('rpg.combats.partials.fight', $data)->render()])
                ->header('Cache-Control', 'private, no-store');
        }

        return response()->view('rpg.combats.show', $data)->header('Cache-Control', 'private, no-store');
    }

    public function command(Request $request, RpgCombat $combat)
    {
        $input = $request->validate(['command' => 'required|in:accept,decline,withdraw,surrender,abort', 'submission_key' => 'required|uuid', 'side' => 'sometimes|integer|in:1,2']);
        $this->service->command($request->user(), $combat->id, $input['command'], $input['submission_key'], isset($input['side']) ? (int) $input['side'] : null);

        return redirect()->route('rpg.combats.show', $combat);
    }

    public function decide(Request $request, RpgCombat $combat, int $decision)
    {
        $input = $request->except('_token');
        // HTML controls carry strings; normalize only known numeric and boolean fields.
        foreach (['mode', 'aim', 'move', 'duration', 'range', 'strength', 'damage', 'displacement', 'modifier', 'difficulty'] as $key) {
            if (isset($input[$key]) && is_string($input[$key]) && preg_match('/^-?\d+$/D', $input[$key])) {
                $input[$key] = (int) $input[$key];
            }
        }
        foreach (['shield', 'full_defense', 'abort', 'entangle'] as $key) {
            if (isset($input[$key]) && in_array($input[$key], ['0', '1'], true)) {
                $input[$key] = $input[$key] === '1';
            }
        }
        if (isset($input['skill']) || ($input['kind'] ?? '') === 'switch') {
            $input['weapons'] ??= [];
        }
        $this->service->decide($request->user(), $combat->id, $decision, $input);

        return redirect()->route('rpg.combats.show', $combat);
    }
}
