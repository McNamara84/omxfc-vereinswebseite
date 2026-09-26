<?php

namespace App\Http\Controllers;

use App\Models\RpgAdvancementRequest;
use App\Models\RpgAdventure;
use App\Models\RpgCharacter;
use App\Services\RpgAccess;
use App\Services\RpgCharacterAdvancementService;
use App\Services\RpgCharacterProgressionAdapter;
use App\Services\RpgCharacterSheetService;
use App\Services\RpgExperienceAwardService;
use App\Services\RpgProgressionHistory;
use App\Support\RpgCharEditorEquipment;
use App\Support\RpgCharEditorSpecialRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RpgProgressionController extends Controller
{
    public function __construct(
        private RpgAccess $access,
        private RpgExperienceAwardService $awards,
        private RpgCharacterAdvancementService $advancements,
        private RpgProgressionHistory $history,
    ) {}

    public function adventures()
    {
        $this->authorize('manage-rpg-experience');

        return view('rpg.progression.adventures', ['adventures' => RpgAdventure::withCount('awards')->latest('id')->paginate(20)]);
    }

    public function createAdventure()
    {
        $this->authorize('manage-rpg-experience');
        $team = $this->access->team();
        $characters = RpgCharacter::with('user')->whereHas('user.teams', fn ($query) => $query->where('teams.id', $team->id))->orderBy('character_name')->get();

        return view('rpg.progression.award', ['config' => [
            'mode' => 'award', 'submission_key' => (string) Str::uuid(), 'today' => now()->toDateString(),
            'previewUrl' => route('rpg.adventures.preview'), 'saveUrl' => route('rpg.adventures.store'),
            'characters' => $characters->map(fn (RpgCharacter $character): array => ['id' => $character->id, 'name' => $character->displayName().' ('.$character->user->nicknameOrName().')'])->values(),
        ]]);
    }

    public function previewAdventure(Request $request)
    {
        return response()->json($this->awards->preview($request->user(), $request->all()));
    }

    public function storeAdventure(Request $request)
    {
        $adventure = $this->awards->award($request->user(), $request->all());

        return $this->saved($request, route('rpg.adventures.show', $adventure), 'Erfahrungspunkte wurden vergeben.');
    }

    public function showAdventure(RpgAdventure $adventure)
    {
        $this->authorize('manage-rpg-experience');

        return view('rpg.progression.adventure', ['adventure' => $adventure->load(['awards.character.user', 'user'])]);
    }

    public function improve(RpgCharacter $rpgCharacter)
    {
        $this->authorize('improve', $rpgCharacter);
        $payload = (new RpgCharacterProgressionAdapter)->normalize($rpgCharacter->payload);
        $skills = array_values(array_unique([
            ...array_column(RpgCharacterSheetService::skillRuleConfig()['skills'], 'name'),
            ...array_column($payload['skills'], 'name'), ...RpgCharEditorSpecialRules::PSYCHIC_POWER_TARGETS,
        ]));

        return view('rpg.progression.improve', [
            'character' => $rpgCharacter, 'payload' => $payload,
            'history' => $this->history->forCharacter($rpgCharacter),
            'pending' => $rpgCharacter->advancements()->where('status', 'pending')->first(),
            'config' => [
                'mode' => 'improve', 'submission_key' => (string) Str::uuid(), 'revision' => $rpgCharacter->revision,
                'previewUrl' => route('rpg.characters.advancement-preview', $rpgCharacter),
                'saveUrl' => route('rpg.characters.advancements.store', $rpgCharacter),
                'names' => [
                    'attribute' => RpgCharEditorSpecialRules::ATTRIBUTE_TARGETS,
                    'skill' => $skills, 'advantage' => array_keys(RpgCharEditorSpecialRules::advantages()),
                    'disadvantage' => $payload['disadvantages'],
                ],
                'advantages' => RpgCharEditorSpecialRules::advantages(),
                'languages' => $payload['languages'] ?? [],
                'equipment' => array_values(array_filter(RpgCharEditorEquipment::items(), RpgCharEditorEquipment::requiresHighTechAdvantage(...))),
            ],
        ]);
    }

    public function preview(Request $request, RpgCharacter $rpgCharacter)
    {
        return response()->json($this->advancements->preview($request->user(), $rpgCharacter, $request->all()));
    }

    public function store(Request $request, RpgCharacter $rpgCharacter)
    {
        $advancement = $this->advancements->submit($request->user(), $rpgCharacter->id, $request->all());

        return $this->saved($request, route('rpg.advancements.show', $advancement), 'Verbesserung wurde zur Prüfung eingereicht.');
    }

    public function history(RpgCharacter $rpgCharacter)
    {
        abort_unless(Gate::allows('view', $rpgCharacter) || Gate::allows('review', $rpgCharacter), 403);

        return view('rpg.progression.history', [
            'character' => $rpgCharacter, 'history' => $this->history->forCharacter($rpgCharacter),
            'requests' => $rpgCharacter->advancements()->latest('id')->paginate(20),
            'payload' => (new RpgCharacterProgressionAdapter)->normalize($rpgCharacter->payload),
        ]);
    }

    public function requests()
    {
        $this->authorize('manage-rpg-experience');

        return view('rpg.progression.requests', [
            'requests' => RpgAdvancementRequest::with('character.user')->where('status', 'pending')->oldest('id')->paginate(20),
        ]);
    }

    public function show(RpgAdvancementRequest $advancementRequest)
    {
        $character = $advancementRequest->character;
        abort_unless(Gate::allows('view', $character) || Gate::allows('review', $character), 403);

        return view('rpg.progression.request', [
            'advancement' => $advancementRequest->load('reviewer'), 'character' => $character,
            'payload' => $character->payload, 'balance' => $character->experienceBalance(),
        ]);
    }

    public function approve(Request $request, RpgAdvancementRequest $advancementRequest)
    {
        $this->advancements->decide($request->user(), $advancementRequest->id, 'approved');

        return back()->with('success', 'Verbesserung genehmigt und Erfahrungspunkte ausgegeben.');
    }

    public function reject(Request $request, RpgAdvancementRequest $advancementRequest)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:4000']]);
        $this->advancements->decide($request->user(), $advancementRequest->id, 'rejected', $data['reason']);

        return back()->with('success', 'Antrag abgelehnt.');
    }

    public function withdraw(Request $request, RpgAdvancementRequest $advancementRequest)
    {
        $this->advancements->decide($request->user(), $advancementRequest->id, 'withdrawn');

        return back()->with('success', 'Antrag zurückgezogen.');
    }

    public function clarify(Request $request, RpgCharacter $rpgCharacter)
    {
        $this->authorize('manage-rpg-experience');
        $data = $request->validate([
            'revision' => ['required', 'integer'], 'reason' => ['required', 'string', 'max:4000'],
            'barbar_attribute' => ['nullable', Rule::in(RpgCharEditorSpecialRules::ATTRIBUTE_TARGETS)],
            'targets' => ['sometimes', 'array'], 'targets.*' => ['required', 'string', 'max:100'],
        ]);
        DB::transaction(function () use ($request, $rpgCharacter, $data): void {
            $this->access->requireLeader($request->user(), lock: true);
            $character = RpgCharacter::lockForUpdate()->findOrFail($rpgCharacter->id);
            if ($character->revision !== (int) $data['revision']) {
                throw ValidationException::withMessages(['revision' => 'Der Charakter wurde geändert. Bitte die Seite neu laden.']);
            }
            $adapter = new RpgCharacterProgressionAdapter;
            $payload = $adapter->normalize($character->payload);
            $before = $payload;
            if (! empty($data['barbar_attribute'])) {
                abort_unless(($payload['character']['race'] ?? '') === 'Barbar' && ! isset($payload['creation']['attribute_race_modifiers']) && ! isset($payload['progression']['race_modifiers']), 422);
                $payload['progression']['race_modifiers'] = [$data['barbar_attribute'] => 1];
            }
            foreach ($data['targets'] ?? [] as $index => $target) {
                $effect = $payload['advantage_effects'][$index] ?? null;
                $rule = $effect ? RpgCharEditorSpecialRules::advantages()[$effect['name']] ?? null : null;
                abort_unless($rule && ($effect['target'] ?? '') === '' && in_array($target, $rule['targets'], true), 422);
                $payload['advantage_effects'][$index]['target'] = $target;
            }
            foreach (['Gesteigertes Attribut', 'Gesteigerter Sinn'] as $name) {
                $targets = array_column(array_filter($payload['advantage_effects'], fn ($effect) => $effect['name'] === $name && ($effect['target'] ?? '') !== ''), 'target');
                if (count($targets) !== count(array_unique($targets))) {
                    throw ValidationException::withMessages(['targets' => 'Ein Ziel darf je Vorteil nur einmal vergeben sein.']);
                }
            }
            if ($before === $payload) {
                throw ValidationException::withMessages(['targets' => 'Es wurden keine fehlenden Herkunftsangaben ergänzt.']);
            }
            $payload['progression']['metadata_history'][] = [
                'date' => now()->toIso8601String(), 'actor' => $request->user()->nicknameOrName(),
                'reason' => $data['reason'], 'barbar_attribute' => $data['barbar_attribute'] ?? null, 'targets' => $data['targets'] ?? [],
            ];
            $character->payload = $payload;
            $character->revision++;
            $character->save();
        }, 3);

        return back()->with('success', 'Fehlende Herkunft dokumentiert. Charakterwerte und EP sind unverändert.');
    }

    private function saved(Request $request, string $url, string $message)
    {
        $request->session()->flash('success', $message);

        return $request->expectsJson() ? response()->json(['redirect' => $url]) : redirect($url);
    }
}
