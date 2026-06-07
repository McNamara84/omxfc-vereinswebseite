<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminMessageController;
use App\Http\Controllers\ArbeitsgruppenController;
use App\Http\Controllers\AuktionController;
use App\Http\Controllers\AuktionVerwaltungController;
use App\Http\Controllers\Auth\CustomEmailVerificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DownloadsController;
use App\Http\Controllers\FanfictionAdminController;
use App\Http\Controllers\FanfictionCommentController;
use App\Http\Controllers\FanfictionController;
use App\Http\Controllers\HoerbuchController;
use App\Http\Controllers\KassenbuchController;
use App\Http\Controllers\KompendiumController;
use App\Http\Controllers\MaddraxiversumController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\MitgliederController;
use App\Http\Controllers\MitgliederKarteController;
use App\Http\Controllers\MitgliedschaftController;
use App\Http\Controllers\NewsletterArchivAdminController;
use App\Http\Controllers\NewsletterArchivController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PhotoGalleryController;
use App\Http\Controllers\ProfileViewController;
use App\Http\Controllers\ReviewCommentController;
use App\Http\Controllers\RezensionController;
use App\Http\Controllers\RomantauschController;
use App\Http\Controllers\RpgCharEditorController;
use App\Http\Controllers\StatistikController;
use App\Http\Controllers\ThreeDModelController;
use App\Http\Controllers\TourAdminController;
use App\Http\Controllers\TourController;
use App\Http\Controllers\VeranstaltungController;
use App\Http\Controllers\VeranstaltungVerwaltungController;
use App\Http\Middleware\RedirectIfAnwaerter;
use App\Livewire\BelohnungenAdmin;
use App\Livewire\BelohnungenIndex;
use App\Livewire\FanfictionCreate;
use App\Livewire\FanfictionEdit;
use App\Livewire\FantreffenAdminDashboard;
use App\Livewire\FantreffenVipAuthors;
use App\Livewire\HoerbuchForm;
use App\Livewire\HoerbuchIndex;
use App\Livewire\HoerbuchShow;
use App\Livewire\KassenbuchIndex;
use App\Livewire\KompendiumAdminDashboard;
use App\Livewire\KompendiumSearchAnalyticsDashboard;
use App\Livewire\MeetingAdmin;
use App\Livewire\MitgliederIndex;
use App\Livewire\RezensionForm;
use App\Livewire\RezensionIndex;
use App\Livewire\RezensionShow;
use App\Livewire\RomantauschBundleForm;
use App\Livewire\RomantauschIndex;
use App\Livewire\RomantauschOfferForm;
use App\Livewire\RomantauschRequestForm;
use App\Livewire\RomantauschShowOffer;
use App\Livewire\ThreeDModelForm;
use App\Livewire\ThreeDModelIndex;
use App\Livewire\ThreeDModelShow;
use App\Livewire\TodoForm;
use App\Livewire\TodoIndex;
use App\Livewire\TodoShow;
use App\Livewire\Umfragen\UmfrageVerwaltung;
use App\Livewire\Umfragen\UmfrageVote;
use App\Models\Poll;
use App\Models\Veranstaltung;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Umfrage (aktuelle aktive) – öffentlich oder intern je nach Konfiguration
Route::livewire('/umfrage', UmfrageVote::class)->name('umfrage.aktuell');

