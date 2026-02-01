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
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class UmfrageVote extends Component
{
    // Locked: Diese Werte können nicht vom Client manipuliert werden
    #[Locked]
    public ?int $pollId = null;

    public ?int $selectedOptionId = null;

    #[Locked]
    public ?string $statusMessage = null;

    #[Locked]
    public bool $hasVoted = false;

    #[Locked]
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
                : PollVotingService::ERROR_ALREADY_VOTED_IP;
            $this->canVote = false;
            return;
        }

        $this->canVote = true;
    }

    public function submit(ActivePollResolver $resolver, PollVotingService $voting): void
    {
        if ($this->hasVoted) {
            $this->statusMessage ??= 'Du hast bereits an dieser Umfrage teilgenommen.';
            return;
        }

        // Do not trust cached pollId from mount: the active poll may change between page load and submit.
        $poll = $resolver->current();
        if (! $poll) {
            $this->statusMessage = 'Aktuell läuft keine Umfrage.';
            $this->canVote = false;
            return;
        }

        if ($this->pollId && $poll->id !== $this->pollId) {
            $this->statusMessage = 'Die Umfrage hat sich geändert. Bitte lade die Seite neu.';
            $this->canVote = false;
            return;
        }

        try {
            $voting->assertPollAcceptsVotes($poll);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->statusMessage = $errors['poll'][0] ?? 'Abstimmung aktuell nicht möglich.';
            $this->canVote = false;
            return;
        }

        // Keep pollId in sync with the actual active poll.
        $this->pollId = $poll->id;

        $this->validate([
            'selectedOptionId' => ['required', 'integer'],
        ], [
            'selectedOptionId.required' => 'Bitte wähle eine Antwort aus.',
        ]);

        $optionId = (int) $this->selectedOptionId;
        $option = $poll->options()->whereKey($optionId)->first();
        if (! $option) {
            $this->statusMessage = 'Ungültige Auswahl.';
            $this->addError('selectedOptionId', 'Ungültige Auswahl.');
            return;
        }

        try {
            $voting->vote(
                $poll,
                $option,
                Auth::user(),
                $poll->visibility === PollVisibility::Public ? $this->ipHash() : null,
            );
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->setErrorBag($errors);
            $this->statusMessage = $errors['poll'][0] ?? 'Abstimmung nicht möglich.';
            return;
        }

        $this->hasVoted = true;
        $this->canVote = false;
        $this->statusMessage = 'Danke! Deine Stimme wurde gespeichert.';
    }

    /**
     * Aktive Poll als Computed Property.
     */
    #[Computed]
    public function poll(): ?Poll
    {
        if (!$this->pollId) {
            return null;
        }

        return Poll::query()->with('options')->find($this->pollId);
    }

    private function ipHash(): ?string
    {
        $ip = request()->ip();

        if (!$ip) {
            return null;
        }

        return hash_hmac('sha256', $ip, (string) config('app.key'));
    }

    public function render()
    {
        return view('livewire.umfragen.umfrage-vote')
            ->layout('layouts.app', [
                'title' => $this->poll ? $this->poll->menu_label : 'Umfrage',
                'description' => $this->poll ? Str::limit($this->poll->question, 140) : 'Aktuelle Umfrage',
            ]);
    }
}
