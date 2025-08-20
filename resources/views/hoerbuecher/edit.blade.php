<x-app-layout>
    <x-member-page class="max-w-3xl">
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
            <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-6">Hörbuchfolge bearbeiten</h2>

            <form action="{{ route('hoerbuecher.update', $episode) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="mb-4">
                    <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Titel</label>
                    <input type="text" name="title" id="title" value="{{ old('title', $episode->title) }}" required class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                    @error('title')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="episode_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Folgenummer</label>
                        <input type="text" name="episode_number" id="episode_number" value="{{ old('episode_number', $episode->episode_number) }}" required class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                        @error('episode_number')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                        <select name="status" id="status" required class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                            <option value="">-- Status wählen --</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" {{ old('status', $episode->status) === $status ? 'selected' : '' }}>{{ $status }}</option>
                            @endforeach
                        </select>
                        @error('status')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="planned_release_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Ziel-EVT</label>
                        <input type="text" name="planned_release_date" id="planned_release_date" value="{{ old('planned_release_date', $episode->planned_release_date) }}" required placeholder="JJJJ, MM.JJJJ oder TT.MM.JJJJ" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                        @error('planned_release_date')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="author" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Autor</label>
                        <input type="text" name="author" id="author" value="{{ old('author', $episode->author) }}" required class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                        @error('author')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="responsible_user_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Verantwortlicher Bearbeiter</label>
                        <select name="responsible_user_id" id="responsible_user_id" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                            <option value="">-- Mitglied wählen --</option>
                            @foreach($users as $member)
                                <option value="{{ $member->id }}" {{ old('responsible_user_id', $episode->responsible_user_id) == $member->id ? 'selected' : '' }}>{{ $member->name }}</option>
                            @endforeach
                        </select>
                        @error('responsible_user_id')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="progress" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fortschritt (%)</label>
                        <input type="number" name="progress" id="progress" value="{{ old('progress', $episode->progress) }}" min="0" max="100" required class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                        @error('progress')
                            <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rollen</label>
                    <div id="roles_list">
                        @foreach(old('roles', $episode->roles->toArray()) as $i => $role)
                            <div class="grid grid-cols-5 gap-2 mb-2 items-start role-row">
                                <input type="text" name="roles[{{ $i }}][name]" value="{{ $role['name'] ?? '' }}" placeholder="Rolle" class="col-span-1 w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50" />
                                <input type="text" name="roles[{{ $i }}][description]" value="{{ $role['description'] ?? '' }}" placeholder="Beschreibung" class="col-span-1 w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50" />
                                <input type="number" name="roles[{{ $i }}][takes]" value="{{ $role['takes'] ?? 0 }}" min="0" placeholder="Takes" class="col-span-1 w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50" />
                                <div class="col-span-1">
                                    <input type="text" name="roles[{{ $i }}][member_name]" value="{{ $role['speaker_name'] ?? ($role['member_name'] ?? '') }}" list="members" placeholder="Sprecher" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50" />
                                    <input type="hidden" name="roles[{{ $i }}][member_id]" value="{{ $role['user_id'] ?? ($role['member_id'] ?? '') }}" />
                                    @php($prev = $previousSpeakers[$role['name'] ?? ''] ?? null)
                                    <div class="text-xs text-gray-500 mt-1 previous-speaker">
                                        {{ $prev ? 'Bisheriger Sprecher: ' . $prev : '' }}
                                    </div>
                                </div>
                                <button type="button" class="col-span-1 text-red-600" aria-label="Remove">&times;</button>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" id="add_role" class="mt-2 inline-flex items-center px-2 py-1 bg-gray-200 dark:bg-gray-600 rounded">Rolle hinzufügen</button>
                    <datalist id="members">
                        @foreach($users as $member)
                            <option data-id="{{ $member->id }}" value="{{ $member->name }}"></option>
                        @endforeach
                    </datalist>
                    @error('roles')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-6">
                    <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Anmerkungen</label>
                    <textarea name="notes" id="notes" rows="4" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">{{ old('notes', $episode->notes) }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex justify-end">
                    <a href="{{ route('hoerbuecher.index') }}" class="mr-3 inline-flex items-center px-4 py-2 bg-gray-300 dark:bg-gray-600 border border-transparent rounded-md font-semibold text-gray-800 dark:text-white hover:bg-gray-400 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">Abbrechen</a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-[#8B0116] dark:bg-[#C41E3A] border border-transparent rounded-md font-semibold text-white hover:bg-[#A50019] dark:hover:bg-[#D63A4D] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#8B0116] dark:focus:ring-[#FF6B81]">Aktualisieren</button>
                </div>
            </form>
        </div>
    </x-member-page>
    <script>
        const members = Array.from(document.querySelectorAll('#members option')).map(o => ({id: o.dataset.id, name: o.value}));
        const previousSpeakerUrl = "{{ route('hoerbuecher.previous-speaker') }}";
        let roleIndex = document.querySelectorAll('#roles_list .role-row').length;

        function debounce(fn, delay = 300) {
            let timeout;
            return (...args) => {
                clearTimeout(timeout);
                timeout = setTimeout(() => fn(...args), delay);
            };
        }

        function bindRoleRow(row) {
            const memberInput = row.querySelector('input[list]');
            const hidden = row.querySelector('input[type="hidden"]');
            const roleNameInput = row.querySelector('input[name$="[name]"]');
            const hint = row.querySelector('.previous-speaker');

            memberInput.addEventListener('input', e => {
                const option = members.find(m => m.name === e.target.value);
                hidden.value = option ? option.id : '';
            });

            function updateHint() {
                const name = roleNameInput.value.trim();
                if (!name) {
                    hint.textContent = '';
                    return;
                }
                fetch(`${previousSpeakerUrl}?name=${encodeURIComponent(name)}`)
                    .then(r => {
                        if (r.status === 401) throw new Error('unauthorized');
                        if (!r.ok) throw new Error('request-failed');
                        return r.json();
                    })
                    .then(data => {
                        hint.textContent = data.speaker ? `Bisheriger Sprecher: ${data.speaker}` : '';
                    })
                    .catch(err => {
                        hint.textContent = err.message === 'unauthorized'
                            ? 'Nicht berechtigt'
                            : 'Fehler beim Laden des bisherigen Sprechers';
                    });
            }

            const debouncedHint = debounce(updateHint);
            roleNameInput.addEventListener('input', debouncedHint);
            roleNameInput.addEventListener('blur', updateHint);

            row.querySelector('button').addEventListener('click', () => row.remove());
            updateHint();
        }

        document.querySelectorAll('#roles_list .role-row').forEach(bindRoleRow);

        function addRole() {
            const container = document.getElementById('roles_list');
            const wrapper = document.createElement('div');
            wrapper.className = 'grid grid-cols-5 gap-2 mb-2 items-start role-row';
            wrapper.innerHTML = `
                <input type="text" name="roles[${roleIndex}][name]" placeholder="Rolle" class="col-span-1 w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50" />
                <input type="text" name="roles[${roleIndex}][description]" placeholder="Beschreibung" class="col-span-1 w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50" />
                <input type="number" name="roles[${roleIndex}][takes]" min="0" placeholder="Takes" class="col-span-1 w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50" />
                <div class="col-span-1">
                    <input type="text" name="roles[${roleIndex}][member_name]" list="members" placeholder="Sprecher" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50" />
                    <input type="hidden" name="roles[${roleIndex}][member_id]" />
                    <div class="text-xs text-gray-500 mt-1 previous-speaker"></div>
                </div>
                <button type="button" class="col-span-1 text-red-600" aria-label="Remove">&times;</button>
            `;
            bindRoleRow(wrapper);
            container.appendChild(wrapper);
            roleIndex++;
        }

        document.getElementById('add_role').addEventListener('click', addRole);
    </script>
</x-app-layout>
