<?php

namespace App\Services\Maddraxikon;

use App\Services\MaddraxDataService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MaddraxikonRefreshCoordinator
{
    public function __construct(
        private readonly MaddraxikonCandidateStore $candidates,
        private readonly MaddraxikonBookImporter $importer,
        private readonly MaddraxDataService $dataService,
        private readonly MaddraxikonSnapshotRepository $snapshots,
    ) {}

    /** @return array<string, int> */
    public function promote(string $candidateId): array
    {
        $candidate = $this->candidates->load($candidateId);

        if (! $candidate['already_active']) {
            DB::transaction(function () use ($candidate, $candidateId): void {
                $this->snapshots->storeAndActivate($candidateId, $candidate['json']);
                $this->importer->import($candidate['datasets'], false);
            });
        }

        if (! $this->candidates->delete($candidateId)) {
            Log::warning('Erfolgreich aktivierter Maddraxikon-Kandidat konnte nicht gelöscht werden.', [
                'candidate_id' => $candidateId,
            ]);
        }

        try {
            $this->snapshots->pruneInactive();
            $this->candidates->pruneExpired();
        } catch (\Throwable $exception) {
            Log::warning('Veraltete Maddraxikon-Snapshots konnten nicht vollständig bereinigt werden.', [
                'message' => $exception->getMessage(),
            ]);
        }

        try {
            $this->dataService->clearCache();
        } catch (\Throwable $exception) {
            Log::warning('Maddraxikon-Datencache konnte nach erfolgreicher Freigabe nicht invalidiert werden.', [
                'candidate_id' => $candidateId,
                'message' => $exception->getMessage(),
            ]);
        }

        return collect($candidate['datasets'])
            ->map(static fn (array $rows): int => count($rows))
            ->all();
    }
}
