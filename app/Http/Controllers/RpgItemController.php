<?php

namespace App\Http\Controllers;

use App\Models\RpgItemType;
use App\Services\RpgAccess;
use App\Services\RpgEquipmentCatalogResolver;
use App\Services\RpgItemCreationEvaluator;
use App\Services\RpgItemService;
use App\Support\RpgCharEditorEquipment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RpgItemController extends Controller
{
    public function index()
    {
        $types = RpgItemType::with('versions')->where('team_id', app(RpgAccess::class)->team()->id)->latest('id')->paginate(20);

        return view('rpg.items.index', compact('types'));
    }

    public function create(?RpgItemType $itemType = null)
    {
        if ($itemType) {
            $this->authorize('update', $itemType);
        }
        $latest = $itemType?->versions()->where('version', $itemType->current_version)->firstOrFail();
        $input = $latest?->input ?? [];
        if (isset($latest?->definition['ammunitionProfile'])) {
            $input['ammunition_id'] = $latest->definition['ammunitionProfile']['id'];
        }
        $config = ['input' => $input, 'fields' => RpgItemCreationEvaluator::fields(), 'common' => RpgItemCreationEvaluator::COMMON,
            'categories' => RpgCharEditorEquipment::categories() + ['clothing' => 'Kleidung'],
            'ammunition' => app(RpgEquipmentCatalogResolver::class)->resolve()->ammunition(),
            'revision' => $itemType?->revision ?? 0, 'submissionKey' => (string) Str::uuid(),
            'previewUrl' => $itemType ? route('rpg.items.versions.preview', $itemType) : route('rpg.items.preview'),
            'saveUrl' => $itemType ? route('rpg.items.versions.store', $itemType) : route('rpg.items.store')];

        return view('rpg.items.create', compact('config', 'itemType'));
    }

    public function show(RpgItemType $itemType)
    {
        $this->authorize('update', $itemType);
        $versions = $itemType->versions()->orderByDesc('version')->get();

        return view('rpg.items.show', compact('itemType', 'versions'));
    }

    public function preview(Request $request, RpgItemService $service, ?RpgItemType $itemType = null)
    {
        return response()->json($service->preview($request->user(), $request->except('_token'), $itemType?->id));
    }

    public function store(Request $request, RpgItemService $service, ?RpgItemType $itemType = null)
    {
        $version = $service->save($request->user(), $request->except('_token'), $itemType?->id);

        return response()->json(['redirect' => route('rpg.items.show', $version->item_type_id)]);
    }

    public function configure(Request $request, RpgItemType $itemType, RpgItemService $service)
    {
        $service->configure($request->user(), $itemType->id, $request->except('_token', '_method'));

        return redirect()->route('rpg.items.show', $itemType)->with('success', 'Gegenstandsfreigabe wurde gespeichert.');
    }
}
