<?php

namespace App\Http\Controllers;

use App\Enums\KassenbuchEditReasonType;
use App\Enums\KassenbuchEntryType;
use App\Models\KassenbuchEditRequest;
use App\Models\KassenbuchEntry;
use App\Models\Kassenstand;
use App\Models\User;
use App\Services\MembersTeamProvider;
use App\Services\UserRoleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class KassenbuchController extends Controller
{
    public function __construct(
        private UserRoleService $userRoleService,
        private MembersTeamProvider $membersTeamProvider
    ) {
    }

    public function index()
    {
        $user = Auth::user();
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();

        // Benutzerrolle ermitteln
        $userRole = $this->userRoleService->getRole($user, $team);

        $canViewKassenbuch = $user->can('viewAll', KassenbuchEntry::class);
        $canManageKassenbuch = $user->can('manage', KassenbuchEntry::class);
        $canProcessEditRequests = $user->can('processEditRequest', KassenbuchEntry::class);

        // Aktuellen Kassenstand abrufen
        $kassenstand = Kassenstand::where('team_id', $team->id)->first();

        // Falls noch kein Kassenstand existiert, einen initialen Eintrag erstellen
        if (! $kassenstand) {
            $kassenstand = Kassenstand::create([
                'team_id' => $team->id,
                'betrag' => 0.00,
                'letzte_aktualisierung' => now(),
            ]);
        }

        // Für Vorstand und Kassenwart: Alle Mitglieder mit ihren Zahlungsdaten abrufen
        $members = null;
        $kassenbuchEntries = null;
        $pendingEditRequests = null;

        if ($canViewKassenbuch) {
            $members = $team->activeUsers()
                ->orderBy('bezahlt_bis')
                ->get();

            $kassenbuchEntries = KassenbuchEntry::where('team_id', $team->id)
                ->with(['pendingEditRequest', 'approvedEditRequest', 'creator', 'lastEditor'])
                ->orderBy('buchungsdatum', 'desc')
                ->get();
        }

        // Für Vorstand: Offene Bearbeitungsanfragen laden
        if ($canProcessEditRequests) {
            $pendingEditRequests = KassenbuchEditRequest::with(['entry', 'requester'])
                ->where('status', KassenbuchEditRequest::STATUS_PENDING)
                ->whereHas('entry', fn ($q) => $q->where('team_id', $team->id))
                ->orderBy('created_at', 'desc')
                ->get();
        }

        // Für das angemeldete Mitglied: Eigene Zahlungsdaten abrufen
        $memberData = $user;

        // Prüfen, ob Mitgliedschaft bald abläuft (innerhalb eines Monats)
        $renewalWarning = false;
        if ($user->bezahlt_bis) {
            $today = Carbon::now();
            $expiryDate = $user->bezahlt_bis instanceof Carbon
                ? $user->bezahlt_bis
                : Carbon::parse((string) $user->bezahlt_bis);
            $daysUntilExpiry = $today->diffInDays($expiryDate, false);

            if ($daysUntilExpiry > 0 && $daysUntilExpiry <= 30) {
                $renewalWarning = true;
            }
        }

        return view('kassenbuch.index', [
            'userRole' => $userRole,
            'canViewKassenbuch' => $canViewKassenbuch,
            'canManageKassenbuch' => $canManageKassenbuch,
            'canProcessEditRequests' => $canProcessEditRequests,
            'kassenstand' => $kassenstand,
            'members' => $members,
            'kassenbuchEntries' => $kassenbuchEntries,
            'pendingEditRequests' => $pendingEditRequests,
            'editReasonTypes' => KassenbuchEditReasonType::cases(),
            'memberData' => $memberData,
            'renewalWarning' => $renewalWarning,
        ]);
    }

    public function updatePaymentStatus(Request $request, User $user)
    {
        $data = $request->validate([
            'bezahlt_bis' => 'required|date',
            'mitgliedsbeitrag' => 'required|numeric|min:0',
            'mitglied_seit' => 'nullable|date',
        ]);

        $currentUser = Auth::user();
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();

        $this->authorize('manage', KassenbuchEntry::class);

        // Zahlungsdaten aktualisieren
        $user->update([
            'bezahlt_bis' => $data['bezahlt_bis'],
            'mitgliedsbeitrag' => $data['mitgliedsbeitrag'],
            'mitglied_seit' => $data['mitglied_seit'] ?? null,
        ]);

        return back()->with('status', 'Zahlungsdaten für '.$user->name.' wurden aktualisiert.');
    }

    public function addKassenbuchEntry(Request $request)
    {
        $data = $request->validate([
            'buchungsdatum' => 'required|date',
            'betrag' => 'required|numeric|not_in:0',
            'beschreibung' => 'required|string|max:255',
            'typ' => 'required|in:'.implode(',', KassenbuchEntryType::values()),
        ]);

        $user = Auth::user();
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();

        $this->authorize('manage', KassenbuchEntry::class);

        // Betrag anpassen (positiv für Einnahmen, negativ für Ausgaben)
        $amount = abs($data['betrag']);
        if ($data['typ'] === KassenbuchEntryType::Ausgabe->value) {
            $amount = -$amount;
        }

        // Neuen Eintrag erstellen
        DB::transaction(function () use ($team, $user, $data, $amount) {
            // Kassenbucheintrag erstellen
            KassenbuchEntry::create([
                'team_id' => $team->id,
                'created_by' => $user->id,
                'buchungsdatum' => $data['buchungsdatum'],
                'betrag' => $amount,
                'beschreibung' => $data['beschreibung'],
                'typ' => $data['typ'],
            ]);

            // Kassenstand aktualisieren
            $kassenstand = Kassenstand::where('team_id', $team->id)->first();
            $kassenstand->betrag += $amount;
            $kassenstand->letzte_aktualisierung = now();
            $kassenstand->save();
        });

        return back()->with('status', 'Kassenbucheintrag wurde hinzugefügt.');
    }

    /**
     * Request to edit a kassenbuch entry.
     */
    public function requestEdit(Request $request, KassenbuchEntry $entry)
    {
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();

        // Entry muss zum Team gehören
        if ($entry->team_id !== $team->id) {
            abort(404);
        }

        $this->authorize('requestEdit', $entry);

        $data = $request->validate([
            'reason_type' => 'required|in:'.implode(',', KassenbuchEditReasonType::values()),
            'reason_text' => 'nullable|string|max:500',
        ]);

        // Bei "Sonstiges" ist Freitext erforderlich (blank() lehnt auch whitespace-only ab)
        if ($data['reason_type'] === KassenbuchEditReasonType::Sonstiges->value && blank($data['reason_text'] ?? null)) {
            return back()->withErrors(['reason_text' => 'Bei "Sonstiges" ist eine Begründung erforderlich.']);
        }

        // Transaction mit Lock verhindert Race Conditions bei gleichzeitigen Anfragen
        $result = DB::transaction(function () use ($entry, $data) {
            // Lock den Entry um Race Conditions zu verhindern
            $lockedEntry = KassenbuchEntry::query()->lockForUpdate()->findOrFail($entry->id);

            // Prüfe erneut innerhalb der Transaction mit frischen Daten
            if ($lockedEntry->hasPendingEditRequest() || $lockedEntry->hasApprovedEditRequest()) {
                return ['error' => 'Für diesen Eintrag existiert bereits eine offene Bearbeitungsanfrage.'];
            }

            KassenbuchEditRequest::create([
                'kassenbuch_entry_id' => $lockedEntry->id,
                'requested_by' => Auth::id(),
                'reason_type' => $data['reason_type'],
                'reason_text' => $data['reason_text'] ?? null,
                'status' => KassenbuchEditRequest::STATUS_PENDING,
            ]);

            return ['success' => true];
        });

        if (isset($result['error'])) {
            return back()->withErrors(['entry' => $result['error']]);
        }

        return back()->with('status', 'Bearbeitungsanfrage wurde gestellt.');
    }

    /**
     * Approve an edit request.
     */
    public function approveEditRequest(KassenbuchEditRequest $editRequest)
    {
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();

        // Request muss zum Team gehören
        if ($editRequest->entry->team_id !== $team->id) {
            abort(404);
        }

        $this->authorize('processEditRequest', KassenbuchEntry::class);

        // Atomare Operation: Lock, prüfe Status, update
        $result = DB::transaction(function () use ($editRequest) {
            $lockedRequest = KassenbuchEditRequest::query()
                ->lockForUpdate()
                ->findOrFail($editRequest->id);

            if (! $lockedRequest->isPending()) {
                return ['error' => 'Diese Anfrage wurde bereits bearbeitet.'];
            }

            $lockedRequest->update([
                'status' => KassenbuchEditRequest::STATUS_APPROVED,
                'processed_by' => Auth::id(),
                'processed_at' => now(),
            ]);

            return ['success' => true];
        });

        if (isset($result['error'])) {
            return back()->withErrors(['request' => $result['error']]);
        }

        return back()->with('status', 'Bearbeitung wurde freigegeben.');
    }

    /**
     * Reject an edit request.
     */
    public function rejectEditRequest(Request $request, KassenbuchEditRequest $editRequest)
    {
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();

        // Request muss zum Team gehören
        if ($editRequest->entry->team_id !== $team->id) {
            abort(404);
        }

        $this->authorize('processEditRequest', KassenbuchEntry::class);

        $data = $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        // Atomare Operation: Lock, prüfe Status, update
        $result = DB::transaction(function () use ($editRequest, $data) {
            $lockedRequest = KassenbuchEditRequest::query()
                ->lockForUpdate()
                ->findOrFail($editRequest->id);

            if (! $lockedRequest->isPending()) {
                return ['error' => 'Diese Anfrage wurde bereits bearbeitet.'];
            }

            $lockedRequest->update([
                'status' => KassenbuchEditRequest::STATUS_REJECTED,
                'processed_by' => Auth::id(),
                'processed_at' => now(),
                'rejection_reason' => $data['rejection_reason'] ?? null,
            ]);

            return ['success' => true];
        });

        if (isset($result['error'])) {
            return back()->withErrors(['request' => $result['error']]);
        }

        return back()->with('status', 'Bearbeitungsanfrage wurde abgelehnt.');
    }

    /**
     * Update a kassenbuch entry (after approval).
     */
    public function updateEntry(Request $request, KassenbuchEntry $entry)
    {
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();

        // Entry muss zum Team gehören
        if ($entry->team_id !== $team->id) {
            abort(404);
        }

        $this->authorize('edit', $entry);

        $data = $request->validate([
            'buchungsdatum' => 'required|date',
            'betrag' => 'required|numeric|not_in:0',
            'beschreibung' => 'required|string|max:255',
            'typ' => 'required|in:'.implode(',', KassenbuchEntryType::values()),
        ]);

        $kassenstand = Kassenstand::where('team_id', $team->id)->firstOrFail();

        DB::transaction(function () use ($entry, $data, $kassenstand) {
            // Alten Betrag vom Kassenstand abziehen
            $kassenstand->betrag -= $entry->betrag;

            // Neuen Betrag berechnen
            $newAmount = abs($data['betrag']);
            if ($data['typ'] === KassenbuchEntryType::Ausgabe->value) {
                $newAmount = -$newAmount;
            }

            // Begründung aus der Freigabe-Anfrage holen (innerhalb Transaction für Konsistenz)
            $editRequest = $entry->approvedEditRequest()->lockForUpdate()->firstOrFail();
            $editReason = $editRequest->getFormattedReason();

            // Eintrag aktualisieren
            $entry->update([
                'buchungsdatum' => $data['buchungsdatum'],
                'betrag' => $newAmount,
                'beschreibung' => $data['beschreibung'],
                'typ' => $data['typ'],
                'last_edited_by' => Auth::id(),
                'last_edited_at' => now(),
                'last_edit_reason' => $editReason,
            ]);

            // Neuen Betrag zum Kassenstand addieren
            $kassenstand->betrag += $newAmount;
            $kassenstand->letzte_aktualisierung = now();
            $kassenstand->save();

            // Freigabe-Anfrage löschen
            $editRequest->delete();
        });

        return back()->with('status', 'Kassenbucheintrag wurde aktualisiert.');
    }
}
