<?php

namespace Tests\Support;

use App\Enums\Role;
use App\Models\RpgCharacter;
use App\Models\Team;
use App\Models\User;
use App\Services\RpgExperienceAwardService;
use Illuminate\Support\Str;

trait RpgProgressionFixtures
{
    protected User $leader;

    protected User $player;

    protected Team $rpgTeam;

    protected RpgCharacter $character;

    protected function progressionFixtures(): void
    {
        $this->leader = $this->progressionMember();
        $this->player = $this->progressionMember();
        $this->rpgTeam = Team::factory()->create(['name' => 'AG Rollenspiel', 'user_id' => $this->leader->id, 'personal_team' => false]);
        $this->rpgTeam->users()->attach([$this->leader->id, $this->player->id], ['role' => Role::Mitglied->value]);
        $this->character = RpgCharacter::factory()->create([
            'user_id' => $this->player->id, 'character_name' => 'Arkon',
            'payload' => $this->progressionPayload(),
        ])->refresh();
    }

    protected function progressionMember(Role $role = Role::Mitglied): User
    {
        $team = Team::membersTeam();
        $user = User::factory()->create(['current_team_id' => $team->id]);
        $team->users()->attach($user, ['role' => $role->value]);

        return $user;
    }

    protected function progressionPayload(): array
    {
        return [
            'character' => ['character_name' => 'Arkon', 'player_name' => 'Spieler', 'race' => 'Hydrit', 'culture' => 'Meeresbewohner'],
            'attributes' => ['st' => 0, 'ge' => 0, 'ro' => 0, 'wi' => 0, 'wa' => 0, 'in' => 0, 'au' => 0],
            'skills' => [['name' => 'Nahkampf', 'value' => 2], ['name' => 'Bildung', 'value' => 1]],
            'advantages' => [], 'disadvantages' => ['Lichtscheu'],
            'advantage_effects' => [], 'advantage_counts' => [], 'languages' => [],
            'equipment' => ['items' => [], 'notes' => ''],
        ];
    }

    protected function awardInput(int $points = 60): array
    {
        return [
            'submission_key' => (string) Str::uuid(), 'title' => 'Die Ruinen', 'completed_on' => now()->toDateString(),
            'minutes' => 480, 'cycle_bonus' => 0,
            'participants' => [[
                'character_id' => $this->character->id, 'survived' => true, 'roleplay' => 1,
                'humor' => false, 'rescue' => false, 'unwounded' => true,
                'points' => $points, 'reason' => 'Besondere Leistung – vertrauliche Bewertung',
            ]],
        ];
    }

    protected function credit(int $points = 60): void
    {
        app(RpgExperienceAwardService::class)->award($this->leader, $this->awardInput($points));
    }

    protected function operation(string $type = 'skill', string $name = 'Nahkampf', int $steps = 1, array $extra = []): array
    {
        return $extra + ['type' => $type, 'name' => $name, 'steps' => $steps, 'reason' => 'Im Abenteuer von der Wache gelernt.'];
    }

    protected function advancementInput(?array $operations = null): array
    {
        return [
            'submission_key' => (string) Str::uuid(), 'revision' => $this->character->fresh()->revision,
            'operations' => $operations ?? [$this->operation()],
        ];
    }
}
