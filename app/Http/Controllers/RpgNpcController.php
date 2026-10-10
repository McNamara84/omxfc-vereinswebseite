<?php

namespace App\Http\Controllers;

use App\Models\RpgNpc;
use App\Services\RpgNpcService;
use App\Support\RpgNpcCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RpgNpcController extends Controller
{
    public function __construct(private RpgNpcService $service) {}

    public function create(Request $request)
    {
        $data = $request->validate(['template_key' => ['sometimes', Rule::in(array_keys(RpgNpcCatalog::all()))]]);

        return $this->form($data['template_key'] ?? old('template_key', 'androne'), (string) Str::uuid());
    }

    public function preview(Request $request)
    {
        $input = $request->except('_token');
        $preview = $this->service->preview($request->user(), $input);

        return $this->form($input['template_key'], $input['submission_key'] ?? (string) Str::uuid(), $preview, $input);
    }

    private function form(string $key, string $submissionKey, ?array $preview = null, array $input = [])
    {
        if (! isset(RpgNpcCatalog::all()[$key])) {
            $key = 'androne';
        }

        return response()->view('rpg.npcs.create', [
            'templates' => RpgNpcCatalog::all(), 'selected' => RpgNpcCatalog::all()[$key], 'ranks' => RpgNpcCatalog::ranks(),
            'skills' => RpgNpcCatalog::SKILLS, 'submissionKey' => $submissionKey, 'preview' => $preview, 'input' => $input,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        $npc = $this->service->create($request->user(), $request->except('_token'));

        return redirect()->route('rpg.npcs.show', $npc)->with('success', 'NSC aus dem Regelwerk erstellt.');
    }

    public function show(RpgNpc $npc)
    {
        $this->authorize('view', $npc);

        return response()->view('rpg.npcs.show', ['npc' => $npc, 'profile' => $npc->profile])->header('Cache-Control', 'private, no-store');
    }

    public function rename(Request $request, RpgNpc $npc)
    {
        $this->service->rename($request->user(), $npc, $request->except('_token', '_method'));

        return redirect()->route('rpg.npcs.show', $npc)->with('success', 'NSC-Name geändert.');
    }

    public function destroy(Request $request, RpgNpc $npc)
    {
        $this->service->delete($request->user(), $npc);

        return redirect()->route('rpg.characters.index')->with('success', 'NSC gelöscht.');
    }
}
