<?php

use App\Models\RpgCharacter;
use App\Models\User;
use App\Services\RpgAccess;
use App\Services\RpgCheckDice;
use App\Services\RpgCheckService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'omxfc_rpg_test') {
    throw new RuntimeException('Worker requires the isolated omxfc_rpg_test database.');
}
$app->instance(RpgCheckDice::class, new class extends RpgCheckDice
{
    public function roll(): array
    {
        return [3, 4];
    }
});
fwrite(STDOUT, "ready\n");
fflush(STDOUT);
$data = json_decode(fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
try {
    $actor = User::findOrFail($data['actor']);
    $service = app(RpgCheckService::class);
    $result = match ($data['action']) {
        'create' => $service->create($actor, $data['input']),
        'roll' => $service->roll($actor, $data['check'], $data['participant']),
        'cancel' => $service->cancel($actor, $data['check'], 'Parallel storniert'),
        'resolve' => $service->resolve($actor, $data['check'], $data['resolution'], 'Parallel entschieden'),
        'delete' => DB::transaction(function () use ($actor, $data) {
            app(RpgAccess::class)->team(lock: true);
            $character = RpgCharacter::lockForUpdate()->findOrFail($data['character']);
            Gate::forUser($actor)->authorize('delete', $character);
            $character->delete();

            return $character;
        }, 3),
    };
    fwrite(STDOUT, json_encode(['ok' => true, 'id' => $result->id], JSON_THROW_ON_ERROR)."\n");
} catch (ValidationException|ModelNotFoundException $exception) {
    fwrite(STDOUT, json_encode(['ok' => false], JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n");
    exit(1);
}
