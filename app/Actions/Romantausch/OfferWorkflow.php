<?php

namespace App\Actions\Romantausch;

use App\Models\Activity;
use App\Models\BookOffer;
use App\Models\User;
use App\Services\Romantausch\BookPhotoService;
use App\Services\Romantausch\CommittedMatching;
use App\Services\Romantausch\MutationLock;
use App\Services\Romantausch\RomantauschBaxxService;
use App\Services\Romantausch\SourceDeletion;
use App\Support\Romantausch\InputRules;
use App\Support\Romantausch\MutationResult;
use App\Support\Romantausch\WorkflowFailure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class OfferWorkflow
{
    public function __construct(private readonly MutationLock $lock, private readonly BookPhotoService $photos, private readonly RomantauschBaxxService $baxx, private readonly CommittedMatching $matching, private readonly SourceDeletion $deletion) {}

    public function create(User $actor, array $data): MutationResult
    {
        return $this->save($actor, $data);
    }

    public function update(User $actor, int $id, array $data): MutationResult
    {
        Gate::forUser($actor)->authorize('update', BookOffer::findOrFail($id));

        return $this->save($actor, $data, $id);
    }

    private function save(User $actor, array $data, ?int $id = null): MutationResult
    {
        $data = InputRules::validate($data, InputRules::single(true));
        $book = InputRules::book($data);
        $uploaded = $this->photos->uploadForMutation($data['photos'] ?? [], $actor->id);
        try {
            $result = $this->lock->run([$actor->id], function () use ($actor, $data, $book, $uploaded, $id): MutationResult {
                $offer = $id ? BookOffer::lockForUpdate()->findOrFail($id) : new BookOffer(['user_id' => $actor->id]);
                if ($id) {
                    Gate::forUser($actor)->authorize('update', $offer);
                    InputRules::editable((bool) $offer->completed || $offer->swap()->lockForUpdate()->first() !== null, 'offer', 'Angebote');
                }
                $old = $offer->photos;
                $paths = $this->photos->combine($old, $data['remove_photos'] ?? [], $uploaded);
                $offer->fill(['series' => $data['series'], 'book_number' => $data['book_number'], 'book_title' => $book->title, 'condition' => $data['condition'], 'photos' => $paths])->save();
                if (! $id) {
                    $this->baxx->awardForNewOffers($actor->id, 1);
                    Activity::create(['user_id' => $actor->id, 'subject_type' => BookOffer::class, 'subject_id' => $offer->id]);
                }
                $this->photos->removeUnreferencedAfterCommit(array_values(array_diff($old, $paths)));
                $result = new MutationResult($offer);
                $this->matching->schedule([$offer], $result);

                return $result;
            });
        } catch (Throwable $error) {
            $this->photos->deletePhotos($uploaded);
            if ($error instanceof ValidationException || $error instanceof AuthorizationException || $error instanceof HttpExceptionInterface || $error instanceof ModelNotFoundException) {
                throw $error;
            }
            report($error);
            throw new WorkflowFailure($id ? 'Angebot konnte aktuell nicht aktualisiert werden. Bitte versuche es später erneut.' : 'Angebot konnte aktuell nicht erstellt werden. Bitte versuche es später erneut.', 'book_number', $error);
        }
        $this->photos->cleanupOnOuterRollback($uploaded);

        return $result;
    }

    public function delete(User $actor, int $id): void
    {
        Gate::forUser($actor)->authorize('delete', BookOffer::findOrFail($id));
        $this->lock->run([$actor->id], function () use ($actor, $id): void {
            $offer = BookOffer::lockForUpdate()->findOrFail($id);
            Gate::forUser($actor)->authorize('delete', $offer);
            $paths = $offer->photos;
            $this->deletion->delete($offer);
            $this->photos->removeUnreferencedAfterCommit($paths);
        });
    }
}
