<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ReviewPreviewController extends Controller
{
    /**
     * Liefert die fünf neuesten Rezensionen mit kurzem Vorschau-Text.
     */
    public function latest(): JsonResponse
    {
        $team = Team::membersTeam();

        if (! $team) {
            return response()->json([]);
        }

        $reviews = Review::withoutTrashed()
            ->where('team_id', $team->id)
            ->with(['book:id,roman_number,title'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function (Review $review) {
                $plainContent = strip_tags(Str::markdown($review->content));

                $excerpt = trim($plainContent);
                $excerpt = mb_strimwidth($excerpt, 0, 75, '…', 'UTF-8');

                return [
                    'roman_number' => $review->book->roman_number,
                    'roman_title' => $review->book->title,
                    'review_title' => $review->title,
                    'excerpt' => $excerpt,
                ];
            });

        return response()->json($reviews);
    }
}
