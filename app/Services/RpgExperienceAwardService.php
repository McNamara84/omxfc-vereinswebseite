<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\RpgAdventure;
use App\Models\RpgCharacter;
use App\Models\RpgExperienceAward;
use App\Models\User;
use App\Support\RpgExperienceRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class RpgExperienceAwardService
{
    public function __construct(private RpgAccess $access, private RpgExperienceAwardCalculator $calculator) {}

    public function preview(User $actor, array $input): array
    {
        $team = $this->access->requireLeader($actor);
        $data = $this->validated($input);
        $awards = [];
        foreach ($data['participants'] as $index => $participant) {
            $character = RpgCharacter::findOrFail($participant['character_id']);
            if (! $this->access->isMember($character->user_id, $team)) {
                throw ValidationException::withMessages(['participants' => 'Alle Charaktere müssen aktuellen Mitgliedern der AG Rollenspiel gehören.']);
            }
            try {
                $award = $this->calculator->calculate((int) $data['minutes'], (int) $data['cycle_bonus'], $participant);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages(["participants.{$index}" => $exception->validator->errors()->all()]);
            }
            $awards[] = $award + ['character_id' => $character->id, 'character_name' => $character->displayName()];
        }

        return ['data' => $data, 'awards' => $awards];
    }

    public function award(User $actor, array $input): RpgAdventure
    {
        $data = $this->validated($input);

        return DB::transaction(function () use ($actor, $data): RpgAdventure {
            $team = $this->access->requireLeader($actor, lock: true);
            $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
            $existing = RpgAdventure::where('submission_key', $data['submission_key'])->first();
            if ($existing) {
                abort_unless($existing->user_id === $actor->id, 403);
                if ($existing->submission_hash !== $hash) {
                    throw ValidationException::withMessages(['submission_key' => 'Diese Vergabe wurde bereits mit anderen Angaben gespeichert.']);
                }

                return $existing;
            }
            RpgCharacter::whereIn('id', array_column($data['participants'], 'character_id'))->orderBy('id')->lockForUpdate()->get();
            $preview = $this->preview($actor, $data);
            $adventure = RpgAdventure::create([
                'team_id' => $team->id, 'user_id' => $actor->id,
                'submission_key' => $data['submission_key'], 'submission_hash' => $hash,
                'title' => $data['title'], 'completed_on' => $data['completed_on'],
                'minutes' => $data['minutes'], 'cycle_bonus' => $data['cycle_bonus'],
                'rule_version' => RpgExperienceRules::VERSION,
            ]);
            foreach ($preview['awards'] as $row) {
                $character = RpgCharacter::findOrFail($row['character_id']);
                unset($row['character_id']);
                $award = RpgExperienceAward::create($row + [
                    'rpg_adventure_id' => $adventure->id, 'rpg_character_id' => $character->id,
                ]);
                $character->experienceEntries()->create(['rpg_experience_award_id' => $award->id, 'amount' => $award->points]);
                if ($award->points > 0) {
                    Activity::create([
                        'user_id' => $character->user_id, 'subject_type' => RpgExperienceAward::class,
                        'subject_id' => $award->id, 'action' => Activity::ACTION_RPG_EXPERIENCE_AWARDED,
                    ]);
                }
            }

            return $adventure;
        }, 3);
    }

    private function validated(array $input): array
    {
        return Validator::make($input, [
            'submission_key' => ['required', 'uuid'],
            'title' => ['required', 'string', 'max:255'],
            'completed_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'minutes' => ['required', 'integer', 'min:0', 'max:10000000'],
            'cycle_bonus' => ['required', Rule::in([0, 1, 2, 4])],
            'participants' => ['required', 'array', 'min:1', 'max:100'],
            'participants.*' => ['required', 'array:character_id,survived,roleplay,humor,rescue,unwounded,points,reason'],
            'participants.*.character_id' => ['required', 'integer', 'distinct', 'exists:rpg_characters,id'],
            'participants.*.survived' => ['required', 'boolean'],
            'participants.*.roleplay' => ['required', 'integer', 'between:0,2'],
            'participants.*.humor' => ['required', 'boolean'],
            'participants.*.rescue' => ['required', 'boolean'],
            'participants.*.unwounded' => ['required', 'boolean'],
            'participants.*.points' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'participants.*.reason' => ['nullable', 'string', 'max:4000'],
        ])->validate();
    }
}
