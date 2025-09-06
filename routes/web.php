<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminMessageController;
use App\Http\Controllers\ArbeitsgruppenController;
use App\Http\Controllers\Auth\CustomEmailVerificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DownloadsController;
use App\Http\Controllers\HoerbuchController;
use App\Http\Controllers\KassenbuchController;
use App\Http\Controllers\KompendiumController;
use App\Http\Controllers\MaddraxiversumController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\MitgliederController;
use App\Http\Controllers\MitgliederKarteController;
use App\Http\Controllers\MitgliedschaftController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PhotoGalleryController;
use App\Http\Controllers\ProfileViewController;
use App\Http\Controllers\ReviewCommentController;
use App\Http\Controllers\RewardController;
use App\Http\Controllers\RezensionController;
use App\Http\Controllers\RomantauschController;
use App\Http\Controllers\RpgCharEditorController;
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
Route::get('/termine', [PageController::class, 'termine'])->name('termine');
Route::get('/arbeitsgruppen', [ArbeitsgruppenController::class, 'publicIndex'])->name('arbeitsgruppen');
Route::get('/mitglied-werden', [PageController::class, 'mitgliedWerden'])->name('mitglied.werden');
Route::get('/impressum', [PageController::class, 'impressum'])->name('impressum');
Route::get('/datenschutz', [PageController::class, 'datenschutz'])->name('datenschutz');
Route::get('/spenden', [PageController::class, 'spenden'])->name('spenden');
Route::get('/changelog', [PageController::class, 'changelog'])->name('changelog');
Route::get('/mitglied-werden/erfolgreich', [PageController::class, 'mitgliedWerdenErfolgreich'])->name('mitglied.werden.erfolgreich');
Route::get('/mitglied-werden/bestaetigt', [PageController::class, 'mitgliedWerdenBestaetigt'])->name('mitglied.werden.bestaetigt');

// POST Route für Mitgliedschaftsantrag
Route::post('/mitglied-werden', [MitgliedschaftController::class, 'store'])->name('mitglied.store');

