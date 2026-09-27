<?php

namespace App\Console\Commands;

use App\Jobs\SendRpgCombatMail;
use App\Models\RpgCombat;
use App\Models\RpgCombatDecision;
use App\Models\RpgCombatDelivery;
use App\Services\RpgCombat\CombatService;
use Illuminate\Console\Command;

class ProcessRpgCombats extends Command
{
    protected $signature = 'rpg:process-combats';

    protected $description = 'Übungskämpfe prüfen, fällige Entscheidungen ersetzen und Benachrichtigungen zustellen';

    public function handle(CombatService $service): int
    {
        if (! config('rpg-combat.enabled')) {
            return self::SUCCESS;
        }
        $failures = 0;
        // Capture before processing: newly opened decisions always get their full own deadline.
        $due = RpgCombatDecision::where('status', 'pending')->where('due_at', '<=', now('UTC'))->orderBy('due_at')
            ->limit(config('rpg-combat.deadline_batch_size'))->get(['id', 'rpg_combat_id']);
        RpgCombat::whereIn('status', ['challenged', 'preparing', 'active', 'awaiting_ruling'])->select('id')->chunkById(100, function ($combats) use ($service, &$failures) {
            foreach ($combats as $combat) {
                try {
                    $service->sweep($combat->id);
                } catch (\Throwable $error) {
                    report($error);
                    $failures++;
                }
            }
        });
        foreach ($due as $decision) {
            try {
                $fresh = $decision->fresh();
                if ($fresh?->status === 'pending' && $fresh->due_at && now('UTC')->greaterThanOrEqualTo($fresh->due_at)) {
                    $service->decide(null, $decision->rpg_combat_id, $decision->id);
                }
            } catch (\Throwable $error) {
                report($error);
                $failures++;
            }
        }
        RpgCombatDelivery::where('status', 'pending')->orWhere(fn ($q) => $q->where('status', 'processing')->where('claimed_at', '<=', now('UTC')->subMinutes(15)))
            ->orderBy('id')->limit(200)->pluck('id')->each(fn ($id) => SendRpgCombatMail::dispatch($id));
        $this->info('Kampffristen geprüft; Fehler: '.$failures);

        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
