<?php

declare(strict_types=1);

use App\Actions\Romantausch\BundleWorkflow;
use App\Actions\Romantausch\OfferWorkflow;
use App\Actions\Romantausch\SwapWorkflow;
use App\Enums\BookType;
use App\Mail\BookSwapMatched;
use App\Models\BookOffer;
use App\Models\User;
use App\Services\Romantausch\SwapMatchingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    if (! app()->environment('testing') || config('database.default') !== 'mysql'
        || config('database.connections.mysql.database') !== 'omxfc_maddraxikon_test') {
        throw new RuntimeException('Unsichere Worker-Datenbank verweigert.');
    }
    config(['logging.default' => 'null']);
    Mail::fake();
    [$operation, $userId, $id, $otherId] = array_pad(array_slice($argv, 1), 4, '0');
    $actor = User::findOrFail((int) $userId);
    fwrite(STDOUT, "ready\n");
    fflush(STDOUT);
    if (trim((string) fgets(STDIN)) !== 'RUN') {
        throw new RuntimeException('Startkommando fehlt.');
    }
    try {
        $data = ['series' => BookType::MaddraxDieDunkleZukunftDerErde->value, 'book_number' => 1, 'condition' => 'Z0'];
        match ($operation) {
            'create' => app(OfferWorkflow::class)->create($actor, $data),
            'update' => app(OfferWorkflow::class)->update($actor, (int) $id, $data),
            'delete' => app(OfferWorkflow::class)->delete($actor, (int) $id),
            'match' => app(SwapMatchingService::class)->matchSwap(BookOffer::findOrFail((int) $id), 'offer'),
            'confirm' => app(SwapWorkflow::class)->confirm($actor, (int) $id),
            'complete' => app(SwapWorkflow::class)->complete($actor, (int) $id, (int) $otherId),
            'bundle-update' => app(BundleWorkflow::class)->update($actor, $id, $data + ['book_numbers' => '1-3']),
            'bundle-delete' => app(BundleWorkflow::class)->delete($actor, $id),
        };
        $result = 'ok';
    } catch (ValidationException|ModelNotFoundException|NotFoundHttpException $error) {
        $result = 'rejected';
    }
    fwrite(STDOUT, json_encode(['result' => $result, 'mails' => Mail::queued(BookSwapMatched::class)->count()], JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error::class.': '.$error->getMessage());
    exit(1);
}
