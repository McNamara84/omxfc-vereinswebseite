<?php

use App\Http\Controllers\Api\ReviewPreviewController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/nutzer', fn (Request $request) => $request->user())->middleware('auth:sanctum');

Route::get('/reviews/latest', [ReviewPreviewController::class, 'latest'])
    ->middleware('throttle:60,1')
    ->name('api.reviews.latest');
