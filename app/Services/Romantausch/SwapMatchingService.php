<?php

namespace App\Services\Romantausch;

use App\Mail\BookSwapMatched;
use App\Models\Activity;
use App\Models\BookOffer;
use App\Models\BookRequest;
use App\Models\BookSwap;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Service für das Matching von Tauschpartnern in der Romantauschbörse.
 *
 * Verantwortlich für:
 * - Finden von passenden Angeboten/Gesuchen
 * - Erkennen von reziproken Tausch-Möglichkeiten
 * - Erstellen von BookSwap-Einträgen
 * - Benachrichtigung der beteiligten Nutzer
 */
class SwapMatchingService
{
    /**
     * Versucht einen Match für ein neues Angebot oder Gesuch zu finden.
     *
     * Prüft auf reziproke Tausch-Möglichkeiten: Beide Parteien haben jeweils
     * etwas, das die andere Partei sucht.
     *
     * @param  Model  $model  BookOffer oder BookRequest
     * @param  string  $type  'offer' oder 'request'
     */
    public function matchSwap(Model $model, string $type): void
    {
        if ($type === 'offer') {
            $this->matchOffer($model);
        } else {
            $this->matchRequest($model);
        }
    }

    /**
     * Sucht passende Gesuche für ein Angebot.
     */
    private function matchOffer(BookOffer $offer): void
    {
        $potentialRequests = BookRequest::where('book_number', $offer->book_number)
            ->where('series', $offer->series)
            ->where('completed', false)
            ->where('user_id', '!=', $offer->user_id)
            ->doesntHave('swap')
            ->get();

        foreach ($potentialRequests as $request) {
            if ($this->attemptReciprocalSwap($offer, $request)) {
                break;
            }
        }
    }

    /**
     * Sucht passende Angebote für ein Gesuch.
     */
    private function matchRequest(BookRequest $request): void
    {
        $potentialOffers = BookOffer::where('book_number', $request->book_number)
            ->where('series', $request->series)
            ->where('completed', false)
            ->where('user_id', '!=', $request->user_id)
            ->doesntHave('swap')
            ->get();

        foreach ($potentialOffers as $offer) {
            if ($this->attemptReciprocalSwap($offer, $request)) {
                break;
            }
        }
    }

    /**
     * Versucht einen reziproken Tausch zu erstellen.
     *
     * Ein reziproker Tausch liegt vor, wenn:
     * - User A bietet Roman X an, den User B sucht
     * - User B bietet Roman Y an, den User A sucht
     *
     * @return bool True wenn ein reziproker Tausch erstellt wurde
     */
    private function attemptReciprocalSwap(BookOffer $offer, BookRequest $request): bool
    {
        if ($offer->user_id === $request->user_id) {
            return false;
        }

        // Gesuche des Angebot-Besitzers
        $offerOwnerRequests = BookRequest::where('user_id', $offer->user_id)
            ->where('completed', false)
            ->doesntHave('swap')
            ->get()
            ->keyBy(fn ($item) => $this->buildBookKey($item->series, (int) $item->book_number));

        if ($offerOwnerRequests->isEmpty()) {
            return false;
        }

        // Angebote des Gesuch-Besitzers
        $requestOwnerOffers = BookOffer::where('user_id', $request->user_id)
            ->where('completed', false)
            ->doesntHave('swap')
            ->get()
            ->keyBy(fn ($item) => $this->buildBookKey($item->series, (int) $item->book_number));

        if ($requestOwnerOffers->isEmpty()) {
            return false;
        }

        // Finde Überschneidungen: Bücher die A sucht UND B anbietet
        $matchingEntries = $offerOwnerRequests->intersectByKeys($requestOwnerOffers);

        if ($matchingEntries->isEmpty()) {
            return false;
        }

        $matchingKey = $matchingEntries->keys()->first();

        $reciprocalRequest = $offerOwnerRequests->get($matchingKey);
        $reciprocalOffer = $requestOwnerOffers->get($matchingKey);

        if (! $reciprocalRequest || ! $reciprocalOffer) {
            return false;
        }

        // Beide Swaps in einer Transaktion erstellen
        [$firstSwap, $secondSwap] = DB::transaction(function () use ($offer, $request, $reciprocalOffer, $reciprocalRequest) {
            $primarySwap = BookSwap::create([
                'offer_id' => $offer->id,
                'request_id' => $request->id,
            ]);

            $reciprocalSwap = BookSwap::create([
                'offer_id' => $reciprocalOffer->id,
                'request_id' => $reciprocalRequest->id,
            ]);

            return [$primarySwap, $reciprocalSwap];
        });

        // Benachrichtigungen versenden
        $this->notifySwapParticipants($firstSwap, $request->user);
        $this->notifySwapParticipants($secondSwap, $reciprocalRequest->user);

        return true;
    }

