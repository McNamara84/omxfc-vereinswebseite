<?php

namespace App\Jobs;

use App\Mail\RpgCombatMail;
use App\Models\RpgCombatDelivery;
use App\Models\User;
use App\Services\RpgCombat\CombatAuthority;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

class SendRpgCombatMail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $uniqueFor = 900;

    public function __construct(public int $deliveryId)
    {
        $this->afterCommit();
    }

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public function backoff(): array
    {
        return [30, 120, 600, 900];
    }

    public function handle(): void
    {
        $delivery = DB::transaction(function () {
            $delivery = RpgCombatDelivery::lockForUpdate()->find($this->deliveryId);
            if (! $delivery || in_array($delivery->status, ['sent', 'cancelled'], true) || ($delivery->status === 'processing' && $delivery->claimed_at > now('UTC')->subMinutes(15))) {
                return null;
            }
            $user = User::find($delivery->recipient_id);
            $combat = $delivery->combat;
            $decision = $delivery->decision;
            $valid = $user && $combat && Gate::forUser($user)->allows('view', $combat);
            $valid = $valid && ($combat->kind !== 'npc_vs_player' || app(CombatAuthority::class)->leader($combat) !== null);
            if ($decision) {
                $valid = $valid && ! $combat->suspension && $decision->status === 'pending'
                    && ($decision->timeout_policy === 'manual_leader' || $decision->due_at?->isFuture())
                    && app(CombatAuthority::class)->canDecide($combat, $decision, $user);
            } elseif ($delivery->kind === 'invitation') {
                $valid = $valid && $combat->status === 'challenged' && $combat->expires_at->isFuture();
            } else {
                $valid = $valid && $combat->isOpen() && in_array($user->id, $combat->participants->map(fn ($p) => app(CombatAuthority::class)->controller($combat, $p->side))->all(), true);
            }
            if (! $valid) {
                $delivery->update(['status' => 'cancelled']);

                return null;
            }
            $delivery->update(['status' => 'processing', 'claimed_at' => now('UTC'), 'attempts' => $delivery->attempts + 1]);

            return $delivery;
        });
        if (! $delivery) {
            return;
        }
        try {
            Mail::to(User::findOrFail($delivery->recipient_id)->email)->send(new RpgCombatMail($delivery));
            $delivery->update(['status' => 'sent', 'sent_at' => now('UTC')]);
        } catch (\Throwable $error) {
            $delivery->update(['status' => 'pending']);
            throw $error;
        }
    }
}
