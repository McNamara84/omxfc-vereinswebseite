<?php

use App\Models\RpgCharacter;
use App\Models\RpgCombat;
use App\Models\User;
use App\Services\RpgAccess;
use App\Services\RpgCombat\CombatDice;
use App\Services\RpgCombat\CombatService;
use App\Services\RpgNpcService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CombatTestDice;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'omxfc_rpg_test') {
    throw new RuntimeException('Only isolated omxfc_rpg_test is permitted.');
}
$app->instance(CombatDice::class, new CombatTestDice);
fwrite(STDOUT, "ready\n");
fflush(STDOUT);
$data = json_decode(fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
try {
    $actor = isset($data['actor']) ? User::findOrFail($data['actor']) : null;
    $service = app(CombatService::class);
    $result = match ($data['action']) {
        'decide' => $service->decide($actor, $data['combat'], $data['decision'], $data['input'] ?? []),
        'command' => $service->command($actor, $data['combat'], $data['command'], $data['key']),
        'challenge' => $service->challenge($actor, $data['input']),
        'npc-create' => app(RpgNpcService::class)->create($actor, $data['input']),
        'remind' => (function () use ($service, $data) {
            $service->remind($data['combat'], $data['decision']);

            return RpgCombat::findOrFail($data['combat']);
        })(),
        'delete' => DB::transaction(function () use ($data) {
            app(RpgAccess::class)->team(lock: true);
            $character = RpgCharacter::lockForUpdate()->findOrFail($data['character']);
            $character->delete();

            return $character;
        }, 3),
    };
    fwrite(STDOUT, json_encode(['ok' => true, 'id' => $result->id])."\n");
} catch (ValidationException|ModelNotFoundException|HttpException $error) {
    fwrite(STDOUT, json_encode(['ok' => false, 'error' => $error->getMessage()])."\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error::class.': '.$error->getMessage()."\n");
    exit(1);
}
