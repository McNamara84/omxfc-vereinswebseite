<?php

namespace App\Actions\Romantausch;

use App\Models\Activity;
use App\Models\BookRequest;
use App\Models\User;
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
use Throwable;

final class RequestWorkflow
{
    public function __construct(private readonly MutationLock $lock, private readonly RomantauschBaxxService $baxx, private readonly CommittedMatching $matching, private readonly SourceDeletion $deletion) {}

    public function create(User $actor, array $data): MutationResult
    {
        return $this->save($actor, $data);
    }

    public function update(User $actor, int $id, array $data): MutationResult
    {
        Gate::forUser($actor)->authorize('update', BookRequest::findOrFail($id));

        return $this->save($actor, $data, $id);
    }

    private function save(User $actor, array $data, ?int $id = null): MutationResult
    {
        $data = InputRules::validate($data, InputRules::single(), true);
        $book = InputRules::book($data);
        try {
            return $this->lock->run([$actor->id], function () use ($actor, $data, $book, $id): MutationResult {
                $request = $id ? BookRequest::lockForUpdate()->findOrFail($id) : new BookRequest(['user_id' => $actor->id]);
                if ($id) {
                    Gate::forUser($actor)->authorize('update', $request);
                    InputRules::editable((bool) $request->completed || $request->swap()->lockForUpdate()->first() !== null, 'book_number', 'Gesuche');
                }
                $request->fill(['series' => $data['series'], 'book_number' => $data['book_number'], 'book_title' => $book->title, 'condition' => $data['condition']])->save();
                if (! $id) {
                    $this->baxx->awardForNewRequests($actor->id);
                    Activity::create(['user_id' => $actor->id, 'subject_type' => BookRequest::class, 'subject_id' => $request->id]);
                }
                $result = new MutationResult($request);
                $this->matching->schedule([$request], $result);

                return $result;
            });
        } catch (ValidationException|AuthorizationException|ModelNotFoundException $error) {
            throw $error;
        } catch (Throwable $error) {
            report($error);
            throw new WorkflowFailure($id ? 'Gesuch konnte aktuell nicht aktualisiert werden. Bitte versuche es später erneut.' : 'Gesuch konnte aktuell nicht erstellt werden. Bitte versuche es später erneut.', 'book_number', $error);
        }
    }

    public function delete(User $actor, int $id): void
    {
        Gate::forUser($actor)->authorize('delete', BookRequest::findOrFail($id));
        $this->lock->run([$actor->id], function () use ($actor, $id): void {
            $request = BookRequest::lockForUpdate()->findOrFail($id);
            Gate::forUser($actor)->authorize('delete', $request);
            $this->deletion->delete($request);
        });
    }
}
