<?php

namespace App\Livewire\Umfragen;

use App\Enums\PollVisibility;
use App\Models\Poll;
use App\Models\PollOption;
use App\Services\Polls\ActivePollResolver;
use App\Services\Polls\PollVotingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class UmfrageVote extends Component
{
    public ?int $pollId = null;
    public ?int $selectedOptionId = null;

    public ?string $statusMessage = null;
    public bool $hasVoted = false;
    public bool $canVote = false;

    public function mount(ActivePollResolver $resolver, PollVotingService $voting): void
    {
        $poll = $resolver->current();

        if (! $poll) {
            $this->statusMessage = 'Aktuell läuft keine Umfrage.';
            return;
        }

        $this->pollId = $poll->id;

        $poll->loadMissing('options');
        if ($poll->options->isEmpty()) {
            $this->statusMessage = 'Diese Umfrage hat keine Antwortmöglichkeiten.';
            $this->canVote = false;
            return;
        }

        if (! $poll->isWithinVotingWindow()) {
            if ($poll->starts_at && now()->lt($poll->starts_at)) {
                $this->statusMessage = 'Diese Umfrage ist noch nicht gestartet.';
            } elseif ($poll->ends_at && now()->gt($poll->ends_at)) {
                $this->statusMessage = 'Diese Umfrage ist bereits beendet.';
            } else {
                $this->statusMessage = 'Diese Umfrage läuft aktuell nicht.';
            }

            $this->canVote = false;
            return;
        }

        if ($poll->visibility === PollVisibility::Internal) {
            if (! Auth::check()) {
                $this->statusMessage = 'Bitte logge dich ein, um an dieser Umfrage teilzunehmen.';
                $this->canVote = false;
                return;
            }

            try {
                $voting->assertMemberEligible(Auth::user());
            } catch (ValidationException $e) {
                $errors = $e->errors();
                $this->statusMessage = $errors['poll'][0] ?? 'Diese Umfrage ist nur für Vereinsmitglieder verfügbar.';
                $this->canVote = false;
                return;
            }
        }

        $ipHash = $this->ipHash();

        if ($voting->hasAlreadyVoted($poll, Auth::user(), $ipHash)) {
            $this->hasVoted = true;
            $this->statusMessage = $poll->visibility === PollVisibility::Internal
                ? 'Du hast bereits an dieser Umfrage teilgenommen.'
                : 'Von dieser IP wurde bereits abgestimmt.';
            $this->canVote = false;
            return;
        }

        $this->canVote = true;
    }

    public function submit(PollVotingService $voting): void
    {
        $poll = $this->poll();

        if (! $poll) {
            $this->statusMessage = 'Aktuell läuft keine Umfrage.';
            return;
        }

        if (! $this->canVote || $this->hasVoted) {
            $this->statusMessage ??= 'Abstimmung aktuell nicht möglich.';
            return;
        }

        $this->validate([
            'selectedOptionId' => ['required', 'integer'],
        ], [
            'selectedOptionId.required' => 'Bitte wähle eine Antwort aus.',
        ]);

        $option = PollOption::query()->findOrFail((int) $this->selectedOptionId);

        try {
            $voting->vote(
                $poll,
                $option,
                Auth::user(),
                $poll->visibility === PollVisibility::Public ? $this->ipHash() : null,
            );
        } catch (ValidationException $e) {
            $errors = method_exists($e, 'errors') ? $e->errors() : [];

            if (! empty($errors)) {
                $this->setErrorBag($errors);
                $this->statusMessage = $errors['poll'][0] ?? 'Abstimmung nicht möglich.';
            } else {
                $this->statusMessage = 'Abstimmung nicht möglich.';
            }
            return;
        }

        $this->hasVoted = true;
        $this->canVote = false;
        $this->statusMessage = 'Danke! Deine Stimme wurde gespeichert.';
    }

    private function poll(): ?Poll
    {
        if (! $this->pollId) {
            return null;
        }

        return Poll::query()->with('options')->find($this->pollId);
    }

    private function ipHash(): ?string
    {
        $ip = request()->ip();

        if (! $ip) {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    public function render()
    {
        $poll = $this->poll();

        return view('livewire.umfragen.umfrage-vote', [
            'poll' => $poll,
        ])->layout('layouts.app', [
            'title' => $poll ? $poll->menu_label : 'Umfrage',
            'description' => $poll ? Str::limit($poll->question, 140) : 'Aktuelle Umfrage',
        ]);
    }
}
