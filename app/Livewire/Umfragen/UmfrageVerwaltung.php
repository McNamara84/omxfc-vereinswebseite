<?php

namespace App\Livewire\Umfragen;

use App\Enums\PollStatus;
use App\Enums\PollVisibility;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class UmfrageVerwaltung extends Component
{
    public ?int $selectedPollId = null;
    public ?int $pollId = null;

    public string $question = '';
    public string $menuLabel = '';
    public string $visibility = 'internal';
    public string $status = 'draft';
    public ?string $startsAt = null;
    public ?string $endsAt = null;

    /**
     * @var array<int, array{id?:int,label:string,image_url:?string,link_url:?string}>
     */
    public array $options = [];

    public array $chartData = [];

    public function mount(): void
    {
        $this->newPoll();

        $latest = Poll::query()->orderByDesc('id')->first();

        if ($latest) {
            $this->selectPoll($latest->id);
        }
    }

    public function updatedSelectedPollId($value): void
    {
        if ($value) {
            $this->selectPoll((int) $value);
        }
    }

    public function newPoll(): void
    {
        $this->resetValidation();

        $this->selectedPollId = null;
        $this->pollId = null;
        $this->question = '';
        $this->menuLabel = '';
        $this->visibility = PollVisibility::Internal->value;
        $this->status = PollStatus::Draft->value;
        $this->startsAt = now()->addHour()->format('Y-m-d\TH:i');
        $this->endsAt = now()->addDays(7)->format('Y-m-d\TH:i');
        $this->options = [
            ['label' => '', 'image_url' => null, 'link_url' => null],
            ['label' => '', 'image_url' => null, 'link_url' => null],
        ];
        $this->chartData = [];
    }

    public function addOption(): void
    {
        if (count($this->options) >= 13) {
            $this->addError('options', 'Maximal 13 Antwortmöglichkeiten sind erlaubt.');
            return;
        }

        $this->resetErrorBag('options');
        $this->options[] = ['label' => '', 'image_url' => null, 'link_url' => null];
    }

    public function removeOption(int $index): void
    {
        if (! isset($this->options[$index])) {
            return;
        }

        unset($this->options[$index]);
        $this->options = array_values($this->options);

        if (count($this->options) < 13) {
            $this->resetErrorBag('options');
        }
    }

    public function selectPoll(int $pollId): void
    {
        $poll = Poll::query()->with('options')->findOrFail($pollId);

        $this->resetValidation();

        $this->selectedPollId = $poll->id;
        $this->pollId = $poll->id;
        $this->question = $poll->question;
        $this->menuLabel = $poll->menu_label;
        $this->visibility = $poll->visibility->value;
        $this->status = $poll->status->value;
        $this->startsAt = $poll->starts_at?->format('Y-m-d\TH:i');
        $this->endsAt = $poll->ends_at?->format('Y-m-d\TH:i');
        $this->options = $poll->options
            ->map(fn (PollOption $option) => [
                'id' => $option->id,
                'label' => $option->label,
                'image_url' => $option->image_url,
                'link_url' => $option->link_url,
            ])
            ->all();

        if (count($this->options) === 0) {
            $this->options = [['label' => '', 'image_url' => null, 'link_url' => null]];
        }

        $this->refreshResults();
    }

    public function save(): void
    {
        $this->validate($this->rules(), $this->messages());

        DB::transaction(function () {
            $poll = $this->pollId
                ? Poll::query()->lockForUpdate()->findOrFail($this->pollId)
                : new Poll();

            if (! $poll->exists) {
                $poll->created_by_user_id = (int) Auth::id();
                $poll->status = PollStatus::Draft;
            }

            $poll->question = $this->question;
            $poll->menu_label = $this->menuLabel;
            $poll->visibility = PollVisibility::from($this->visibility);
            $poll->starts_at = $this->startsAt;
            $poll->ends_at = $this->endsAt;
            $poll->save();

            $this->pollId = $poll->id;
            $this->selectedPollId = $poll->id;

            $existingOptionIds = $poll->options()->pluck('id')->all();
            $incomingOptionIds = collect($this->options)->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
            $removedOptionIds = array_values(array_diff($existingOptionIds, $incomingOptionIds));

            if (! empty($removedOptionIds)) {
                $hasVotes = PollVote::query()->whereIn('poll_option_id', $removedOptionIds)->exists();

                if ($hasVotes) {
                    throw ValidationException::withMessages([
                        'options' => 'Antwortmöglichkeiten mit bereits abgegebenen Stimmen können nicht entfernt werden.',
                    ]);
                }

                PollOption::query()->whereIn('id', $removedOptionIds)->delete();
            }

            foreach (array_values($this->options) as $index => $optionData) {
                $label = trim((string) ($optionData['label'] ?? ''));
                $imageUrl = $optionData['image_url'] ?? null;
                $linkUrl = $optionData['link_url'] ?? null;

                /** @var PollOption $option */
                $option = isset($optionData['id'])
                    ? PollOption::query()->where('poll_id', $poll->id)->findOrFail((int) $optionData['id'])
                    : new PollOption(['poll_id' => $poll->id]);

                $option->label = $label;
                $option->image_url = $imageUrl ?: null;
                $option->link_url = $linkUrl ?: null;
                $option->sort_order = $index;
                $option->save();

                $this->options[$index]['id'] = $option->id;
            }

            $poll->load('options');
            $this->status = $poll->status->value;
        });

        $this->refreshResults();
        session()->flash('success', 'Umfrage gespeichert.');
    }

    public function activate(): void
    {
        if (! $this->pollId) {
            throw ValidationException::withMessages([
                'poll' => 'Bitte speichere die Umfrage zuerst.',
            ]);
        }

        DB::transaction(function () {
            $poll = Poll::query()->lockForUpdate()->findOrFail($this->pollId);

            if ($poll->ends_at && now()->gt($poll->ends_at)) {
                throw ValidationException::withMessages([
                    'poll' => 'Die Umfrage ist bereits beendet und kann nicht aktiviert werden.',
                ]);
            }

            $otherActive = Poll::query()
                ->lockForUpdate()
                ->where('status', PollStatus::Active)
                ->where('id', '!=', $poll->id)
                ->exists();

            if ($otherActive) {
                throw ValidationException::withMessages([
                    'poll' => 'Es ist bereits eine andere Umfrage aktiv. Bitte archiviere diese zuerst.',
                ]);
            }

            if ($poll->options()->count() < 1) {
                throw ValidationException::withMessages([
                    'poll' => 'Eine Umfrage benötigt mindestens eine Antwortmöglichkeit.',
                ]);
            }

            $poll->status = PollStatus::Active;
            $poll->activated_at = now();
            $poll->archived_at = null;
            $poll->save();

            $this->status = $poll->status->value;
        });

        $this->refreshResults();
        session()->flash('success', 'Umfrage aktiviert.');
    }

    public function archive(): void
    {
        if (! $this->pollId) {
            return;
        }

        DB::transaction(function () {
            $poll = Poll::query()->lockForUpdate()->findOrFail($this->pollId);
            $poll->status = PollStatus::Archived;
            $poll->archived_at = now();
            $poll->save();

            $this->status = $poll->status->value;
        });

        $this->refreshResults();
        session()->flash('success', 'Umfrage archiviert.');
    }

    private function refreshResults(): void
    {
        if (! $this->pollId) {
            $this->chartData = [];
            return;
        }

        $poll = Poll::query()->with('options')->find($this->pollId);

        if (! $poll) {
            $this->chartData = [];
            return;
        }

        $this->chartData = $poll->buildChartData();

        $this->dispatch('poll-results-updated', data: $this->chartData);
    }

    private function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:2000'],
            'menuLabel' => ['required', 'string', 'max:80'],
            'visibility' => ['required', Rule::in([PollVisibility::Internal->value, PollVisibility::Public->value])],
            'startsAt' => ['required', 'date'],
            'endsAt' => ['required', 'date', 'after:startsAt'],
            'options' => ['required', 'array', 'min:1', 'max:13'],
            'options.*.label' => ['required', 'string', 'max:160'],
            'options.*.image_url' => ['nullable', 'url', 'max:2048'],
            'options.*.link_url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    private function messages(): array
    {
        return [
            'options.*.label.required' => 'Bitte gib für jede Antwortmöglichkeit einen Text an.',
        ];
    }

    public function render()
    {
        $polls = Poll::query()
            ->orderForAdminIndex()
            ->get(['id', 'question', 'status']);

        return view('livewire.umfragen.umfrage-verwaltung', [
            'polls' => $polls,
        ])->layout('layouts.app', [
            'title' => 'Umfrage verwalten',
        ]);
    }
}