    /**
     * Benachrichtigt einen Teilnehmer über einen neuen Swap.
     */
    private function notifySwapParticipants(BookSwap $swap, User $recipient): void
    {
        try {
            Mail::to($recipient->email)->queue(new BookSwapMatched($swap));
        } catch (\Throwable $e) {
            Log::error('Swap-Benachrichtigung fehlgeschlagen', [
                'swap_id' => $swap->id,
                'recipient_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Erstellt einen eindeutigen Key für ein Buch.
     */
    public function buildBookKey(string $series, int $bookNumber): string
    {
        return sprintf('%s::%d', $series, $bookNumber);
    }

    /**
     * Löscht einen Swap und erstellt ein Activity-Log für den betroffenen Nutzer.
     *
     * @param  BookSwap  $swap  Der zu löschende Swap
     */
    public function deleteSwapWithActivity(BookSwap $swap): void
    {
        $affectedUser = $swap->request?->user;

        if ($affectedUser && User::where('id', $affectedUser->id)->exists()) {
            try {
                Activity::create([
                    'user_id' => $affectedUser->id,
                    'subject_type' => BookRequest::class,
                    'subject_id' => $swap->request_id,
                    'action' => 'match_cancelled_by_offer_owner',
                ]);
            } catch (\Illuminate\Database\QueryException $e) {
                $this->handleActivityCreationError($e, $swap, $affectedUser);
            }
        }

        $swap->delete();
    }

    /**
     * Behandelt Fehler bei der Activity-Erstellung.
     */
    private function handleActivityCreationError(
        \Illuminate\Database\QueryException $e,
        BookSwap $swap,
        User $affectedUser
    ): void {
        // Prüfe ob es sich um eine FK-Constraint-Verletzung handelt
        $isForeignKeyError = in_array($e->getCode(), ['23000', '23503', 1452, 19], false)
            || str_contains(strtolower($e->getMessage()), 'foreign key');

        if ($isForeignKeyError) {
            Log::warning('Activity-Log für gelöschten Swap fehlgeschlagen (FK)', [
                'swap_id' => $swap->id,
                'affected_user_id' => $affectedUser->id,
                'error' => $e->getMessage(),
            ]);
        } else {
            throw $e;
        }
    }

    /**
     * Bestätigt einen Tausch durch einen Nutzer.
     *
     * @return array{completed: bool, points_awarded: bool}
     */
    public function confirmSwap(BookSwap $swap, User $user): array
    {
        $result = [
            'completed' => false,
            'points_awarded' => false,
        ];

        if ($user->is($swap->offer->user)) {
            $swap->offer_confirmed = true;
        }

        if ($user->is($swap->request->user)) {
            $swap->request_confirmed = true;
        }

        $swap->save();

        // Prüfen ob beide Seiten bestätigt haben
        if ($swap->offer_confirmed && $swap->request_confirmed && ! $swap->completed_at) {
            $swap->completed_at = now();
            $swap->save();

            $swap->offer->update(['completed' => true]);
            $swap->request->update(['completed' => true]);

            // Punkte vergeben
            $swap->offer->user->incrementTeamPoints(2);
            $swap->request->user->incrementTeamPoints(2);

            $result['completed'] = true;
            $result['points_awarded'] = true;
        }

        return $result;
    }

    /**
     * Schließt einen Tausch direkt ab (z.B. durch Admin).
     */
    public function completeSwap(BookOffer $offer, BookRequest $request): BookSwap
    {
        $swap = BookSwap::create([
            'offer_id' => $offer->id,
            'request_id' => $request->id,
            'completed_at' => now(),
        ]);

        $offer->update(['completed' => true]);
        $request->update(['completed' => true]);

        return $swap;
    }
}
