<?php

namespace App\Jobs;

use App\Mail\RpgCombatMail;
use App\Models\RpgCombatDelivery;
use App\Models\User;
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
            if ($decision) {
                $valid = $valid && $decision->status === 'pending' && $decision->due_at?->isFuture() && ($decision->type === 'ruling'
                    ? Gate::forUser($user)->allows('rule', $combat)
                    : $combat->participants->firstWhere('side', $decision->controller_side)?->owner_id === $user->id);
            } elseif ($delivery->kind === 'invitation') {
                $valid = $valid && $combat->status === 'challenged' && $combat->expires_at->isFuture();
            } else {
                $valid = $valid && $combat->isOpen();
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