// Route für E-Mail-Verifizierung (Laravel Jetstream / Fortify)
Route::get('/email/bestaetigen/{id}/{hash}', CustomEmailVerificationController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->withoutMiddleware([RedirectIfAnwaerter::class])
    ->name('verification.verify.de');

// Nur für eingeloggte und verifizierte Mitglieder, die NICHT Anwärter sind
Route::middleware(['auth', 'verified', 'redirect.if.anwaerter'])->group(function () {
    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index')->middleware('admin');
    Route::controller(DashboardController::class)->group(function () {
        Route::get('/dashboard', 'index')->name('dashboard');
        Route::post('/anwaerter/{user}/freigeben', 'approveAnwaerter')->name('anwaerter.approve');
        Route::post('/anwaerter/{user}/ablehnen', 'rejectAnwaerter')->name('anwaerter.reject');
    });

    Route::controller(PageController::class)->group(function () {
        Route::get('/protokolle', 'protokolle')->name('protokolle');
        Route::get('/protokolle/download/{datei}', 'downloadProtokoll')->name('protokolle.download');
    });

    Route::get('/fotogalerie', [PhotoGalleryController::class, 'index'])->name('fotogalerie');

    Route::prefix('newsletter')->name('newsletter.')->controller(NewsletterController::class)->middleware('admin')->group(function () {
        Route::get('versenden', 'create')->name('create');
        Route::post('versenden', 'send')->name('send');
    });

    Route::prefix('admin/messages')->name('admin.messages.')->controller(AdminMessageController::class)->middleware('admin')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::delete('{message}', 'destroy')->name('destroy');
    });

    Route::prefix('mitglieder')->name('mitglieder.')->controller(MitgliederController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::put('/{user}/role', 'changeRole')->name('change-role');
        Route::post('/export-csv', 'exportCsv')->name('export-csv');
        Route::get('/all-emails', 'getAllEmails')->name('all-emails');
        Route::delete('/{user}', 'removeMember')->name('remove');
    });

    Route::prefix('profil')->name('profile.')->group(function () {
        Route::get('anzeigen', function () {
            return app(ProfileViewController::class)->show(Auth::user());
        })->name('view.self');
        Route::get('{user}', [ProfileViewController::class, 'show'])->name('view');
    });

    Route::prefix('mitglieder/karte')->controller(MitgliederKarteController::class)->group(function () {
        Route::get('/', 'index')->name('mitglieder.karte');
        Route::get('/gesperrt', 'locked')->name('mitglieder.karte.locked');
    });

    Route::prefix('aufgaben')->name('todos.')->controller(TodoController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('erstellen', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{todo}', 'show')->name('show');
        Route::get('{todo}/bearbeiten', 'edit')->name('edit');
        Route::post('{todo}/zuweisen', 'assign')->name('assign');
        Route::put('{todo}', 'update')->name('update');
        Route::post('{todo}/abschliessen', 'complete')->name('complete');
        Route::post('{todo}/pruefen', 'verify')->name('verify');
        Route::post('{todo}/freigeben', 'release')->name('release');
    });
    Route::prefix('hoerbuecher')->name('hoerbuecher.')->controller(HoerbuchController::class)->group(function () {
        Route::get('/', 'index')->name('index')->middleware('vorstand');

        Route::middleware('admin-or-vorstand')->group(function () {
            Route::get('erstellen', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('previous-speaker', 'previousSpeaker')->name('previous-speaker');
            Route::get('{episode}', 'show')->name('show');
            Route::get('{episode}/bearbeiten', 'edit')->name('edit');
            Route::put('{episode}', 'update')->name('update');
            Route::delete('{episode}', 'destroy')->name('destroy');
        });
    });

    Route::prefix('admin/arbeitsgruppen')->name('arbeitsgruppen.')->controller(ArbeitsgruppenController::class)->middleware('auth')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('erstellen', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{team}/bearbeiten', 'edit')->name('edit');
        Route::put('{team}', 'update')->name('update');
    });

    Route::get('/ag', [ArbeitsgruppenController::class, 'leaderIndex'])->name('ag.index');

    Route::get('/belohnungen', [RewardController::class, 'index'])->name('rewards.index');

    Route::prefix('treffen')->controller(MeetingController::class)->group(function () {
        Route::get('/', 'index')->name('meetings');
        Route::post('umleiten', 'redirectToZoom')->name('meetings.redirect');
    });

    Route::prefix('kassenbuch')->name('kassenbuch.')->controller(KassenbuchController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::put('zahlung-aktualisieren/{user}', 'updatePaymentStatus')->name('update-payment');
        Route::post('eintrag-hinzufuegen', 'addKassenbuchEntry')->name('add-entry');
    });

    Route::controller(MaddraxiversumController::class)->group(function () {
        Route::get('/maddraxiversum', 'index')->name('maddraxiversum.index');
        Route::get('/maddraxikon-staedte', 'getCities');
        Route::post('/mission/starten', 'startMission');
        Route::post('/mission/status-pruefen', 'checkMissionStatus');
        Route::get('/mission/status', 'getMissionStatus');
    });

    Route::get('/rpg/char-editor', [RpgCharEditorController::class, 'index'])
        ->name('rpg.char-editor')
        ->middleware('admin');

    Route::prefix('romantauschboerse')->name('romantausch.')->controller(RomantauschController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('angebot-erstellen', 'createOffer')->name('create-offer');
        Route::post('angebot-speichern', 'storeOffer')->name('store-offer');
        Route::get('anfrage-erstellen', 'createRequest')->name('create-request');
        Route::post('anfrage-speichern', 'storeRequest')->name('store-request');
        Route::post('{offer}/angebot-loeschen', 'deleteOffer')->name('delete-offer');
        Route::post('{request}/anfrage-loeschen', 'deleteRequest')->name('delete-request');
        Route::post('{offer}/{request}/abschliessen', 'completeSwap')->name('complete-swap');
        Route::post('tausche/{swap}/bestaetigen', 'confirmSwap')->name('confirm-swap');
    });

    Route::prefix('downloads')->controller(DownloadsController::class)->group(function () {
        Route::get('/', 'index')->name('downloads');
        Route::get('herunterladen/{datei}', 'download')->name('downloads.download');
    });

    Route::prefix('kompendium')->name('kompendium.')->controller(KompendiumController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('suche', 'search')->name('search');
    });

    Route::get('/statistik', [StatistikController::class, 'index'])->name('statistik.index');

    Route::prefix('rezensionen')->name('reviews.')->group(function () {
        Route::get('/', [RezensionController::class, 'index'])->name('index');
        Route::get('/{book}', [RezensionController::class, 'show'])->name('show');
        Route::get('/{book}/erstellen', [RezensionController::class, 'create'])->name('create');
        Route::post('/{book}', [RezensionController::class, 'store'])->name('store');
        Route::get('/{review}/bearbeiten', [RezensionController::class, 'edit'])->name('edit');
        Route::put('/{review}', [RezensionController::class, 'update'])->name('update');
        Route::delete('/{review}', [RezensionController::class, 'destroy'])->name('destroy');
        Route::post('/{review}/kommentar', [ReviewCommentController::class, 'store'])->name('comments.store');
        Route::put('/kommentar/{comment}', [ReviewCommentController::class, 'update'])->name('comments.update');
        Route::delete('/kommentar/{comment}', [ReviewCommentController::class, 'destroy'])->name('comments.destroy');
    });
});
