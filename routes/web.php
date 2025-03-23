<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\MitgliedschaftController;


// Öffentliche Seiten
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/satzung', [PageController::class, 'satzung'])->name('satzung');
Route::get('/chronik', [PageController::class, 'chronik'])->name('chronik');
Route::get('/ehrenmitglieder', [PageController::class, 'ehrenmitglieder'])->name('ehrenmitglieder');
Route::get('/arbeitsgruppen', [PageController::class, 'arbeitsgruppen'])->name('arbeitsgruppen');
Route::get('/termine', [PageController::class, 'termine'])->name('termine');
Route::get('/mitglied-werden', [PageController::class, 'mitgliedWerden'])->name('mitglied.werden');
Route::get('/impressum', [PageController::class, 'impressum'])->name('impressum');
Route::get('/datenschutz', [PageController::class, 'datenschutz'])->name('datenschutz');
Route::get('/mitglied-werden/erfolgreich', [PageController::class, 'mitgliedWerdenErfolgreich'])->name('mitglied.werden.erfolgreich');


// Nur für eingeloggte Mitglieder
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

Route::post('/mitglied-werden', [MitgliedschaftController::class, 'store'])->name('mitglied.store');