<?php

namespace App\Actions\Romantausch;

use App\Models\Activity;
use App\Models\BookOffer;
use App\Models\BookSwap;
use App\Models\User;
use App\Services\Romantausch\BookPhotoService;
use App\Services\Romantausch\BundleService;
use App\Services\Romantausch\CommittedMatching;
use App\Services\Romantausch\MutationLock;
use App\Services\Romantausch\RomantauschBaxxService;
use App\Services\Romantausch\SourceDeletion;
use App\Support\Romantausch\InputRules;
use App\Support\Romantausch\MutationResult;
use App\Support\Romantausch\WorkflowFailure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class BundleWorkflow
{
    public function __construct(private readonly MutationLock $lock, private readonly BookPhotoService $photos, private readonly BundleService $bundles, private readonly RomantauschBaxxService $baxx, private readonly CommittedMatching $matching, private readonly SourceDeletion $deletion) {}

    public function create(User $actor, array $data): MutationResult
    {
        return $this->save($actor, $data);
    }

    public function update(User $actor, string $id, array $data): MutationResult
    {
        return $this->save($actor, $data, $id);
    }

    private function save(User $actor, array $data, ?string $id = null): MutationResult
    {
        $series = $id ? $this->offers($actor, $id)->first()->series : ($data['series'] ?? '');
        $data = InputRules::validate($data, InputRules::bundle($id !== null));
        $numbers = $this->bundles->parseBookNumbers($data['book_numbers']);
        if (count($numbers) < BundleService::MIN_BUNDLE_SIZE || count($numbers) > BundleService::MAX_BUNDLE_SIZE) {
            throw ValidationException::withMessages(['book_numbers' => count($numbers) < BundleService::MIN_BUNDLE_SIZE
                ? 'Ein Stapel-Angebot muss mindestens '.BundleService::MIN_BUNDLE_SIZE.' Romane enthalten.'
                : 'Ein Stapel-Angebot darf maximal '.BundleService::MAX_BUNDLE_SIZE.' Romane enthalten.']);
        }
        if ($error = $this->bundles->validateConditionRange($data['condition'], $data['condition_max'] ?? null)) {
            throw ValidationException::withMessages(['condition_max' => $error]);
        }
        $books = $this->bundles->getExistingBooks($series, $numbers);
        if ($missing = $this->bundles->validateMissingBookNumbers($numbers, $books->keys()->all())) {
            throw ValidationException::withMessages(['book_numbers' => "Folgende Roman-Nummern existieren nicht in der Serie \"{$series}\": {$missing}"]);
        }
        $uploaded = $this->photos->uploadForMutation($data['photos'] ?? [], $actor->id);
        try {
            $result = $this->lock->run([$actor->id], function () use ($actor, $data, $id, $series, $numbers, $books, $uploaded): MutationResult {
                $offers = $id ? $this->offers($actor, $id, true) : new Collection;
                $assignedOffers = BookSwap::whereIn('offer_id', $offers->modelKeys())->orderBy('id')->lockForUpdate()->pluck('offer_id')->all();
                foreach ($offers as $offer) {
                    Gate::forUser($actor)->authorize('update', $offer);
                    InputRules::editable((bool) $offer->completed || in_array($offer->id, $assignedOffers), 'book_numbers', 'Stapel');
                }
                $oldPhotos = $offers->flatMap(fn ($offer) => $offer->photos)->unique()->values()->all();
                $paths = $this->photos->combine($oldPhotos, $data['remove_photos'] ?? [], $uploaded);
                $bundleId = $id ?? (string) Str::uuid();
                $current = $offers->pluck('book_number')->all();
                $added = array_diff($numbers, $current);
                $removed = 0;
                $updated = 0;
                foreach ($offers as $offer) {
                    if (! in_array($offer->book_number, $numbers)) {
                        $this->deletion->delete($offer);
                        $removed++;
                    } else {
                        $offer->update(['condition' => $data['condition'], 'condition_max' => $data['condition_max'] ?? null, 'photos' => $paths]);
                        $updated++;
                    }
                }
                $newOffers = [];
                foreach ($added as $number) {
                    $newOffers[] = BookOffer::create(['user_id' => $actor->id, 'bundle_id' => $bundleId, 'series' => $series,
                        'book_number' => $number, 'book_title' => $books[$number]->title, 'condition' => $data['condition'],
                        'condition_max' => $data['condition_max'] ?? null, 'photos' => $paths]);
                }
                if ($newOffers !== []) {
                    $this->baxx->awardForNewOffers($actor->id, count($newOffers));
                }
                $model = $newOffers[0] ?? $offers->first(fn ($offer) => $offer->exists);
                if (! $id) {
                    Activity::create(['user_id' => $actor->id, 'subject_type' => BookOffer::class, 'subject_id' => $model->id, 'action' => 'bundle_created']);
                }
                $this->photos->removeUnreferencedAfterCommit(array_values(array_diff($oldPhotos, $paths)));
                $result = new MutationResult($model, ['bundle_id' => $bundleId, 'count' => count($numbers), 'added' => count($added), 'removed' => $removed, 'updated' => $updated]);
                $this->matching->schedule($newOffers, $result);

                return $result;
            });
        } catch (Throwable $error) {
            $this->photos->deletePhotos($uploaded);
            if ($error instanceof ValidationException || $error instanceof AuthorizationException || $error instanceof ModelNotFoundException || $error instanceof HttpExceptionInterface) {
                throw $error;
            }
            report($error);
            throw new WorkflowFailure($id ? 'Beim Aktualisieren des Stapel-Angebots ist ein Fehler aufgetreten.' : 'Beim Erstellen des Stapel-Angebots ist ein Fehler aufgetreten.', 'book_numbers', $error);
        }
        $this->photos->cleanupOnOuterRollback($uploaded);

        return $result;
    }

    private function offers(User $actor, string $id, bool $lock = false): Collection
    {
        $query = BookOffer::where('bundle_id', $id)->where('user_id', $actor->id)->orderBy('id');
        $offers = ($lock ? $query->lockForUpdate() : $query)->get();
        abort_if($offers->isEmpty(), 404);

        return $offers;
    }

    public function delete(User $actor, string $id): int
    {
        return $this->lock->run([$actor->id], function () use ($actor, $id): int {
            $offers = $this->offers($actor, $id, true);
            $paths = $offers->flatMap(fn ($offer) => $offer->photos)->unique()->values()->all();
            foreach ($offers as $offer) {
                Gate::forUser($actor)->authorize('delete', $offer);
                $this->deletion->delete($offer);
            }
            $this->photos->removeUnreferencedAfterCommit($paths);

            return $offers->count();
        });
    }
}
