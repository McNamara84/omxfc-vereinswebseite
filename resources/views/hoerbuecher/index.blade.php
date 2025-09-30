<x-app-layout>
    <x-member-page>
        @if(session('status'))
            <div class="mb-4 p-4 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-600 text-green-800 dark:text-green-200 rounded">
                {{ session('status') }}
            </div>
        @endif
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6 flex justify-between items-center">
            <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81]">Hörbuchfolgen</h2>
            @if(auth()->user()->hasVorstandRole() || auth()->user()->isOwnerOfTeam('AG Fanhörbücher'))
                <a href="{{ route('hoerbuecher.create') }}" class="inline-flex items-center px-4 py-2 bg-[#8B0116] dark:bg-[#C41E3A] border border-transparent rounded-md font-semibold text-white hover:bg-[#A50019] dark:hover:bg-[#D63A4D] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#8B0116] dark:focus:ring-[#FF6B81]">
                    Neue Folge
                </a>
            @endif
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div id="card-unfilled-roles" data-unfilled-roles="{{ $totalUnfilledRoles }}" class="p-4 border border-gray-200 dark:border-gray-700 rounded cursor-pointer text-center hover:bg-gray-100 dark:hover:bg-gray-700">
                    <div class="text-3xl font-bold text-gray-800 dark:text-gray-200">{{ $totalUnfilledRoles }}</div>
                    <div class="text-gray-600 dark:text-gray-400">Unbesetzte Rollen</div>
                </div>
                <div id="card-next-event" data-episode-id="{{ $nextEpisode?->id }}" data-days-left="{{ $daysUntilNextEvt }}" class="p-4 border border-gray-200 dark:border-gray-700 rounded cursor-pointer text-center hover:bg-gray-100 dark:hover:bg-gray-700">
                    @if($nextEpisode)
                        <div class="text-3xl font-bold text-gray-800 dark:text-gray-200">{{ $daysUntilNextEvt }}</div>
                        <div class="text-gray-600 dark:text-gray-400">
                            Tage bis {{ $nextEpisode->title }} veröffentlicht wird ({{ $nextEpisode->planned_release_date_parsed->format('d.m.Y') }})
                        </div>
                    @else
                        <div class="text-gray-600 dark:text-gray-400">Kein Termin</div>
                    @endif
                </div>
                <div id="card-open-episodes" data-open-episodes="{{ $openRolesEpisodes }}" class="p-4 border border-gray-200 dark:border-gray-700 rounded cursor-pointer text-center hover:bg-gray-100 dark:hover:bg-gray-700">
                    <div class="text-3xl font-bold text-gray-800 dark:text-gray-200">{{ $openRolesEpisodes }}</div>
                    <div class="text-gray-600 dark:text-gray-400">Folgen mit offenen Rollen</div>
                </div>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
            <div class="mb-4 flex flex-wrap gap-4" aria-label="Filter für die Hörbuchfolgen">
                <select id="status-filter" class="border-gray-300 dark:border-gray-600 rounded-md">
                    <option value="">Alle Status</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status }}">{{ $status }}</option>
                    @endforeach
                </select>
                <select id="type-filter" class="border-gray-300 dark:border-gray-600 rounded-md">
                    <option value="">Alle Typen</option>
                    <option value="regular">Reguläre Folge</option>
                    <option value="se">Sonderedition</option>
                </select>
                <select id="year-filter" class="border-gray-300 dark:border-gray-600 rounded-md">
                    <option value="">Alle Jahre</option>
                    @foreach($years as $year)
                        <option value="{{ $year }}">{{ $year }}</option>
                    @endforeach
                </select>
                <div class="flex flex-col">
                    <label for="role-name-filter" class="sr-only">Nach Rolle filtern</label>
                    <select
                        id="role-name-filter"
                        class="border-gray-300 dark:border-gray-600 rounded-md"
                        aria-label="Hörbuchfolgen nach Rolle filtern"
                    >
                        <option value="">Alle Rollen</option>
                        @foreach($roleNames as $roleName)
                            <option value="{{ $roleName }}">{{ $roleName }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="inline-flex items-center">
                    <input type="checkbox" id="roles-filter" class="form-checkbox">
                    <span class="ml-2">Besetzt</span>
                </label>
                <label class="inline-flex items-center">
                    <input type="checkbox" id="roles-unfilled-filter" class="form-checkbox">
                    <span class="ml-2">Unbesetzt</span>
                </label>
                <label class="inline-flex items-center">
                    <input
                        type="checkbox"
                        id="hide-released-filter"
                        class="form-checkbox"
                        checked
                        aria-describedby="hide-released-hint"
                    >
                    <span class="ml-2">
                        Veröffentlicht<span class="sr-only">e Folgen ausblenden</span>
                    </span>
                </label>
                <p id="hide-released-hint" class="sr-only">
                    Bereits veröffentlichte Folgen können angezeigt werden, indem der Filter deaktiviert wird.
                </p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead>
                        <tr>
                            <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Folge</th>
                            <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Titel</th>
                            <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Ziel-EVT</th>
                            <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Status & Fortschritt</th>
                            <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Rollenbesetzung</th>
                            <th class="px-4 py-2 text-left text-gray-800 dark:text-gray-200">Bemerkungen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($episodes as $episode)
                            <tr
                                class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
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
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $episode->episode_number }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $episode->title }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $episode->planned_release_date }}</td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">
                                    <span>{{ $episode->status->value }}</span>
                                    <div
                                        class="mt-1 w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4"
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
                                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4">
                                        <div class="h-4 rounded-full text-xs font-medium text-center leading-none text-white" style="width: {{ $episode->rolesFilledPercent() }}%; background-color: hsl({{ $episode->rolesHue() }}, 100%, 40%);">
                                            {{ $episode->roles_filled }}/{{ $episode->roles_total }}
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $episode->notes }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-2 text-center text-gray-700 dark:text-gray-300">Keine Hörbuchfolgen vorhanden.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @vite(['resources/js/hoerbuecher.js'])
    </x-member-page>
</x-app-layout>
