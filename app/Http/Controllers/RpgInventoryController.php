<?php

namespace App\Http\Controllers;

use App\Models\RpgCharacter;
use App\Models\RpgInventoryTransaction;
use App\Services\RpgAccess;
use App\Services\RpgInventoryAdapter;
use App\Services\RpgInventoryService;
use App\Support\RpgCharEditorEquipment;
use App\Support\RpgInventoryCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class RpgInventoryController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('manage-rpg-inventory');
        $search = trim($request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? '');
        $team = app(RpgAccess::class)->team();
        $characters = RpgCharacter::select(['id', 'user_id', 'character_name'])->with('user:id,name,nickname')
            ->whereIn('user_id', $team->activeUsers()->select('users.id'))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('character_name', 'like', '%'.$search.'%')
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%'.$search.'%')->orWhere('nickname', 'like', '%'.$search.'%'))))
            ->orderBy('character_name')->paginate(20)->withQueryString();

        return view('rpg.inventory.index', compact('characters', 'search'));
    }

    public function show(RpgCharacter $rpgCharacter)
    {
        $this->authorize('viewInventory', $rpgCharacter);
        $canManage = Gate::allows('manageInventory', $rpgCharacter);
        $state = app(RpgInventoryAdapter::class)->state($rpgCharacter->payload);
        $catalog = RpgInventoryCatalog::positions();
        foreach ($state['stocks'] as $id => $quantity) {
            $catalog[$id] ??= ['id' => $id, 'name' => 'Unbekannter Altbestand: '.$id, 'kind' => 'unknown'];
        }
        $peers = $canManage ? RpgCharacter::select(['id', 'user_id', 'character_name', 'revision'])->with('user:id,name,nickname')
            ->where('id', '!=', $rpgCharacter->id)->whereIn('user_id', app(RpgAccess::class)->team()->activeUsers()->select('users.id'))
            ->orderBy('character_name')->get()->map(fn ($character) => ['id' => $character->id, 'revision' => $character->revision,
                'name' => $character->displayName().' ('.$character->user->nicknameOrName().')'])->values()->all() : [];
        $config = ['state' => $state, 'catalog' => $catalog, 'categories' => RpgCharEditorEquipment::categories() + ['clothing' => 'Kleidung', 'ammo' => 'Munition'], 'peers' => $peers, 'characterId' => $rpgCharacter->id,
            'revision' => $rpgCharacter->revision, 'catalogVersion' => RpgInventoryCatalog::VERSION, 'submissionKey' => (string) Str::uuid(),
            'today' => now()->timezone('Europe/Berlin')->toDateString(), 'previewUrl' => route('rpg.inventory.preview', $rpgCharacter),
            'saveUrl' => route('rpg.inventory.store', $rpgCharacter)];

        return view('rpg.inventory.show', [
            'character' => $rpgCharacter, 'state' => $state, 'catalog' => $catalog, 'canManage' => $canManage, 'config' => $config,
            'kinds' => array_diff_key(RpgInventoryTransaction::kinds(), ['advancement' => true, 'reversal' => true]),
            'clothing' => RpgCharEditorEquipment::clothing(), 'items' => RpgCharEditorEquipment::items(),
            'entries' => $rpgCharacter->inventoryEntries()->with(['transaction.actor', 'transaction.reversal', 'transaction.entries'])
                ->latest('id')->paginate(20),
        ]);
    }

    public function preview(Request $request, RpgCharacter $rpgCharacter)
    {
        return response()->json(app(RpgInventoryService::class)->preview($request->user(), $rpgCharacter->id, $request->except('_token')));
    }

    public function store(Request $request, RpgCharacter $rpgCharacter)
    {
        app(RpgInventoryService::class)->book($request->user(), $rpgCharacter->id, $request->except('_token'));
        $request->session()->flash('success', 'Inventarbuchung wurde gespeichert.');
        $url = route('rpg.inventory.show', $rpgCharacter);

        return $request->expectsJson() ? response()->json(['redirect' => $url]) : redirect($url);
    }
}
