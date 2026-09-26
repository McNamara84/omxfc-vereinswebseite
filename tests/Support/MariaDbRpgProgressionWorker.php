<?php

use App\Models\RpgCharacter;
use App\Models\User;
use App\Services\RpgCharacterAdvancementService;
use App\Services\RpgExperienceAwardService;
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
fwrite(STDOUT, "ready\n");
fflush(STDOUT);
$data = json_decode(fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
try {
    $actor = User::findOrFail($data['actor']);
    $result = match ($data['action']) {
        'award' => app(RpgExperienceAwardService::class)->award($actor, $data['input']),
        'submit' => app(RpgCharacterAdvancementService::class)->submit($actor, $data['character'], $data['input']),
        'delete' => DB::transaction(function () use ($actor, $data) {
            $character = RpgCharacter::lockForUpdate()->findOrFail($data['character']);
            Gate::forUser($actor)->authorize('delete', $character);
            $character->delete();

            return $character;
        }),
        default => app(RpgCharacterAdvancementService::class)->decide($actor, $data['request'], $data['action']),
    };
    fwrite(STDOUT, json_encode(['ok' => true, 'id' => $result->id], JSON_THROW_ON_ERROR)."\n");
} catch (ValidationException $exception) {
    fwrite(STDOUT, json_encode(['ok' => false, 'validation' => $exception->errors()], JSON_THROW_ON_ERROR)."\n");
} catch (ModelNotFoundException) {
    fwrite(STDOUT, json_encode(['ok' => false, 'deleted' => true], JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n");
    exit(1);
}
