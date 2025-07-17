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
use App\Http\Controllers\KassenbuchController;
use App\Http\Controllers\MaddraxiversumController;
use App\Http\Controllers\RomantauschController;
use App\Http\Controllers\DownloadsController;
use App\Http\Controllers\KompendiumController;
use App\Http\Controllers\StatistikController;
use App\Http\Controllers\RezensionController;

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
    Route::post('/mitglieder/export-csv', [MitgliederController::class, 'exportCsv'])->name('mitglieder.export-csv');
    Route::get('/mitglieder/all-emails', [MitgliederController::class, 'getAllEmails'])->name('mitglieder.all-emails');
    Route::delete('/mitglieder/{user}', [MitgliederController::class, 'removeMember'])->name('mitglieder.remove');
    // Eigenes Profil anzeigen (muss VOR der generischen Route stehen)
    Route::get('/profile/view', function () {
        return app(ProfileViewController::class)->show(Auth::user());
    })->name('profile.view.self');
    // Fremdes Profil anzeigen
    Route::get('/profile/{user}', [ProfileViewController::class, 'show'])->name('profile.view');
    Route::get('/mitglieder/karte', [MitgliederKarteController::class, 'index'])->name('mitglieder.karte');
    Route::get('/mitglieder/karte/locked', [MitgliederKarteController::class, 'locked'])->name('mitglieder.karte.locked');
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
    Route::get('/kassenbuch', [KassenbuchController::class, 'index'])->name('kassenbuch.index');
    Route::put('/kassenbuch/update-payment/{user}', [KassenbuchController::class, 'updatePaymentStatus'])->name('kassenbuch.update-payment');
    Route::post('/kassenbuch/add-entry', [KassenbuchController::class, 'addKassenbuchEntry'])->name('kassenbuch.add-entry');
    Route::get('/maddraxiversum', [MaddraxiversumController::class, 'index'])->name('maddraxiversum.index');
    Route::get('/maddraxikon-cities', [MaddraxiversumController::class, 'getCities']);
    Route::post('/mission/start', [MaddraxiversumController::class, 'startMission']);
    Route::post('/mission/check-status', [MaddraxiversumController::class, 'checkMissionStatus']);
    Route::get('/mission/status', [MaddraxiversumController::class, 'getMissionStatus']);
    //Badges
    Route::get('/badges/{filename}', function ($filename) {
        $path = storage_path('app/private/' . $filename);
        if (!file_exists($path)) {
            abort(404);
        }
        return response()->file($path);
    })->name('badges.image');
    // Romantauschbörse
    Route::get('/romantauschboerse', [RomantauschController::class, 'index'])->name('romantausch.index');
    Route::get('/romantauschboerse/create-offer', [RomantauschController::class, 'createOffer'])->name('romantausch.create-offer');
    Route::post('/romantauschboerse/store-offer', [RomantauschController::class, 'storeOffer'])->name('romantausch.store-offer');
    Route::get('/romantauschboerse/create-request', [RomantauschController::class, 'createRequest'])->name('romantausch.create-request');
    Route::post('/romantauschboerse/store-request', [RomantauschController::class, 'storeRequest'])->name('romantausch.store-request');
    Route::post('/romantauschboerse/{offer}/delete-offer', [RomantauschController::class, 'deleteOffer'])->name('romantausch.delete-offer');
    Route::post('/romantauschboerse/{request}/delete-request', [RomantauschController::class, 'deleteRequest'])->name('romantausch.delete-request');
    Route::post('/romantauschboerse/{offer}/{request}/complete', [RomantauschController::class, 'completeSwap'])->name('romantausch.complete-swap');
    Route::get('/downloads', [DownloadsController::class, 'index'])->name('downloads');
    Route::get('/downloads/download/{datei}', [DownloadsController::class, 'download'])->name('downloads.download');
    // Kompendium
    Route::get('/kompendium', [KompendiumController::class, 'index'])->name('kompendium.index');
    Route::get('/kompendium/search', [KompendiumController::class, 'search'])->name('kompendium.search');
    //Statistik
    Route::get('/statistik', [StatistikController::class, 'index'])->name('statistik.index');
    // Rezis
    Route::prefix('rezensionen')->name('reviews.')->group(function () {
        Route::get('/', [RezensionController::class, 'index'])->name('index');
        Route::get('/{review}/edit', [RezensionController::class, 'edit'])->name('edit');
        Route::put('/{review}', [RezensionController::class, 'update'])->name('update');
        Route::get('/{book}/create', [RezensionController::class, 'create'])->name('create');
        Route::post('/{book}', [RezensionController::class, 'store'])->name('store');
        Route::delete('/{review}', [RezensionController::class, 'destroy'])->name('destroy');
        Route::get('/{book}', [RezensionController::class, 'show'])->name('show');
    });
});