// Öffentliche Seiten
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/satzung', [PageController::class, 'satzung'])->name('satzung');
Route::get('/chronik', [PageController::class, 'chronik'])->name('chronik');
Route::get('/ehrenmitglieder', [PageController::class, 'ehrenmitglieder'])->name('ehrenmitglieder');
Route::get('/termine', [PageController::class, 'termine'])->name('termine');
Route::get('/arbeitsgruppen', [ArbeitsgruppenController::class, 'publicIndex'])->name('arbeitsgruppen');
Route::get('/arbeitsgruppen/{team}/kontakt', [ArbeitsgruppenController::class, 'publicContact'])->name('arbeitsgruppen.kontakt');
Route::post('/arbeitsgruppen/{team}/kontakt', [ArbeitsgruppenController::class, 'sendPublicContact'])->middleware('throttle:arbeitsgruppen-kontakt')->name('arbeitsgruppen.kontakt.senden');
Route::get('/mitglied-werden', [PageController::class, 'mitgliedWerden'])->name('mitglied.werden');
Route::get('/impressum', [PageController::class, 'impressum'])->name('impressum');
Route::get('/datenschutz', [PageController::class, 'datenschutz'])->name('datenschutz');
Route::get('/spenden', [PageController::class, 'spenden'])->name('spenden');
Route::get('/changelog', [PageController::class, 'changelog'])->name('changelog');
Route::get('/mitglied-werden/erfolgreich', [PageController::class, 'mitgliedWerdenErfolgreich'])->name('mitglied.werden.erfolgreich');
Route::get('/mitglied-werden/bestaetigt', [PageController::class, 'mitgliedWerdenBestaetigt'])->name('mitglied.werden.bestaetigt');

// Fanfiction - Öffentliche Teaser-Ansicht für Gäste
Route::get('/fanfiction-teaser', [FanfictionController::class, 'publicIndex'])->name('fanfiction.public');

Route::get('/veranstaltungen/aktuell', [VeranstaltungController::class, 'aktuell'])->name('veranstaltungen.aktuell');
Route::get('/veranstaltungen/{veranstaltung:slug}', [VeranstaltungController::class, 'show'])->name('veranstaltungen.show');
Route::post('/veranstaltungen/{veranstaltung:slug}', [VeranstaltungController::class, 'store'])->middleware('throttle:fantreffen-registration')->name('veranstaltungen.anmeldung.store');
Route::get('/veranstaltungen/{veranstaltung:slug}/bestaetigung/{id}', [VeranstaltungController::class, 'bestaetigung'])->name('veranstaltungen.bestaetigung');

// Legacy-Pfade fuer das bisherige Fantreffen-Feature
Route::get('/maddrax-fantreffen-2026', [VeranstaltungController::class, 'legacyShow'])->name('fantreffen.2026');
Route::post('/maddrax-fantreffen-2026', [VeranstaltungController::class, 'legacyStore'])->middleware('throttle:fantreffen-registration')->name('fantreffen.2026.store');
Route::get('/maddrax-fantreffen-2026/bestaetigung/{id}', [VeranstaltungController::class, 'legacyBestaetigung'])->name('fantreffen.2026.bestaetigung');

// Hörbücher – Übersicht + Einzelfolgen öffentlich lesbar (kein Auth nötig), aber nicht im Menü / nicht indexiert
Route::prefix('hoerbuecher')->name('hoerbuecher.')->group(function () {
    Route::livewire('/', HoerbuchIndex::class)->name('index');
    Route::livewire('{episode}', HoerbuchShow::class)->name('show')->whereNumber('episode');
});

if (app()->environment(['local', 'testing'])) {
    Route::view('/_testing/modal-vorschau', 'testing.modal-preview')->name('testing.modal-preview');
}

// POST Route für Mitgliedschaftsantrag
Route::post('/mitglied-werden', [MitgliedschaftController::class, 'store'])->name('mitglied.store');

