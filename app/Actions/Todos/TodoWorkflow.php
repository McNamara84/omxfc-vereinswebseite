<?php

namespace App\Actions\Todos;

use App\Enums\TodoStatus;
use App\Models\Activity;
use App\Models\Todo;
use App\Models\User;
use App\Services\Baxx\BaxxBookingWriter;
use App\Services\MembersTeamProvider;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class TodoWorkflow
{
    public function __construct(private readonly MembersTeamProvider $teams, private readonly BaxxBookingWriter $writer) {}

    public static function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'points' => ['required', 'integer', 'min:1', 'max:1000'],
            'category_id' => ['required', 'exists:todo_categories,id'],
        ];
    }

    public function create(User $actor, array $data): Todo
    {
        Gate::forUser($actor)->authorize('create', Todo::class);
        $validated = Validator::make($data, self::rules())->validate();

        return Todo::create([
            ...$validated,
            'team_id' => $this->teams->getMembersTeamOrAbort()->id,
            'created_by' => $actor->id,
            'status' => TodoStatus::Open,
        ]);
    }

    public function update(User $actor, int $todoId, array $data): Todo
    {
        return $this->mutate($actor, $todoId, 'update', function (Todo $todo) use ($data): void {
            $todo->update(Validator::make($data, self::rules())->validate());
        });
    }

    public function assign(User $actor, int $todoId): Todo
    {
        return $this->mutate($actor, $todoId, 'assign', function (Todo $todo) use ($actor): void {
            $this->ensure(! $todo->assigned_to && $todo->status === TodoStatus::Open, 'Diese Challenge wurde bereits übernommen oder ist nicht mehr verfügbar.');
            $todo->update(['assigned_to' => $actor->id, 'status' => TodoStatus::Assigned]);
            Activity::create(['user_id' => $actor->id, 'subject_type' => Todo::class, 'subject_id' => $todo->id, 'action' => 'accepted']);
        });
    }

    public function complete(User $actor, int $todoId): Todo
    {
        return $this->mutate($actor, $todoId, null, function (Todo $todo) use ($actor): void {
            $this->ensure($todo->assigned_to === $actor->id && $todo->status === TodoStatus::Assigned, 'Sie können diese Challenge nicht als erledigt markieren.');
            $todo->update(['status' => TodoStatus::Completed, 'completed_at' => now()]);
        });
    }

    public function verify(User $actor, int $todoId): Todo
    {
        return $this->mutate($actor, $todoId, 'verify', function (Todo $todo) use ($actor): void {
            $this->ensure($todo->status === TodoStatus::Completed && $todo->assigned_to !== null, 'Diese Challenge kann nicht verifiziert werden.');
            $todo->update(['status' => TodoStatus::Verified, 'verified_by' => $actor->id, 'verified_at' => now()]);
            $this->writer->forTodo($todo);
            Activity::firstOrCreate(['user_id' => $todo->assigned_to, 'subject_type' => Todo::class, 'subject_id' => $todo->id, 'action' => 'completed']);
        });
    }

    public function release(User $actor, int $todoId): Todo
    {
        return $this->mutate($actor, $todoId, null, function (Todo $todo) use ($actor): void {
            $this->ensure($todo->assigned_to === $actor->id && $todo->status === TodoStatus::Assigned, 'Sie können diese Challenge nicht freigeben.');
            $todo->update(['assigned_to' => null, 'status' => TodoStatus::Open]);
        });
    }

    public function delete(User $actor, int $todoId): Todo
    {
        return $this->mutate($actor, $todoId, 'delete', function (Todo $todo): void {
            if ($todo->status === TodoStatus::Verified) {
                $todo->userPoint?->delete();
            }
            $todo->delete();
        });
    }

    private function mutate(User $actor, int $todoId, ?string $ability, Closure $operation): Todo
    {
        $team = $this->teams->getMembersTeamOrAbort();

        return DB::transaction(function () use ($actor, $todoId, $ability, $operation, $team): Todo {
            $todo = Todo::query()->lockForUpdate()->findOrFail($todoId);
            $this->ensure($todo->team_id === $team->id, 'Challenge nicht gefunden.');
            if ($ability !== null) {
                Gate::forUser($actor)->authorize($ability, $todo);
            }
            $previousAssignee = $todo->assigned_to;
            $operation($todo);
            $userIds = array_unique(array_filter([$previousAssignee, $todo->assigned_to]));
            DB::afterCommit(function () use ($team, $userIds): void {
                Cache::forget("pending_verification_{$team->id}");
                Cache::forget("top_users_{$team->id}");
                foreach ($userIds as $userId) {
                    Cache::forget("open_todos_{$userId}");
                }
            });

            return $todo;
        }, 3);
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages(['todo' => $message]);
        }
    }
}
