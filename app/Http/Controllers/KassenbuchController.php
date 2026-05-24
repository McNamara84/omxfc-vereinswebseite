<?php

namespace App\Http\Controllers;

use App\Enums\KassenbuchEditReasonType;
use App\Enums\KassenbuchEditRequestType;
use App\Enums\KassenbuchEntryType;
use App\Enums\Role;
use App\Http\Requests\KassenbuchEntryRequest;
use App\Mail\KassenbuchDeleteApproved;
use App\Mail\KassenbuchDeleteRejected;
use App\Mail\KassenbuchDeleteRequestSubmitted;
use App\Models\KassenbuchEditRequest;
use App\Models\KassenbuchEntry;
use App\Models\Kassenstand;
use App\Models\Team;
use App\Models\User;
use App\Services\KassenbuchService;
use App\Services\MembersTeamProvider;
use App\Services\UserRoleService;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

#[Middleware(middleware: 'vorstand-or-kassenwart', except: ['kassenstand'])]
class KassenbuchController extends Controller
{
    public function __construct(
        private UserRoleService $userRoleService,
        private MembersTeamProvider $membersTeamProvider,
        private KassenbuchService $kassenbuchService,
    ) {}

    public function kassenstand()
    {
        $user = Auth::user();
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();

        $kassenstand = $this->kassenbuchService->getOrCreateKassenstand($team);
        $renewalWarning = $this->kassenbuchService->checkRenewalWarning($user);

        return view('kassenbuch.kassenstand', [
            'kassenstand' => $kassenstand,
            'memberData' => $user,
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

    public function addKassenbuchEntry(KassenbuchEntryRequest $request)
    {
        $data = $request->validated();

        $user = Auth::user();
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();

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

            // Kassenstand atomar aktualisieren
            $kassenstand = Kassenstand::where('team_id', $team->id)->lockForUpdate()->first()
                ?? $this->kassenbuchService->getOrCreateKassenstand($team);
            $kassenstand->increment('betrag', $amount);
            $kassenstand->update(['letzte_aktualisierung' => now()]);
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
        $this->abortIfEntryNotInTeam($entry, $team);

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
            if ($lockedEntry->hasPendingRequest() || $lockedEntry->hasApprovedRequest()) {
                return ['error' => 'Für diesen Eintrag existiert bereits eine offene oder freigegebene Anfrage.'];
            }

            KassenbuchEditRequest::create([
                'kassenbuch_entry_id' => $lockedEntry->id,
                'requested_by' => Auth::id(),
                'reason_type' => $data['reason_type'],
                'reason_text' => $data['reason_text'] ?? null,
                'request_type' => KassenbuchEditRequestType::Edit->value,
                'status' => KassenbuchEditRequest::STATUS_PENDING,
            ]);

            return ['success' => true];
        });

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        return back()->with('status', 'Bearbeitungsanfrage wurde gestellt.');
    }

    /**
     * Request to delete a kassenbuch entry.
     */
    public function requestDelete(Request $request, KassenbuchEntry $entry)
    {
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();

        $this->abortIfEntryNotInTeam($entry, $team);

        $this->authorize('requestDelete', $entry);

        $data = $request->validate([
            'reason_text' => 'required|string|max:500',
        ]);

        if (blank($data['reason_text'] ?? null)) {
            return back()->withErrors(['reason_text' => 'Für die Löschanfrage ist eine Begründung erforderlich.']);
        }

        $result = DB::transaction(function () use ($entry, $data) {
            $lockedEntry = KassenbuchEntry::query()->lockForUpdate()->findOrFail($entry->id);

            if ($lockedEntry->hasPendingRequest() || $lockedEntry->hasApprovedRequest()) {
                return ['error' => 'Für diesen Eintrag existiert bereits eine offene oder freigegebene Anfrage.'];
            }

            KassenbuchEditRequest::create([
                'kassenbuch_entry_id' => $lockedEntry->id,
                'requested_by' => Auth::id(),
                'reason_type' => KassenbuchEditReasonType::Sonstiges->value,
                'reason_text' => $data['reason_text'],
                'request_type' => KassenbuchEditRequestType::Delete->value,
                'status' => KassenbuchEditRequest::STATUS_PENDING,
            ]);

            return [
                'entry_snapshot' => $this->entrySnapshot($lockedEntry),
                'reason_text' => $data['reason_text'],
            ];
        });

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        $this->queueDeleteRequestSubmittedMail(
            $team,
            Auth::user(),
            $result['entry_snapshot'],
            $result['reason_text'],
        );

        return back()->with('status', 'Löschanfrage wurde gestellt.');
    }

    /**
     * Approve an edit request.
     */
    public function approveEditRequest(KassenbuchEditRequest $editRequest)
    {
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();

        // Request muss zum Team gehören
        $this->abortIfRequestNotInTeam($editRequest, $team);

        $this->authorize('processEditRequest', KassenbuchEntry::class);

        // Atomare Operation: Lock, prüfe Status, update
        $result = DB::transaction(function () use ($editRequest, $team) {
            $lockedRequest = KassenbuchEditRequest::query()
                ->with('requester')
                ->lockForUpdate()
                ->findOrFail($editRequest->id);

            if (! $lockedRequest->isPending()) {
                return ['error' => 'Diese Anfrage wurde bereits bearbeitet.'];
            }

            if ($lockedRequest->isDeleteRequest()) {
                $lockedEntry = KassenbuchEntry::query()->lockForUpdate()->findOrFail($lockedRequest->kassenbuch_entry_id);
                $kassenstand = Kassenstand::where('team_id', $team->id)->lockForUpdate()->firstOrFail();
                $entrySnapshot = $this->entrySnapshot($lockedEntry);

                $lockedRequest->update([
                    'status' => KassenbuchEditRequest::STATUS_APPROVED,
                    'processed_by' => Auth::id(),
                    'processed_at' => now(),
                ]);

                $kassenstand->betrag -= $lockedEntry->betrag;
                $kassenstand->letzte_aktualisierung = now();
                $kassenstand->save();

                $lockedEntry->delete();

                return [
                    'success' => true,
                    'message' => 'Löschung wurde freigegeben und durchgeführt.',
                    'request_type' => KassenbuchEditRequestType::Delete->value,
                    'entry_snapshot' => $entrySnapshot,
                    'reason_text' => $lockedRequest->reason_text ?? '',
                    'requester' => $lockedRequest->requester,
                ];
            }

            $lockedRequest->update([
                'status' => KassenbuchEditRequest::STATUS_APPROVED,
                'processed_by' => Auth::id(),
                'processed_at' => now(),
            ]);

            return [
                'success' => true,
                'message' => 'Bearbeitung wurde freigegeben.',
                'request_type' => KassenbuchEditRequestType::Edit->value,
            ];
        });

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        if (($result['request_type'] ?? null) === KassenbuchEditRequestType::Delete->value) {
            $this->queueDeleteApprovedMail(
                $team,
                $result['requester'],
                Auth::user(),
                $result['entry_snapshot'],
                $result['reason_text'],
            );
        }

        return back()->with('status', $result['message']);
    }

    /**
     * Reject an edit request.
     */
    public function rejectEditRequest(Request $request, KassenbuchEditRequest $editRequest)
    {
        $team = $this->membersTeamProvider->getMembersTeamOrAbort();

        // Request muss zum Team gehören
        $this->abortIfRequestNotInTeam($editRequest, $team);

        $this->authorize('processEditRequest', KassenbuchEntry::class);

        $data = $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        // Atomare Operation: Lock, prüfe Status, update
        $result = DB::transaction(function () use ($editRequest, $data) {
            $lockedRequest = KassenbuchEditRequest::query()
                ->with('requester')
                ->lockForUpdate()
                ->findOrFail($editRequest->id);

            if (! $lockedRequest->isPending()) {
                return ['error' => 'Diese Anfrage wurde bereits bearbeitet.'];
            }

            $entrySnapshot = null;

            if ($lockedRequest->isDeleteRequest()) {
                $lockedEntry = KassenbuchEntry::query()->lockForUpdate()->findOrFail($lockedRequest->kassenbuch_entry_id);
                $entrySnapshot = $this->entrySnapshot($lockedEntry);
            }

            $lockedRequest->update([
                'status' => KassenbuchEditRequest::STATUS_REJECTED,
                'processed_by' => Auth::id(),
                'processed_at' => now(),
                'rejection_reason' => $data['rejection_reason'] ?? null,
            ]);

            return [
                'success' => true,
                'message' => $lockedRequest->isDeleteRequest()
                    ? 'Löschanfrage wurde abgelehnt.'
                    : 'Bearbeitungsanfrage wurde abgelehnt.',
                'request_type' => $lockedRequest->request_type->value,
                'entry_snapshot' => $entrySnapshot,
                'reason_text' => $lockedRequest->reason_text,
                'requester' => $lockedRequest->requester,
            ];
        });

        if (isset($result['error'])) {
            return back()->with('error', $result['error']);
        }

        if (($result['request_type'] ?? null) === KassenbuchEditRequestType::Delete->value) {
            $this->queueDeleteRejectedMail(
                $result['requester'],
                Auth::user(),
                $result['entry_snapshot'],
                $result['reason_text'] ?? '',
                $data['rejection_reason'] ?? null,
            );
        }

        return back()->with('status', $result['message']);
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

        DB::transaction(function () use ($entry, $data, $team) {
            // Lock Entry und Kassenstand innerhalb der Transaction für Konsistenz
            $lockedEntry = KassenbuchEntry::query()->lockForUpdate()->findOrFail($entry->id);
            $kassenstand = Kassenstand::where('team_id', $team->id)->lockForUpdate()->firstOrFail();

            // Alten Betrag vom Kassenstand abziehen
            $kassenstand->betrag -= $lockedEntry->betrag;

            // Neuen Betrag berechnen
            $newAmount = abs($data['betrag']);
            if ($data['typ'] === KassenbuchEntryType::Ausgabe->value) {
                $newAmount = -$newAmount;
            }

            // Begründung aus der Freigabe-Anfrage holen (innerhalb Transaction für Konsistenz)
            $editRequest = $lockedEntry->approvedEditRequest()->lockForUpdate()->firstOrFail();
            $editReason = $editRequest->getFormattedReason();

            // Eintrag aktualisieren
            $lockedEntry->update([
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

    private function abortIfEntryNotInTeam(KassenbuchEntry $entry, Team $team): void
    {
        if ($entry->team_id !== $team->id) {
            abort(404);
        }
    }

    private function abortIfRequestNotInTeam(KassenbuchEditRequest $editRequest, Team $team): void
    {
        $exists = KassenbuchEntry::withTrashed()
            ->whereKey($editRequest->kassenbuch_entry_id)
            ->where('team_id', $team->id)
            ->exists();

        if (! $exists) {
            abort(404);
        }
    }

    /**
     * @return array{id:int,beschreibung:string,buchungsdatum:string,typ:string,typ_label:string,betrag:float,betrag_formatiert:string}
     */
    private function entrySnapshot(KassenbuchEntry $entry): array
    {
        $amount = (float) $entry->betrag;

        return [
            'id' => $entry->id,
            'beschreibung' => $entry->beschreibung,
            'buchungsdatum' => $entry->buchungsdatum->format('d.m.Y'),
            'typ' => $entry->typ->value,
            'typ_label' => $entry->typ === KassenbuchEntryType::Einnahme ? 'Einnahme' : 'Ausgabe',
            'betrag' => $amount,
            'betrag_formatiert' => number_format(abs($amount), 2, ',', '.').' €',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function vorstandEmails(Team $team): array
    {
        return $team->activeUsers()
            ->wherePivot('role', Role::Vorstand->value)
            ->whereNotNull('users.email')
            ->pluck('users.email')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function queueDeleteRequestSubmittedMail(Team $team, User $requester, array $entrySnapshot, string $reasonText): void
    {
        $recipients = $this->vorstandEmails($team);

        if ($recipients === []) {
            return;
        }

        Mail::to($recipients)->queue(new KassenbuchDeleteRequestSubmitted($requester, $entrySnapshot, $reasonText));
    }

    private function queueDeleteApprovedMail(Team $team, User $requester, User $processor, array $entrySnapshot, string $reasonText): void
    {
        $recipients = collect($this->vorstandEmails($team))
            ->push($requester->email)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($recipients === []) {
            return;
        }

        Mail::to($recipients)->queue(new KassenbuchDeleteApproved($requester, $processor, $entrySnapshot, $reasonText));
    }

    private function queueDeleteRejectedMail(User $requester, User $processor, array $entrySnapshot, string $reasonText, ?string $rejectionReason): void
    {
        if (blank($requester->email)) {
            return;
        }

        Mail::to($requester->email)->queue(new KassenbuchDeleteRejected(
            $requester,
            $processor,
            $entrySnapshot,
            $reasonText,
            $rejectionReason,
        ));
    }
}
