<?php

namespace App\Support;

use App\Support\Navigation\NavigationSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class Spotlight
{
    public function search(Request $request): JsonResponse
    {
        $headers = ['Cache-Control' => 'private, no-store, max-age=0', 'Vary' => 'Cookie'];
        $validator = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:100'],
            'scope' => ['nullable', 'string', 'in:public,member'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Bitte gib einen Suchbegriff mit höchstens 100 Zeichen ein.'], 422, $headers);
        }
        $data = $validator->validated();

        return response()->json(app(NavigationSearch::class)->search(
            $request->user(), $data['search'] ?? '', $data['scope'] ?? 'public',
        ), 200, $headers);
    }
}
