<?php

namespace Tests\Support;

use App\Models\RpgCharacter;
use App\Models\RpgCheck;
use App\Models\User;
use App\Services\RpgCheckDice;
use App\Services\RpgCheckService;
use Illuminate\Support\Str;

trait RpgCheckFixtures
{
    use RpgProgressionFixtures;

    protected User $otherPlayer;

    protected RpgCharacter $otherCharacter;

    protected function checkFixtures(): void
    {
        $this->progressionFixtures();
        $this->otherPlayer = $this->progressionMember();
        $this->rpgTeam->users()->attach($this->otherPlayer, ['role' => 'Mitglied']);
        $this->otherCharacter = RpgCharacter::factory()->create([
            'user_id' => $this->otherPlayer->id, 'character_name' => 'Birka', 'payload' => $this->progressionPayload(),
        ])->refresh();
    }

    protected function checkInput(string $mode = 'fixed', string $visibility = 'open'): array
    {
        $participant = fn ($character) => [
            'character_id' => $character->id, 'revision' => $character->revision,
            'check_type' => 'skill', 'attribute_key' => 'ge', 'skill_name' => 'Nahkampf', 'modifiers' => [],
        ];

        return ['submission_key' => (string) Str::uuid(), 'mode' => $mode, 'visibility' => $visibility,
            'description' => 'Die schmale Brücke', 'difficulty' => $mode === 'fixed' ? 10 : null,
            'participants' => $mode === 'fixed' ? [$participant($this->character)] : [$participant($this->character), $participant($this->otherCharacter)]];
    }

    protected function createCheck(string $mode = 'fixed', string $visibility = 'open', ?array $input = null): RpgCheck
    {
        return app(RpgCheckService::class)->create($this->leader, $input ?? $this->checkInput($mode, $visibility))->checks()->firstOrFail()->load('participants');
    }

    protected function dice(array ...$rolls): void
    {
        $fake = $this->mock(RpgCheckDice::class);
        $fake->shouldReceive('roll')->times(count($rolls))->andReturn(...$rolls);
    }

    protected function rollCheck(RpgCheck $check, ?User $player = null, int $position = 1): RpgCheck
    {
        $check->load('participants');

        return app(RpgCheckService::class)->roll($player ?? $this->player, $check->id, $check->participants->firstWhere('position', $position)->id);
    }
}
