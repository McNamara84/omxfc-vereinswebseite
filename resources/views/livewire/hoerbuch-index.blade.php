<x-slot:head>
    <meta name="robots" content="noindex, nofollow">
</x-slot:head>
<x-member-page>
    {{-- session('status') wird zentral via flash-toast-bridge im Layout als Toast angezeigt --}}
    {{-- session('toast') wird zentral via flash-toast-bridge im Layout in window.toast() umgewandelt --}}
    <x-card shadow class="mb-6 flex justify-between items-center">
        <x-header title="Hörbuchfolgen" class="!mb-0" />
        @auth
            @if(auth()->user()->hasVorstandRole() || auth()->user()->isOwnerOfTeam('AG Fanhörbücher'))
                <x-button label="Neue Folge" link="{{ route('hoerbuecher.create') }}" wire:navigate icon="o-plus" class="btn-primary" />
            @endif
        @endauth
    </x-card>
    <div
        x-data="{
            statusFilter: '',
            typeFilter: '',
            yearFilter: '',
            roleNameFilter: '',
            showFilled: false,
            showUnfilled: false,
            hideReleased: true,
            onlyEpisodeId: null,
            todayMs: new Date().setHours(0, 0, 0, 0),

            isVisible(el) {
                const d = el.dataset;
                if (this.onlyEpisodeId && d.episodeId !== String(this.onlyEpisodeId)) return false;
                if (this.statusFilter && d.status !== this.statusFilter) return false;
                if (this.typeFilter && d.type !== this.typeFilter) return false;
                if (this.yearFilter && (d.year || '') !== this.yearFilter) return false;
                if (this.roleNameFilter) {
                    try { if (!JSON.parse(d.roleNames || '[]').includes(this.roleNameFilter)) return false; }
                    catch { return false; }
                }
                if (this.showFilled && d.rolesFilled !== '1') return false;
                if (this.showUnfilled && d.rolesFilled === '1') return false;
                if (this.hideReleased && d.plannedReleaseDate && new Date(d.plannedReleaseDate).getTime() < this.todayMs) return false;
                return true;
            },

            filterUnfilled(status = '') {
                this.onlyEpisodeId = null;
                this.statusFilter = status;
                this.roleNameFilter = '';
                this.showFilled = false;
                this.showUnfilled = true;
            },

            filterNextEvent(episodeId) {
                if (!episodeId) return;
                this.onlyEpisodeId = episodeId;
                this.showFilled = false;
                this.showUnfilled = false;
                this.statusFilter = '';
                this.roleNameFilter = '';
            },
        }"
    >
    <x-card shadow class="mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-stat
                id="card-unfilled-roles"
                data-unfilled-roles="{{ $this->totalUnfilledRoles }}"
                title="Unbesetzte Rollen"
                :value="$this->totalUnfilledRoles"
                icon="o-user-minus"
                class="cursor-pointer hover:bg-base-200"
                @click="filterUnfilled()"
            />
            <div
                id="card-next-event"
                data-episode-id="{{ $this->nextEpisode?->id }}"
                data-days-left="{{ $this->daysUntilNextEvt }}"
                class="cursor-pointer"
                @click="filterNextEvent('{{ $this->nextEpisode?->id }}')"
            >
                @if($this->nextEpisode)
                    <x-stat
                        title="Tage bis {{ $this->nextEpisode->title }} veröffentlicht wird ({{ $this->nextEpisode->planned_release_date_parsed->format('d.m.Y') }})"
                        :value="$this->daysUntilNextEvt"
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
                data-open-episodes="{{ $this->episodesWithUnassignedRoles }}"
                title="Folgen mit offenen Rollen"
                :value="$this->episodesWithUnassignedRoles"
                icon="o-microphone"
                class="cursor-pointer hover:bg-base-200"
                @click="filterUnfilled('Rollenbesetzung')"
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
                <select id="status-filter" x-model="statusFilter" class="select select-bordered select-sm">
                    <option value="">Alle Status</option>
                    @foreach($this->statuses as $status)
                        <option value="{{ $status }}">{{ $status }}</option>
                    @endforeach
                </select>
                <select id="type-filter" x-model="typeFilter" class="select select-bordered select-sm">
                    <option value="">Alle Typen</option>
                    <option value="regular">Reguläre Folge</option>
                    <option value="se">Sonderedition</option>
                </select>
                <select id="year-filter" x-model="yearFilter" class="select select-bordered select-sm">
                    <option value="">Alle Jahre</option>
                    @foreach($this->years as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
                <div class="flex flex-col">
                    <label for="role-name-filter" class="sr-only">Nach Rolle filtern</label>
                    <select
                        id="role-name-filter"
                        x-model="roleNameFilter"
                        class="select select-bordered select-sm"
                        aria-label="Hörbuchfolgen nach Rolle filtern"
                    >
                        <option value="">Alle Rollen</option>
                        @foreach($this->roleNames as $roleName)
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
                <label class="label cursor-pointer gap-2">
                    <input type="checkbox" x-model="showFilled" x-bind:disabled="showUnfilled" data-filter="roles" class="checkbox checkbox-sm" />
                    <span class="label-text">Besetzt</span>
                </label>
                <label class="label cursor-pointer gap-2">
                    <input type="checkbox" x-model="showUnfilled" x-bind:disabled="showFilled" data-filter="roles-unfilled" class="checkbox checkbox-sm" />
                    <span class="label-text">Unbesetzt</span>
                </label>
                <label class="label cursor-pointer gap-2">
                    <input
                        type="checkbox"
                        x-model="hideReleased"
                        checked
                        data-filter="hide-released"
                        aria-describedby="hide-released-hint"
                        class="checkbox checkbox-sm"
                    />
                    <span class="label-text">Unveröffentlicht</span>
                </label>
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
                        @if(auth()->user()?->hasVorstandRole() || auth()->user()?->isMemberOfTeam('AG Fanhörbücher'))
                            <th>Bemerkungen</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->episodes as $episode)
                        <tr
                            class="cursor-pointer hover:bg-base-200"
                            role="button"
                            tabindex="0"
                            @click="window.location.href = '{{ route('hoerbuecher.show', $episode) }}'"
                            @keydown.enter="window.location.href = '{{ route('hoerbuecher.show', $episode) }}'"
                            @keydown.space.prevent="window.location.href = '{{ route('hoerbuecher.show', $episode) }}'"
                            data-href="{{ route('hoerbuecher.show', $episode) }}"
                            data-status="{{ $episode->status->value }}"
                            data-type="{{ $episode->episode_type }}"
                            data-roles-filled="{{ $episode->all_roles_filled ? '1' : '0' }}"
                            data-year="{{ $episode->release_year ?? '' }}"
                            data-episode-id="{{ $episode->id }}"
                            data-planned-release-date="{{ optional($episode->planned_release_date_parsed)->toDateString() }}"
                            data-role-names='@json($episode->roles->pluck('name')->filter()->values())'
                            x-show="isVisible($el)"
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
                                    aria-label="Fortschritt der Folge: {{ $episode->status->value }}, {{ $episode->progress }}% abgeschlossen">
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
                            @if(auth()->user()?->hasVorstandRole() || auth()->user()?->isMemberOfTeam('AG Fanhörbücher'))
                                <td class="px-4 py-2">{{ $episode->notes }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ (auth()->user()?->hasVorstandRole() || auth()->user()?->isMemberOfTeam('AG Fanhörbücher')) ? 6 : 5 }}" class="px-4 py-2 text-center text-base-content">Keine Hörbuchfolgen vorhanden.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
    </div>
</x-member-page>
