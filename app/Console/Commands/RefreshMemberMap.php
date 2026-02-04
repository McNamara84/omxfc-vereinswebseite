<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Services\MemberMapCacheService;
use Illuminate\Console\Command;

class RefreshMemberMap extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'member-map:refresh';

    /**
     * The console command description.
     */
    protected $description = 'Refresh cached member map data for all teams.';

    public function __construct(protected MemberMapCacheService $service)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $teams = Team::all();

        foreach ($teams as $team) {
            $this->service->refresh($team);
            $this->info("Refreshed member map for team {$team->id}");
        }

        return self::SUCCESS;
    }
}
