<?php

namespace App\Http\Controllers;

use App\Models\BookCover;
use App\Services\CoverRatings\CoverImageResponse;
use App\Services\CoverRatings\CoverRatingAccessService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CoverImageController extends Controller
{
    public function __invoke(Request $request, BookCover $bookCover, string $variant, CoverRatingAccessService $access, CoverImageResponse $images): Response
    {
        $access->ensureMemberAccess();

        return $images->respond($request, $bookCover, $variant);
    }
}
