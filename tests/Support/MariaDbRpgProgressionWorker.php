<?php

use App\Http\Controllers\RpgCharacterController;
use App\Models\RpgCharacter;
use App\Models\RpgItemType;
use App\Models\User;
use App\Services\RpgAccess;
use App\Services\RpgCharacterAdvancementService;
use App\Services\RpgExperienceAwardService;
use App\Services\RpgInventoryService;
use App\Services\RpgItemService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
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
        'item_configure' => (function () use ($actor, $data) {
            app(RpgItemService::class)->configure($actor, $data['type'], $data['input']);

            return RpgItemType::findOrFail($data['type']);
        })(),
        'character_create' => (function () use ($actor, $data) {
            $request = Request::create('/rpg/charaktere', 'POST', $data['input']);
            $request->setUserResolver(fn () => $actor);
            app(RpgCharacterController::class)->store($request);

            return RpgCharacter::where('user_id', $actor->id)->latest('id')->firstOrFail();
        })(),
        'item' => app(RpgItemService::class)->save($actor, $data['input'], $data['type'] ?? null),
        'inventory' => app(RpgInventoryService::class)->book($actor, $data['character'], $data['input']),
        'award' => app(RpgExperienceAwardService::class)->award($actor, $data['input']),
        'submit' => app(RpgCharacterAdvancementService::class)->submit($actor, $data['character'], $data['input']),
        'delete' => DB::transaction(function () use ($actor, $data) {
            // Match RpgCharacterController::destroy: AG before character, including lifecycle hooks.
            app(RpgAccess::class)->team(lock: true);
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
} catch (AuthorizationException) {
    fwrite(STDOUT, json_encode(['ok' => false, 'forbidden' => true])."\n");
} catch (ModelNotFoundException) {
    fwrite(STDOUT, json_encode(['ok' => false, 'deleted' => true], JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $exception) {
    fwrite(STDERR, $exception::class.': '.$exception->getMessage()."\n");
    exit(1);
}
