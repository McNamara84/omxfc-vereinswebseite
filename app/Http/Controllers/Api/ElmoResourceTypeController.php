<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ResourceType;
use Illuminate\Http\JsonResponse;

class ElmoResourceTypeController extends Controller
{
    private const APPLICATION = 'ELMO';

    public function __invoke(): JsonResponse
    {
        $resourceTypes = ResourceType::query()
            ->forApplication(self::APPLICATION)
            ->orderBy('name')
            ->get([
                'id',
                'application',
                'slug',
                'name',
                'description',
                'updated_at',
            ])
            ->map(fn (ResourceType $resourceType) => [
                'id' => $resourceType->id,
                'application' => $resourceType->application,
                'slug' => $resourceType->slug,
                'name' => $resourceType->name,
                'description' => $resourceType->description,
                'updated_at' => $resourceType->updated_at?->toISOString(),
            ]);

        return response()->json([
            'data' => $resourceTypes,
        ]);
    }
}
