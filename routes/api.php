<?php

use App\Http\Controllers\Api\ElmoResourceTypeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware('elmo.api')->group(function () {
        Route::get('resource-types/elmo', ElmoResourceTypeController::class)
            ->name('api.resource-types.elmo');
    });
});
