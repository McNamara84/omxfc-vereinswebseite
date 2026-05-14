<?php

namespace App\Livewire;

use App\Enums\MeetingRhythmType;
use App\Enums\Role;
use App\Models\Meeting;
use App\Services\MeetingScheduleService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Component;

class MeetingAdmin extends Component
{
    public ?int $editingId = null;

    public bool $showForm = false;

    public string $title = '';

    public string $slug = '';

    public string $zoom_url = '';

    public bool $is_active = true;

    public string $time_from = '';

    public string $time_to = '';

    public string $rhythm_type = 'monthly_nth_weekday';

    public string $interval_weeks = '2';

    public string $starts_on = '';

    public string $weekday = '1';

    public string $week_of_month = '1';

    public string $day_of_month = '';

    public string $rhythm_note = '';

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user || ! $user->hasAnyRole(Role::Admin, Role::Vorstand)) {
            abort(403);
        }

        $this->resetForm();
    }

    public function openForm(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->resetForm();
        $this->showForm = false;
    }

    public function edit(int $meetingId): void
    {
        $meeting = $this->findMeeting($meetingId);

        $this->editingId = $meeting->id;
        $this->title = $meeting->title;
        $this->slug = $meeting->slug;
        $this->zoom_url = $meeting->zoom_url ?? '';
        $this->is_active = $meeting->is_active;
        $this->time_from = $meeting->time_from ?? '';
        $this->time_to = $meeting->time_to ?? '';
        $this->rhythm_type = $meeting->rhythm_type->value;
        $this->interval_weeks = (string) ($meeting->interval_weeks ?? '2');
        $this->starts_on = $meeting->starts_on?->format('Y-m-d') ?? '';
        $this->weekday = (string) ($meeting->weekday ?? '1');
        $this->week_of_month = (string) ($meeting->week_of_month ?? '1');
        $this->day_of_month = (string) ($meeting->day_of_month ?? '');
        $this->rhythm_note = $meeting->rhythm_note ?? '';
        $this->showForm = true;
        $this->resetValidation();
    }

    public function save(): void
    {
        $this->slug = Str::slug(filled(trim($this->slug)) ? $this->slug : $this->title);

        $validated = $this->validate($this->rules(), $this->messages());
        $payload = $this->payloadFromValidated($validated);

        if ($this->editingId) {
            $this->findMeeting($this->editingId)->update($payload);
            session()->flash('success', 'Treffen erfolgreich aktualisiert.');
        } else {
            Meeting::create($payload + ['sort_order' => $this->nextSortOrder()]);
            session()->flash('success', 'Treffen erfolgreich angelegt.');
        }

        $this->closeForm();
    }

    public function toggleActive(int $meetingId): void
    {
        $meeting = $this->findMeeting($meetingId);

        $meeting->update([
            'is_active' => ! $meeting->is_active,
            'updated_by' => Auth::id(),
        ]);
    }

    public function delete(int $meetingId): void
    {
        DB::transaction(function () use ($meetingId) {
            $this->findMeeting($meetingId)->delete();
            $this->recompactSortOrder();
        });

        session()->flash('success', 'Treffen wurde gelöscht.');
    }

    public function moveUp(int $meetingId): void
    {
        $meeting = $this->findMeeting($meetingId);
        $meetingAbove = Meeting::query()
            ->where('sort_order', '<', $meeting->sort_order)
            ->orderByDesc('sort_order')
            ->first();

        if (! $meetingAbove) {
            return;
        }

        DB::transaction(function () use ($meeting, $meetingAbove) {
            $currentSortOrder = $meeting->sort_order;

            $meeting->update([
                'sort_order' => $meetingAbove->sort_order,
                'updated_by' => Auth::id(),
            ]);

            $meetingAbove->update([
                'sort_order' => $currentSortOrder,
                'updated_by' => Auth::id(),
            ]);
        });
    }

    public function moveDown(int $meetingId): void
    {
        $meeting = $this->findMeeting($meetingId);
        $meetingBelow = Meeting::query()
            ->where('sort_order', '>', $meeting->sort_order)
            ->orderBy('sort_order')
            ->first();

        if (! $meetingBelow) {
            return;
        }

        DB::transaction(function () use ($meeting, $meetingBelow) {
            $currentSortOrder = $meeting->sort_order;

            $meeting->update([
                'sort_order' => $meetingBelow->sort_order,
                'updated_by' => Auth::id(),
            ]);

            $meetingBelow->update([
                'sort_order' => $currentSortOrder,
                'updated_by' => Auth::id(),
            ]);
        });
    }

    public function render()
    {
        $scheduleService = $this->scheduleService();
        $meetings = Meeting::query()
            ->ordered()
            ->get()
            ->map(function (Meeting $meeting) use ($scheduleService) {
                $meeting->display_rhythm = $scheduleService->describe($meeting);
                $meeting->next_occurrence = $scheduleService->nextOccurrence($meeting);

                return $meeting;
            });

        return view('livewire.meeting-admin', [
            'meetings' => $meetings,
            'publicMeetingsUrl' => route('meetings'),
            'rhythmTypeOptions' => $this->rhythmTypeOptions(),
            'weekdayOptions' => $this->weekdayOptions(),
            'weekOfMonthOptions' => $this->weekOfMonthOptions(),
        ])->layout('layouts.admin', [
            'title' => 'Treffen - Admin',
        ]);
    }

    protected function rules(): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('meetings', 'slug')->ignore($this->editingId),
            ],
            'zoom_url' => ['nullable', 'url:https', 'max:2048'],
            'is_active' => ['boolean'],
            'time_from' => ['nullable', 'date_format:H:i', 'required_with:time_to'],
            'time_to' => ['nullable', 'date_format:H:i', 'after:time_from'],
            'rhythm_type' => ['required', Rule::enum(MeetingRhythmType::class)],
            'rhythm_note' => ['nullable', 'string', 'max:500'],
        ];

        return match ($this->rhythm_type) {
            MeetingRhythmType::MonthlyNthWeekday->value => $rules + [
                'weekday' => ['required', 'integer', 'between:1,7'],
                'week_of_month' => ['required', 'integer', 'between:1,5'],
            ],
            MeetingRhythmType::MonthlyDayOfMonth->value => $rules + [
                'day_of_month' => ['required', 'integer', 'between:1,31'],
            ],
            MeetingRhythmType::EveryNWeeks->value => $rules + [
                'interval_weeks' => ['required', 'integer', 'between:1,52'],
                'starts_on' => ['required', 'date'],
            ],
            MeetingRhythmType::NoteOnly->value => $rules + [
                'rhythm_note' => ['required', 'string', 'max:500'],
            ],
            default => $rules,
        };
    }

    protected function messages(): array
    {
        return [
            'title.required' => 'Bitte gib einen Titel für das Treffen an.',
            'slug.required' => 'Bitte gib einen technischen Schlüssel oder Titel an.',
            'slug.regex' => 'Der technische Schlüssel darf nur Kleinbuchstaben, Zahlen und Bindestriche enthalten.',
            'slug.unique' => 'Dieser technische Schlüssel ist bereits vergeben.',
            'zoom_url.url' => 'Bitte gib eine gültige HTTPS-URL für Zoom an.',
            'time_from.date_format' => 'Die Startzeit muss im Format HH:MM angegeben werden.',
            'time_to.date_format' => 'Die Endzeit muss im Format HH:MM angegeben werden.',
            'time_to.after' => 'Die Endzeit muss nach der Startzeit liegen.',
            'weekday.required' => 'Bitte wähle einen Wochentag aus.',
            'week_of_month.required' => 'Bitte wähle die Woche im Monat aus.',
            'day_of_month.required' => 'Bitte gib einen Monatstag an.',
            'interval_weeks.required' => 'Bitte gib den Wochenabstand an.',
            'starts_on.required' => 'Bitte gib ein Startdatum an.',
            'rhythm_note.required' => 'Bitte gib einen Hinweis zum Rhythmus an.',
        ];
    }

    protected function payloadFromValidated(array $validated): array
    {
        $rhythmType = $validated['rhythm_type'] instanceof MeetingRhythmType
            ? $validated['rhythm_type']->value
            : $validated['rhythm_type'];

        $payload = [
            'title' => trim($validated['title']),
            'slug' => $validated['slug'],
            'zoom_url' => $validated['zoom_url'] ?: null,
            'is_active' => (bool) $validated['is_active'],
            'time_from' => $validated['time_from'] ?: null,
            'time_to' => $validated['time_to'] ?: null,
            'rhythm_type' => $rhythmType,
            'interval_weeks' => null,
            'starts_on' => null,
            'weekday' => null,
            'week_of_month' => null,
            'day_of_month' => null,
            'rhythm_note' => $validated['rhythm_note'] ?: null,
            'updated_by' => Auth::id(),
        ];

        return match ($rhythmType) {
            MeetingRhythmType::MonthlyNthWeekday->value => array_merge($payload, [
                'weekday' => (int) $validated['weekday'],
                'week_of_month' => (int) $validated['week_of_month'],
            ]),
            MeetingRhythmType::MonthlyDayOfMonth->value => array_merge($payload, [
                'day_of_month' => (int) $validated['day_of_month'],
            ]),
            MeetingRhythmType::EveryNWeeks->value => array_merge($payload, [
                'interval_weeks' => (int) $validated['interval_weeks'],
                'starts_on' => $validated['starts_on'],
            ]),
            MeetingRhythmType::NoteOnly->value => $payload,
            default => $payload,
        };
    }

    protected function findMeeting(int $meetingId): Meeting
    {
        return Meeting::query()->findOrFail($meetingId);
    }

    protected function nextSortOrder(): int
    {
        return ((int) Meeting::query()->max('sort_order')) + 10;
    }

    protected function recompactSortOrder(): void
    {
        $meetings = Meeting::query()->ordered()->get(['id']);

        foreach ($meetings as $index => $meeting) {
            Meeting::query()->whereKey($meeting->id)->update([
                'sort_order' => ($index + 1) * 10,
            ]);
        }
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->title = '';
        $this->slug = '';
        $this->zoom_url = '';
        $this->is_active = true;
        $this->time_from = '';
        $this->time_to = '';
        $this->rhythm_type = MeetingRhythmType::MonthlyNthWeekday->value;
        $this->interval_weeks = '2';
        $this->starts_on = now()->format('Y-m-d');
        $this->weekday = '1';
        $this->week_of_month = '1';
        $this->day_of_month = '';
        $this->rhythm_note = '';
        $this->resetValidation();
    }

    protected function scheduleService(): MeetingScheduleService
    {
        return app(MeetingScheduleService::class);
    }

    protected function rhythmTypeOptions(): array
    {
        return [
            MeetingRhythmType::MonthlyNthWeekday->value => 'Monatlich an einem bestimmten Wochentag',
            MeetingRhythmType::MonthlyDayOfMonth->value => 'Monatlich an einem festen Tag',
            MeetingRhythmType::EveryNWeeks->value => 'Alle X Wochen ab Startdatum',
            MeetingRhythmType::NoteOnly->value => 'Nur als Hinweistext',
        ];
    }

    protected function weekdayOptions(): array
    {
        return [
            1 => 'Montag',
            2 => 'Dienstag',
            3 => 'Mittwoch',
            4 => 'Donnerstag',
            5 => 'Freitag',
            6 => 'Samstag',
            7 => 'Sonntag',
        ];
    }

    protected function weekOfMonthOptions(): array
    {
        return [
            1 => '1. Woche',
            2 => '2. Woche',
            3 => '3. Woche',
            4 => '4. Woche',
            5 => '5. Woche',
        ];
    }
}
