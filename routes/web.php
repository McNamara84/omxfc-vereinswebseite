<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\MitgliedschaftController;
use App\Http\Controllers\Auth\CustomEmailVerificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PhotoGalleryController;
use App\Http\Controllers\MitgliederController;
use App\Http\Controllers\ProfileViewController;
use App\Http\Middleware\RedirectIfAnwaerter;
use App\Http\Controllers\MitgliederKarteController;
use App\Http\Controllers\TodoController;
use App\Http\Controllers\MeetingController;
use Illuminate\Support\Facades\Auth;

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
Route::get('/changelog', [PageController::class, 'changelog'])->name('changelog');
Route::get('/mitglied-werden/erfolgreich', [PageController::class, 'mitgliedWerdenErfolgreich'])->name('mitglied.werden.erfolgreich');
Route::get('/mitglied-werden/bestaetigt', [PageController::class, 'mitgliedWerdenBestaetigt'])->name('mitglied.werden.bestaetigt');

// POST Route für Mitgliedschaftsantrag
Route::post('/mitglied-werden', [MitgliedschaftController::class, 'store'])->name('mitglied.store');

// Route für E-Mail-Verifizierung (Laravel Jetstream / Fortify)
Route::get('/email/verify/{id}/{hash}', CustomEmailVerificationController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->withoutMiddleware([RedirectIfAnwaerter::class])
    ->name('verification.verify');

// Nur für eingeloggte und verifizierte Mitglieder, die NICHT Anwärter sind
Route::middleware(['auth', 'verified', 'redirect.if.anwaerter'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/protokolle', [PageController::class, 'protokolle'])->name('protokolle');
    Route::get('/protokolle/download/{datei}', [PageController::class, 'downloadProtokoll'])->name('protokolle.download');
    Route::post('/anwaerter/{user}/approve', [DashboardController::class, 'approveAnwaerter'])->name('anwaerter.approve');
    Route::post('/anwaerter/{user}/reject', [DashboardController::class, 'rejectAnwaerter'])->name('anwaerter.reject');
    Route::get('/fotogalerie', [PhotoGalleryController::class, 'index'])->name('fotogalerie');
    Route::get('/mitglieder', [MitgliederController::class, 'index'])->name('mitglieder.index');
    Route::put('/mitglieder/{user}/role', [MitgliederController::class, 'changeRole'])->name('mitglieder.change-role');
    Route::delete('/mitglieder/{user}', [MitgliederController::class, 'removeMember'])->name('mitglieder.remove');
    // Eigenes Profil anzeigen (muss VOR der generischen Route stehen)
    Route::get('/profile/view', function () {
        return app(ProfileViewController::class)->show(Auth::user());
    })->name('profile.view.self');
    // Fremdes Profil anzeigen
    Route::get('/profile/{user}', [ProfileViewController::class, 'show'])->name('profile.view');
    Route::get('/mitglieder/karte', [MitgliederKarteController::class, 'index'])->name('mitglieder.karte');
    Route::get('/todos', [TodoController::class, 'index'])->name('todos.index');
    Route::get('/todos/create', [TodoController::class, 'create'])->name('todos.create');
    Route::post('/todos', [TodoController::class, 'store'])->name('todos.store');
    Route::get('/todos/{todo}', [TodoController::class, 'show'])->name('todos.show');
    Route::post('/todos/{todo}/assign', [TodoController::class, 'assign'])->name('todos.assign');
    Route::post('/todos/{todo}/complete', [TodoController::class, 'complete'])->name('todos.complete');
    Route::post('/todos/{todo}/verify', [TodoController::class, 'verify'])->name('todos.verify');
    Route::post('/todos/{todo}/release', [TodoController::class, 'release'])->name('todos.release');
    Route::get('/meetings', [MeetingController::class, 'index'])->name('meetings');
    Route::post('/meetings/redirect', [MeetingController::class, 'redirectToZoom'])->name('meetings.redirect');
    //Badges
    Route::get('/badges/{filename}', function ($filename) {
        $path = storage_path('app/private/' . $filename);
        if (!file_exists($path)) {
            abort(404);
        }
        return response()->file($path);
    })->name('badges.image');
});
