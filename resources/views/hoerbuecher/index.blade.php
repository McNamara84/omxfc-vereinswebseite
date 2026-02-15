<x-app-layout>
    <x-member-page>
        @if(session('status'))
            <x-alert icon="o-check-circle" class="alert-success mb-4">
                {{ session('status') }}
            </x-alert>
        @endif
        <x-card shadow class="mb-6 flex justify-between items-center">
            <x-header title="Hörbuchfolgen" class="!mb-0" />
            @if(auth()->user()->hasVorstandRole() || auth()->user()->isOwnerOfTeam('AG Fanhörbücher'))
                <x-button label="Neue Folge" link="{{ route('hoerbuecher.create') }}" icon="o-plus" class="btn-primary" />
            @endif
        </x-card>
        <x-card shadow class="mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <x-stat
                    id="card-unfilled-roles"
                    data-unfilled-roles="{{ $totalUnfilledRoles }}"
                    title="Unbesetzte Rollen"
                    :value="$totalUnfilledRoles"
                    icon="o-user-minus"
                    class="cursor-pointer hover:bg-base-200"
                />
                <div id="card-next-event" data-episode-id="{{ $nextEpisode?->id }}" data-days-left="{{ $daysUntilNextEvt }}" class="cursor-pointer">
                    @if($nextEpisode)
                        <x-stat
                            title="Tage bis {{ $nextEpisode->title }} veröffentlicht wird ({{ $nextEpisode->planned_release_date_parsed->format('d.m.Y') }})"
                            :value="$daysUntilNextEvt"
                            icon="o-calendar"
                            class="hover:bg-base-200"
                        />
                    @else
                        <x-stat
                            title="Kein Termin"
                            value="–"
                            icon="o-calendar"
                            class="hover:bg-base-200"
                        />
                    @endif
                </div>
                <x-stat
                    id="card-open-episodes"
                    data-open-episodes="{{ $episodesWithUnassignedRoles }}"
                    title="Folgen mit offenen Rollen"
                    :value="$episodesWithUnassignedRoles"
                    icon="o-microphone"
                    class="cursor-pointer hover:bg-base-200"
                />
            </div>
        </x-card>
        <x-card shadow>
            <div class="mb-4 space-y-4" aria-label="Filter für die Hörbuchfolgen">
                <div
                    id="episode-select-filters"
                    class="flex flex-wrap gap-4 items-end"
                    role="group"
                    aria-label="Auswahlfilter"
                >
                    <select id="status-filter" class="select select-bordered select-sm">
                        <option value="">Alle Status</option>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}">{{ $status }}</option>
                        @endforeach
                    </select>
                    <select id="type-filter" class="select select-bordered select-sm">
                        <option value="">Alle Typen</option>
                        <option value="regular">Reguläre Folge</option>
                        <option value="se">Sonderedition</option>
                    </select>
                    <select id="year-filter" class="select select-bordered select-sm">
                        <option value="">Alle Jahre</option>
                        @foreach($years as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                        @endforeach
                    </select>
                    <div class="flex flex-col">
                        <label for="role-name-filter" class="sr-only">Nach Rolle filtern</label>
                        <select
                            id="role-name-filter"
                            class="select select-bordered select-sm"
                            aria-label="Hörbuchfolgen nach Rolle filtern"
                        >
                            <option value="">Alle Rollen</option>
                            @foreach($roleNames as $roleName)
                                <option value="{{ $roleName }}">{{ $roleName }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <fieldset
                    id="episode-checkbox-filters"
                    class="flex flex-wrap gap-4 border-0 p-0 m-0"
                    aria-describedby="checkbox-filter-hint"
                >
                    <legend class="text-sm font-semibold text-base-content w-full mb-2">Checkbox-Filter</legend>
                    <p id="checkbox-filter-hint" class="sr-only">
                        Aktiviere einen oder mehrere Checkbox-Filter, um unveröffentlichte Folgen oder Episoden mit vollständig besetzten Rollen einzublenden.
                    </p>
                    <x-checkbox id="roles-filter" label="Besetzt" class="checkbox-sm" />
                    <x-checkbox id="roles-unfilled-filter" label="Unbesetzt" class="checkbox-sm" />
                    <x-checkbox
                        id="hide-released-filter"
                        label="Unveröffentlicht"
                        checked
                        aria-describedby="hide-released-hint"
                        class="checkbox-sm"
                    />
                </fieldset>
                <p id="hide-released-hint" class="sr-only">
                    Unveröffentlichte Folgen werden angezeigt, solange der Filter aktiv ist. Deaktiviere den Filter, um bereits veröffentlichte Folgen einzublenden.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Folge</th>
                            <th>Titel</th>
                            <th>Ziel-EVT</th>
                            <th>Status & Fortschritt</th>
                            <th>Rollenbesetzung</th>
                            <th>Bemerkungen</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($episodes as $episode)
                            <tr
                                class="cursor-pointer hover:bg-base-200"
                                role="button"
                                tabindex="0"
                                data-href="{{ route('hoerbuecher.show', $episode) }}"
                                data-status="{{ $episode->status->value }}"
                                data-type="{{ $episode->episode_type }}"
                                data-roles-filled="{{ $episode->all_roles_filled ? '1' : '0' }}"
                                data-year="{{ $episode->release_year ?? '' }}"
                                data-episode-id="{{ $episode->id }}"
                                data-planned-release-date="{{ optional($episode->planned_release_date_parsed)->toDateString() }}"
                                data-role-names='@json($episode->roles->pluck('name')->filter()->values())'
                            >
                                <td class="px-4 py-2">{{ $episode->episode_number }}</td>
                                <td class="px-4 py-2">{{ $episode->title }}</td>
                                <td class="px-4 py-2">{{ $episode->planned_release_date }}</td>
                                <td class="px-4 py-2">
                                    <span>{{ $episode->status->value }}</span>
                                    <div
                                        class="mt-1 w-full bg-base-200 rounded-full h-4"
                                        role="progressbar"
                                        aria-valuenow="{{ $episode->progress }}"
                                        aria-valuemin="0"
                                        aria-valuemax="100"
                                        aria-label="Episode progress: {{ $episode->status->value }}, {{ $episode->progress }}% complete">
                                        {{-- Map 0–100% progress to a hue range of 0–120 (red → green). --}}
                                        <div class="h-4 rounded-full text-xs font-medium text-center leading-none text-white" style="width: {{ $episode->progress }}%; background-color: hsl({{ $episode->progressHue() }}, 100%, 40%);">
                                            {{ $episode->progress }}%
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2">
                                    <div class="w-full bg-base-200 rounded-full h-4">
                                        <div class="h-4 rounded-full text-xs font-medium text-center leading-none text-white" style="width: {{ $episode->rolesFilledPercent() }}%; background-color: hsl({{ $episode->rolesHue() }}, 100%, 40%);">
                                            {{ $episode->roles_filled }}/{{ $episode->roles_total }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2">{{ $episode->notes }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-2 text-center text-base-content">Keine Hörbuchfolgen vorhanden.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
        @vite(['resources/js/hoerbuecher.js'])
    </x-member-page>
</x-app-layout>
