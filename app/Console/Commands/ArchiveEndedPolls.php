<?php

namespace App\Console\Commands;

use App\Enums\PollStatus;
use App\Models\Poll;
use Illuminate\Console\Command;

class ArchiveEndedPolls extends Command
{
    protected $signature = 'polls:archive-ended';

    protected $description = 'Archiviert beendete aktive Umfragen.';

    public function handle(): int
    {
        $now = now();

        $archivedCount = Poll::query()
            ->where('status', PollStatus::Active)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now)
            ->update([
                'status' => PollStatus::Archived,
                'archived_at' => $now,
            ]);

        $this->info(sprintf('Archiviert: %d', $archivedCount));

        return self::SUCCESS;
    }
}
