<?php

use App\Http\Controllers\Auth\CustomEmailVerificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DownloadsController;
use App\Http\Controllers\KassenbuchController;
use App\Http\Controllers\KompendiumController;
use App\Http\Controllers\MaddraxiversumController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\MitgliederController;
use App\Http\Controllers\MitgliederKarteController;
use App\Http\Controllers\MitgliedschaftController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PhotoGalleryController;
use App\Http\Controllers\ProfileViewController;
use App\Http\Controllers\ReviewCommentController;
use App\Http\Controllers\RewardController;
use App\Http\Controllers\RezensionController;
use App\Http\Controllers\RomantauschController;
use App\Http\Controllers\StatistikController;
use App\Http\Controllers\TodoController;
use App\Http\Middleware\RedirectIfAnwaerter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

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
    Route::controller(DashboardController::class)->group(function () {
        Route::get('/dashboard', 'index')->name('dashboard');
        Route::post('/anwaerter/{user}/approve', 'approveAnwaerter')->name('anwaerter.approve');
        Route::post('/anwaerter/{user}/reject', 'rejectAnwaerter')->name('anwaerter.reject');
    });

    Route::controller(PageController::class)->group(function () {
        Route::get('/protokolle', 'protokolle')->name('protokolle');
        Route::get('/protokolle/download/{datei}', 'downloadProtokoll')->name('protokolle.download');
    });

    Route::get('/fotogalerie', [PhotoGalleryController::class, 'index'])->name('fotogalerie');

    Route::prefix('mitglieder')->name('mitglieder.')->controller(MitgliederController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::put('/{user}/role', 'changeRole')->name('change-role');
        Route::post('/export-csv', 'exportCsv')->name('export-csv');
        Route::get('/all-emails', 'getAllEmails')->name('all-emails');
        Route::delete('/{user}', 'removeMember')->name('remove');
    });

    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('view', function () {
            return app(ProfileViewController::class)->show(Auth::user());
        })->name('view.self');
        Route::get('{user}', [ProfileViewController::class, 'show'])->name('view');
    });

    Route::prefix('mitglieder/karte')->controller(MitgliederKarteController::class)->group(function () {
        Route::get('/', 'index')->name('mitglieder.karte');
        Route::get('/locked', 'locked')->name('mitglieder.karte.locked');
    });

    Route::prefix('todos')->name('todos.')->controller(TodoController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{todo}', 'show')->name('show');
        Route::get('{todo}/edit', 'edit')->name('edit');
        Route::post('{todo}/assign', 'assign')->name('assign');
        Route::put('{todo}', 'update')->name('update');
        Route::post('{todo}/complete', 'complete')->name('complete');
        Route::post('{todo}/verify', 'verify')->name('verify');
        Route::post('{todo}/release', 'release')->name('release');
    });

    Route::get('/belohnungen', [RewardController::class, 'index'])->name('rewards.index');

    Route::prefix('meetings')->controller(MeetingController::class)->group(function () {
        Route::get('/', 'index')->name('meetings');
        Route::post('redirect', 'redirectToZoom')->name('meetings.redirect');
    });

    Route::prefix('kassenbuch')->name('kassenbuch.')->controller(KassenbuchController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::put('update-payment/{user}', 'updatePaymentStatus')->name('update-payment');
        Route::post('add-entry', 'addKassenbuchEntry')->name('add-entry');
    });

    Route::controller(MaddraxiversumController::class)->group(function () {
        Route::get('/maddraxiversum', 'index')->name('maddraxiversum.index');
        Route::get('/maddraxikon-cities', 'getCities');
        Route::post('/mission/start', 'startMission');
        Route::post('/mission/check-status', 'checkMissionStatus');
        Route::get('/mission/status', 'getMissionStatus');
    });

    Route::get('/badges/{filename}', function ($filename) {
        $path = public_path('images/badges/' . $filename);
        if (!file_exists($path)) {
            abort(404);
        }

        return response()->file($path);
    })->name('badges.image');

    Route::prefix('romantauschboerse')->name('romantausch.')->controller(RomantauschController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create-offer', 'createOffer')->name('create-offer');
        Route::post('store-offer', 'storeOffer')->name('store-offer');
        Route::get('create-request', 'createRequest')->name('create-request');
        Route::post('store-request', 'storeRequest')->name('store-request');
        Route::post('{offer}/delete-offer', 'deleteOffer')->name('delete-offer');
        Route::post('{request}/delete-request', 'deleteRequest')->name('delete-request');
        Route::post('{offer}/{request}/complete', 'completeSwap')->name('complete-swap');
        Route::post('swaps/{swap}/confirm', 'confirmSwap')->name('confirm-swap');
    });

    Route::prefix('downloads')->controller(DownloadsController::class)->group(function () {
        Route::get('/', 'index')->name('downloads');
        Route::get('download/{datei}', 'download')->name('downloads.download');
    });

    Route::prefix('kompendium')->name('kompendium.')->controller(KompendiumController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('search', 'search')->name('search');
    });

    Route::get('/statistik', [StatistikController::class, 'index'])->name('statistik.index');

    Route::prefix('rezensionen')->name('reviews.')->group(function () {
        Route::get('/', [RezensionController::class, 'index'])->name('index');
        Route::get('/{book}', [RezensionController::class, 'show'])->name('show');
        Route::get('/{book}/create', [RezensionController::class, 'create'])->name('create');
        Route::post('/{book}', [RezensionController::class, 'store'])->name('store');
        Route::get('/{review}/edit', [RezensionController::class, 'edit'])->name('edit');
        Route::put('/{review}', [RezensionController::class, 'update'])->name('update');
        Route::delete('/{review}', [RezensionController::class, 'destroy'])->name('destroy');
        Route::post('/{review}/comment', [ReviewCommentController::class, 'store'])->name('comments.store');
    });
});
