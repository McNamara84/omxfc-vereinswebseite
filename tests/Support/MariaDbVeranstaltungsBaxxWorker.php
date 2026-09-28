<?php

use App\Models\User;
use App\Models\Veranstaltung;
use App\Services\FantreffenRegistrationService;
use App\Services\VeranstaltungsBaxxService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'omxfc_veranstaltungen_test') {
    throw new RuntimeException('Worker requires the isolated omxfc_veranstaltungen_test database.');
}
config(['logging.default' => 'null']);
Mail::fake();
$input = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$event = Veranstaltung::findOrFail($input['event']);
$actor = User::findOrFail($input['actor']);
$emitted = false;
DB::connection()->beforeExecuting(function (string $query) use (&$emitted, $input) {
    $target = $input['action'] === 'demotion' ? 'team_user' : 'veranstaltungen';
    if (! $emitted && str_contains($query, $target) && str_contains($query, 'for update')) {
        $emitted = true;
        fwrite(STDOUT, "locking\n");
        fflush(STDOUT);
    }
});
try {
    if ($input['prevalidateRegistration'] ?? false) {
        // Wie im Controller: Vorprüfung vor der Service-Transaktion.
        $registrationService = app(FantreffenRegistrationService::class);
        Validator::make($input['data'], $registrationService->validationRules(! $input['guest'], $event))->validate();
        if (! $input['guest'] && $event->anmeldungen()->where('user_id', $input['newMember'])->exists()) {
            throw new RuntimeException('Die erste Duplikatprüfung muss vor der konkurrierenden Anmeldung erfolgreich sein.');
        }
    }
    $service = app(VeranstaltungsBaxxService::class);
    match ($input['action']) {
        'archive', 'demotion' => $service->speichern($event, ['status' => 'archiviert'], $actor),
        'attendance' => $service->setTeilnahme($event, $input['registration'], false, $actor),
        'amount' => $service->speichern($event, ['teilnahme_baxx' => 50], $actor),
        'delete' => $service->deleteAnmeldung($event, $input['registration'], $actor),
        'register' => app(FantreffenRegistrationService::class)->register(
            $input['data'] ?? [], $event, ($input['guest'] ?? false) ? null : User::findOrFail($input['newMember'])
        ),
    };
    fwrite(STDOUT, json_encode(['ok' => true], JSON_THROW_ON_ERROR)."\n");
} catch (ValidationException $exception) {
    fwrite(STDOUT, json_encode(['ok' => false, 'errors' => $exception->errors()], JSON_THROW_ON_ERROR)."\n");
}
