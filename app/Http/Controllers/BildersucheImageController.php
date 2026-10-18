<?php

namespace App\Http\Controllers;

use App\Models\BookCover;
use App\Services\BildersucheAccessService;
use App\Services\BildersucheService;
use App\Services\CoverRatings\CoverImageResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BildersucheImageController extends Controller
{
    public function __invoke(Request $request, BookCover $bookCover, string $variant, BildersucheAccessService $access, CoverImageResponse $images): Response
    {
        $access->ensureSearchAccess();
        abort_unless(app(BildersucheService::class)->searchable()->whereKey($bookCover->id)->exists(), 404);

        return $images->respond($request, $bookCover, $variant, 'private, no-cache');
    }
}
