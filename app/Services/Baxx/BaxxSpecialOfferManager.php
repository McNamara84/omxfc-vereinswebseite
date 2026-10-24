<?php

namespace App\Services\Baxx;

use App\Enums\Role;
use App\Models\BaxxEarningRule;
use App\Models\RomantauschBaxxSpecialOffer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BaxxSpecialOfferManager
{
    public function __construct(private readonly BaxxRuleResolver $rules) {}

    public function save(User $actor, string $actionKey, array $data, ?int $id, string $fieldPrefix): Model
    {
        abort_unless($actor->hasRole(Role::Admin), 403);

        return DB::transaction(function () use ($actionKey, $data, $id, $fieldPrefix): Model {
            $this->lockRules($actionKey);
            $query = $this->rules->offers($actionKey);
            // Romantausch offers may move between actions; all three rules are locked.
            $offer = $id ? $query->getModel()->newQuery()->lockForUpdate()->findOrFail($id) : $query->getModel()->newInstance();
            $endsAt = Carbon::parse($data['ends_at'], config('app.timezone'));
            $this->assertActiveAllowed($actionKey, $id, (bool) $data['is_active'], $endsAt, $fieldPrefix);
            $attributes = [
                'points' => (int) $data['points'],
                'every_count' => (int) $data['every_count'],
                'ends_at' => $endsAt,
                'is_active' => (bool) $data['is_active'],
            ];
            if ($attributes['points'] < 1 || $attributes['every_count'] < 1) {
                throw ValidationException::withMessages([$fieldPrefix.'Points' => 'Punkte und Intervall müssen mindestens 1 betragen.']);
            }
            if ($offer instanceof RomantauschBaxxSpecialOffer) {
                $attributes['action_key'] = $actionKey;
            }
            $offer->fill($attributes)->save();

            return $offer;
        }, 3);
    }

    public function toggle(User $actor, string $actionKey, int $id, string $fieldPrefix): string
    {
        abort_unless($actor->hasRole(Role::Admin), 403);

        return DB::transaction(function () use ($actionKey, $id, $fieldPrefix): string {
            $this->lockRules($actionKey);
            $offer = $this->rules->offers($actionKey)->lockForUpdate()->findOrFail($id);
            $active = ! $offer->is_active;
            $this->assertActiveAllowed($actionKey, $id, $active, $offer->ends_at, $fieldPrefix);
            $offer->update(['is_active' => $active]);

            return $active ? 'aktiviert' : 'deaktiviert';
        }, 3);
    }

    private function lockRules(string $actionKey): void
    {
        $keys = in_array($actionKey, RomantauschBaxxSpecialOffer::allowedActionKeys(), true)
            ? RomantauschBaxxSpecialOffer::allowedActionKeys() : [$actionKey];
        sort($keys);
        foreach ($keys as $key) {
            $this->rules->base($key);
        }
        BaxxEarningRule::query()->whereIn('action_key', $keys)->orderBy('action_key')->lockForUpdate()->get();
    }

    private function assertActiveAllowed(string $actionKey, ?int $id, bool $active, Carbon $endsAt, string $fieldPrefix): void
    {
        if (! $active) {
            return;
        }
        if ($endsAt->lte(now())) {
            throw ValidationException::withMessages([$fieldPrefix.'EndsAt' => 'Das Enddatum einer aktiven Sonderaktion muss in der Zukunft liegen.']);
        }
        // A locking read sees the latest committed offer even when an earlier
        // base-rule lookup established a REPEATABLE READ snapshot.
        if ($this->rules->offers($actionKey)->currentlyActive()->when($id, fn ($query) => $query->whereKeyNot($id))->lockForUpdate()->first(['id'])) {
            throw ValidationException::withMessages([$fieldPrefix.'IsActive' => 'Es kann pro Aktion immer nur eine aktive Sonderaktion gleichzeitig geben.']);
        }
    }
}