// Route für E-Mail-Verifizierung (Laravel Jetstream / Fortify)
Route::get('/email/bestaetigen/{id}/{hash}', CustomEmailVerificationController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->withoutMiddleware([RedirectIfAnwaerter::class])
    ->name('verification.verify.de');

// Nur für eingeloggte und verifizierte Mitglieder, die NICHT Anwärter sind
Route::middleware(['auth', 'verified', 'redirect.if.anwaerter'])->group(function () {
    Route::get('/admin/statistiken', [AdminController::class, 'index'])->name('admin.statistiken.index')->middleware('vorstand-or-kassenwart');

    // Umfragen verwalten (nur Admin/Vorstand)
    Route::livewire('/admin/umfragen', UmfrageVerwaltung::class)
        ->name('admin.umfragen.index')
        ->middleware('can:manage,'.Poll::class);

    Route::prefix('admin/veranstaltungen')->name('admin.veranstaltungen.')->group(function () {
        Route::controller(VeranstaltungVerwaltungController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/neu', 'create')->name('create');
            Route::post('/', 'store')->name('store');
            Route::get('/{veranstaltung}/bearbeiten', 'edit')->name('edit');
            Route::put('/{veranstaltung}', 'update')->name('update');
            Route::post('/{veranstaltung}/abschnitte', 'storeAbschnitt')->name('abschnitte.store');
            Route::put('/{veranstaltung}/abschnitte/{abschnitt}', 'updateAbschnitt')->name('abschnitte.update');
            Route::delete('/{veranstaltung}/abschnitte/{abschnitt}', 'destroyAbschnitt')->name('abschnitte.destroy');
            Route::post('/{veranstaltung}/merch', 'storeMerchartikel')->name('merch.store');
            Route::put('/{veranstaltung}/merch/{merchartikel}', 'updateMerchartikel')->name('merch.update');
            Route::delete('/{veranstaltung}/merch/{merchartikel}', 'destroyMerchartikel')->name('merch.destroy');
        });

        Route::livewire('/{veranstaltung}/anmeldungen', FantreffenAdminDashboard::class)
            ->name('anmeldungen')
            ->middleware('can:manage,'.Veranstaltung::class);

        Route::livewire('/{veranstaltung}/vip-autoren', FantreffenVipAuthors::class)
            ->name('vip-authors')
            ->middleware('can:manage,'.Veranstaltung::class);
    });

    Route::get('/admin/fantreffen-2026', fn () => redirect()->route('admin.veranstaltungen.anmeldungen', ['veranstaltung' => 'maddrax-fantreffen-2026']))
        ->name('admin.fantreffen.2026')
        ->middleware('can:manage,'.Veranstaltung::class);

    Route::get('/admin/fantreffen-2026/vip-autoren', fn () => redirect()->route('admin.veranstaltungen.vip-authors', ['veranstaltung' => 'maddrax-fantreffen-2026']))
        ->name('admin.fantreffen.vip-authors')
        ->middleware('can:manage,'.Veranstaltung::class);

    // Fanfiction Admin (Vorstand)
    Route::prefix('vorstand/fanfiction')->name('admin.fanfiction.')->middleware('vorstand-or-kassenwart')->group(function () {
        Route::get('/', [FanfictionAdminController::class, 'index'])->name('index');
        Route::get('/erstellen', FanfictionCreate::class)->name('create');
        Route::get('/{fanfiction}/bearbeiten', FanfictionEdit::class)->name('edit');
        Route::delete('/{fanfiction}', [FanfictionAdminController::class, 'destroy'])->name('destroy');
        Route::post('/{fanfiction}/veroeffentlichen', [FanfictionAdminController::class, 'publish'])->name('publish');
    });

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

    Route::prefix('newsletter')->name('newsletter.')->controller(NewsletterController::class)->middleware('admin-or-vorstand')->group(function () {
        Route::get('versenden', 'create')->name('create');
        Route::post('versenden', 'send')->name('send');
    });

    Route::prefix('admin/newsletter-archiv')->name('newsletter.archiv.admin.')->controller(NewsletterArchivAdminController::class)->middleware('admin-or-vorstand')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/anlegen', 'store')->name('store');
        Route::get('/{newsletterAusgabe}/bearbeiten', 'edit')->name('edit');
        Route::put('/{newsletterAusgabe}', 'update')->name('update');
        Route::post('/{newsletterAusgabe}/veroeffentlichen', 'publish')->name('publish');
    });

    Route::prefix('admin/messages')->name('admin.messages.')->controller(AdminMessageController::class)->middleware('admin')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::delete('{message}', 'destroy')->name('destroy');
    });

    Route::prefix('mitglieder')->name('mitglieder.')->group(function () {
        Route::livewire('/', MitgliederIndex::class)->name('index');
        Route::controller(MitgliederController::class)->group(function () {
            Route::put('/{user}/role', 'changeRole')->name('change-role');
            Route::post('/export-csv', 'exportCsv')->name('export-csv');
            Route::get('/all-emails', 'getAllEmails')->name('all-emails');
            Route::delete('/{user}', 'removeMember')->name('remove');
        });
    });

    Route::prefix('profil')->name('profile.')->group(function () {
        Route::get('anzeigen', function () {
            return app(ProfileViewController::class)->show(Auth::user());
        })->name('view.self');
        Route::get('{user}', [ProfileViewController::class, 'show'])->name('view');
    });

    Route::prefix('touren')->name('touren.')->controller(TourController::class)->group(function () {
        Route::get('aktuell', 'current')->name('current');
        Route::post('{tourAssignment}/starten', 'start')->name('start');
        Route::post('{tourAssignment}/schritt', 'progress')->name('progress');
        Route::post('{tourAssignment}/abbrechen', 'dismiss')->name('dismiss');
        Route::post('{tourAssignment}/abschliessen', 'complete')->name('complete');
        Route::post('{tourKey}/neu-starten', 'restart')->name('restart');
    });

    Route::prefix('admin/touren')->name('admin.touren.')->controller(TourAdminController::class)->middleware('admin-or-vorstand')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/zuweisen', 'assign')->name('assign');
    });

    Route::prefix('mitglieder/karte')->controller(MitgliederKarteController::class)->group(function () {
        Route::get('/', 'index')->name('mitglieder.karte');
        Route::post('/freischalten', 'purchase')->name('mitglieder.karte.purchase');
        Route::get('/gesperrt', 'locked')->name('mitglieder.karte.locked');
    });

    Route::prefix('aufgaben')->name('todos.')->group(function () {
        Route::livewire('/', TodoIndex::class)->name('index');
        Route::livewire('erstellen', TodoForm::class)->name('create');
        Route::livewire('{todo}', TodoShow::class)->name('show');
        Route::livewire('{todo}/bearbeiten', TodoForm::class)->name('edit');
    });

    Route::prefix('auktionen')->name('auktionen.')->controller(AuktionController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{auktion}', 'show')->name('show')->whereNumber('auktion');
        Route::post('/{auktion}/gebote', 'storeGebot')->name('gebote.store')->whereNumber('auktion');
    });

    Route::prefix('hoerbuecher')->name('hoerbuecher.')->group(function () {
        Route::get('previous-speaker', [HoerbuchController::class, 'previousSpeaker'])->name('previous-speaker');
        Route::patch('rollen/{role}/hochgeladen', [HoerbuchController::class, 'updateRoleUploaded'])->name('roles.uploaded');
        Route::livewire('erstellen', HoerbuchForm::class)->name('create')->middleware('hoerbuch-manage');
        Route::livewire('{episode}/bearbeiten', HoerbuchForm::class)->name('edit')->middleware('hoerbuch-manage');
    });

    Route::prefix('admin/arbeitsgruppen')->name('arbeitsgruppen.')->controller(ArbeitsgruppenController::class)->middleware('auth')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('erstellen', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{team}/bearbeiten', 'edit')->name('edit');
        Route::put('{team}', 'update')->name('update');
        Route::post('{team}/mitglied-hinzufuegen', 'addMember')->name('add-member');
    });

    Route::get('/ag', [ArbeitsgruppenController::class, 'leaderIndex'])->name('ag.index');

    Route::livewire('/belohnungen', BelohnungenIndex::class)
        ->name('rewards.index');

    Route::livewire('/belohnungen/admin', BelohnungenAdmin::class)
        ->name('rewards.admin')
        ->middleware('admin');

    Route::livewire('/admin/treffen', MeetingAdmin::class)
        ->name('admin.meetings')
        ->middleware('admin-or-vorstand');

    Route::prefix('treffen')->controller(MeetingController::class)->group(function () {
        Route::get('/', 'index')->name('meetings');
        Route::post('umleiten', 'redirectToZoom')->name('meetings.redirect');
    });

    Route::get('kassenstand', [KassenbuchController::class, 'kassenstand'])->name('kassenstand.index');

    Route::prefix('kassenbuch')->name('kassenbuch.')->group(function () {
        Route::livewire('/', KassenbuchIndex::class)
            ->name('index')
            ->middleware('vorstand-or-kassenwart');

        Route::controller(KassenbuchController::class)->group(function () {
            Route::put('zahlung-aktualisieren/{user}', 'updatePaymentStatus')->name('update-payment');
            Route::post('eintrag-hinzufuegen', 'addKassenbuchEntry')->name('add-entry');

            // Bearbeitungsanfragen
            Route::post('eintrag/{entry}/bearbeitung-anfragen', 'requestEdit')->name('request-edit');
            Route::post('eintrag/{entry}/loeschung-anfragen', 'requestDelete')->name('request-delete');
            Route::put('eintrag/{entry}', 'updateEntry')->name('update-entry');
            Route::post('anfrage/{editRequest}/freigeben', 'approveEditRequest')->name('approve-edit');
            Route::post('anfrage/{editRequest}/ablehnen', 'rejectEditRequest')->name('reject-edit');
        });
    });

    Route::prefix('admin/auktionen')->name('admin.auktionen.')->controller(AuktionVerwaltungController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/neu', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{auktion}/bearbeiten', 'edit')->name('edit')->whereNumber('auktion');
        Route::put('/{auktion}', 'update')->name('update')->whereNumber('auktion');
        Route::delete('/{auktion}', 'destroy')->name('destroy')->whereNumber('auktion');
        Route::post('/{auktion}/zum-ersten', 'zumErsten')->name('zum-ersten')->whereNumber('auktion');
        Route::post('/{auktion}/zum-zweiten', 'zumZweiten')->name('zum-zweiten')->whereNumber('auktion');
        Route::post('/{auktion}/verkaufen', 'verkaufen')->name('verkaufen')->whereNumber('auktion');
        Route::post('/{auktion}/nicht-verkauft', 'nichtVerkauft')->name('nicht-verkauft')->whereNumber('auktion');
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
        ->middleware('can:access-rpg-char-editor');

    Route::post('/rpg/char-editor/pdf', [RpgCharEditorController::class, 'pdf'])
        ->name('rpg.char-editor.pdf')
        ->middleware('can:access-rpg-char-editor');

    Route::prefix('romantauschboerse')->name('romantausch.')->group(function () {
        Route::livewire('/', RomantauschIndex::class)->name('index');
        Route::livewire('angebot-erstellen', RomantauschOfferForm::class)->name('create-offer');
        Route::livewire('angebot/{offer}/bearbeiten', RomantauschOfferForm::class)->name('edit-offer');
        Route::livewire('angebot/{offer}', RomantauschShowOffer::class)->name('show-offer');
        Route::livewire('anfrage-erstellen', RomantauschRequestForm::class)->name('create-request');
        Route::livewire('anfrage/{bookRequest}/bearbeiten', RomantauschRequestForm::class)->name('edit-request');
        Route::livewire('stapel-angebot-erstellen', RomantauschBundleForm::class)->name('create-bundle-offer');
        Route::livewire('stapel/{bundleId}/bearbeiten', RomantauschBundleForm::class)->name('edit-bundle');

        // Legacy-Controller-Routen zur Abwärtskompatibilität alter Blade-Templates
        Route::post('angebot', [RomantauschController::class, 'storeOffer'])->name('store-offer');
        Route::put('angebot/{offer}', [RomantauschController::class, 'updateOffer'])->name('update-offer');
        Route::delete('angebot/{offer}', [RomantauschController::class, 'deleteOffer'])->name('delete-offer');

        Route::post('anfrage', [RomantauschController::class, 'storeRequest'])->name('store-request');
        Route::put('anfrage/{bookRequest}', [RomantauschController::class, 'updateRequest'])->name('update-request');
        Route::delete('anfrage/{request}', [RomantauschController::class, 'deleteRequest'])->name('delete-request');

        Route::post('stapel', [RomantauschController::class, 'storeBundleOffer'])->name('store-bundle-offer');
        Route::put('stapel/{bundleId}', [RomantauschController::class, 'updateBundle'])->name('update-bundle');
        Route::delete('stapel/{bundleId}', [RomantauschController::class, 'deleteBundle'])->name('delete-bundle');

        Route::post('tausch/{offer}/{request}/abschliessen', [RomantauschController::class, 'completeSwap'])->name('complete-swap');
        Route::post('tausch/{swap}/bestaetigen', [RomantauschController::class, 'confirmSwap'])->name('confirm-swap');
    });

    Route::prefix('downloads')->controller(DownloadsController::class)->group(function () {
        Route::get('/', 'index')->name('downloads');
        Route::get('herunterladen/{download:slug}', 'download')->name('downloads.download');
    });

    Route::prefix('3d-modelle')->name('3d-modelle.')->group(function () {
        Route::livewire('/', ThreeDModelIndex::class)->name('index');
        Route::middleware('admin-or-vorstand')->group(function () {
            Route::livewire('erstellen', ThreeDModelForm::class)->name('create');
            Route::livewire('{threeDModel}/bearbeiten', ThreeDModelForm::class)->name('edit');
        });
        Route::get('{threeDModel}/herunterladen', [ThreeDModelController::class, 'download'])->name('download');
        Route::get('{threeDModel}/vorschau', [ThreeDModelController::class, 'preview'])->name('preview');
        Route::livewire('{threeDModel}', ThreeDModelShow::class)->name('show');
    });

    Route::prefix('kompendium')->name('kompendium.')->group(function () {
        Route::controller(KompendiumController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('suche', 'search')->name('search');
            Route::get('serien', 'getVerfuegbareSerien')->name('serien');
        });

        // Admin-Bereich (nur für Admins)
        Route::get('admin', KompendiumAdminDashboard::class)
            ->middleware('admin')
            ->name('admin');

        Route::get('admin/suchstatistik', KompendiumSearchAnalyticsDashboard::class)
            ->middleware('admin')
            ->name('admin.search-statistics');
    });

    Route::get('/statistiken', [StatistikController::class, 'index'])->name('statistik.index');

    // Fanfiction (Mitglieder)
    Route::prefix('fanfiction')->name('fanfiction.')->group(function () {
        Route::get('/', [FanfictionController::class, 'index'])->name('index');
        Route::get('/{fanfiction}', [FanfictionController::class, 'show'])->name('show');
        Route::post('/{fanfiction}/kaufen', [FanfictionController::class, 'purchase'])->name('purchase');
        Route::post('/{fanfiction}/kommentar', [FanfictionCommentController::class, 'store'])->name('comments.store');
        Route::put('/kommentar/{comment}', [FanfictionCommentController::class, 'update'])->name('comments.update');
        Route::delete('/kommentar/{comment}', [FanfictionCommentController::class, 'destroy'])->name('comments.destroy');
    });

    Route::prefix('newsletter-archiv')->name('newsletter.archiv.')->controller(NewsletterArchivController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{newsletterAusgabe}', 'show')->name('show');
    });

    Route::prefix('rezensionen')->name('reviews.')->group(function () {
        Route::livewire('/', RezensionIndex::class)->name('index');
        Route::livewire('/{book}', RezensionShow::class)->name('show');
        Route::livewire('/{book}/erstellen', RezensionForm::class)->name('create');
        Route::livewire('/{review}/bearbeiten', RezensionForm::class)->name('edit');

        // Legacy-Controller-Routen zur Abwärtskompatibilität alter Blade-Templates
        Route::post('/{book}', [RezensionController::class, 'store'])->name('store');
        Route::put('/{review}', [RezensionController::class, 'update'])->name('update');
        Route::delete('/{review}', [RezensionController::class, 'destroy'])->name('destroy');

        Route::post('/{review}/kommentar', [ReviewCommentController::class, 'store'])->name('comments.store');
        Route::put('/kommentar/{comment}', [ReviewCommentController::class, 'update'])->name('comments.update');
        Route::delete('/kommentar/{comment}', [ReviewCommentController::class, 'destroy'])->name('comments.destroy');
    });
});
