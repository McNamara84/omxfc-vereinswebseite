<?php

declare(strict_types=1);

use App\Actions\Fanfiction\PublishFanfiction;
use App\Actions\Fortify\UpdateUserSeriendaten;
use App\Actions\Reviews\RecordReview;
use App\Actions\Todos\TodoWorkflow;
use App\Enums\TourAssignmentSource;
use App\Models\Fanfiction;
use App\Models\TourAssignment;
use App\Models\User;
use App\Services\Baxx\BaxxSpecialOfferManager;
use App\Services\MembersTeamMembershipLock;
use App\Services\MissionCompletionService;
use App\Services\TourAssignmentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    if (! app()->environment('testing') || config('database.default') !== 'mysql'
        || config('database.connections.mysql.database') !== 'omxfc_maddraxikon_test') {
        throw new RuntimeException('Unsichere Worker-Datenbank verweigert.');
    }
    config(['logging.default' => 'null']);
    [$operation, $userId, $targetId] = array_slice($argv, 1);
    $user = User::findOrFail((int) $userId);
    fwrite(STDOUT, "ready\n");
    fflush(STDOUT);
    if (trim((string) fgets(STDIN)) !== 'RUN') {
        throw new RuntimeException('Startkommando fehlt.');
    }
    try {
        match ($operation) {
            'profile-complete' => app(TourAssignmentService::class)->complete(TourAssignment::findOrFail((int) $targetId)),
            'profile-restart' => app(TourAssignmentService::class)->reassign($user, 'profilpflege', TourAssignmentSource::SelfService),
            'profile-empty' => app(UpdateUserSeriendaten::class)->update($user, ['lieblingsmutation' => null]),
            'profile-revoke' => app(MembersTeamMembershipLock::class)->run([$user->id], fn ($memberships) => $memberships->team->users()->detach($user->id)),
            'assign', 'verify', 'delete' => app(TodoWorkflow::class)->{$operation}($user, (int) $targetId),
            'review' => app(RecordReview::class)->execute($user, $user->current_team_id, (int) $targetId, ['title' => 'Parallel', 'content' => 'Inhalt']),
            'mission' => app(MissionCompletionService::class)->status($user),
            'publish' => app(PublishFanfiction::class)->execute(Fanfiction::findOrFail((int) $targetId)),
            'offer' => app(BaxxSpecialOfferManager::class)->save($user, 'rezension', [
                'points' => 2, 'every_count' => 1, 'ends_at' => now()->addHour(), 'is_active' => true,
            ], null, 'review'),
        };
        $result = 'ok';
    } catch (ValidationException|ModelNotFoundException|HttpException $error) {
        $result = 'rejected';
    }
    fwrite(STDOUT, json_encode(['result' => $result], JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $error) {
    fwrite(STDERR, $error::class.': '.$error->getMessage());
    exit(1);
}
