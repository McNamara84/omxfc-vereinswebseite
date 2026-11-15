<?php

namespace Tests\Support;

use App\Models\RpgCharacter;
use App\Models\RpgCharacterRename;
use App\Services\RpgCharacterRenameService;
use Illuminate\Support\Str;

trait RpgCharacterRenameFixtures
{
    use RpgProgressionFixtures;

    protected function renameInput(string $name = 'Birka', ?RpgCharacter $character = null): array
    {
        return ['character_name' => $name, 'revision' => ($character ?? $this->character)->fresh()->revision, 'submission_key' => (string) Str::uuid()];
    }

    protected function renameCharacter(string $name = 'Birka'): ?RpgCharacterRename
    {
        return app(RpgCharacterRenameService::class)->rename($this->player, $this->character->id, $this->renameInput($name));
    }
}
