<x-app-layout>
    <x-member-page class="max-w-3xl">
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
            <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-6">Neue Hörbuchfolge</h2>

            <form action="{{ route('hoerbuecher.store') }}" method="POST">
                @csrf

                <x-forms.text-field
                    name="title"
                    label="Titel"
                    :value="old('title')"
                    required
                    class="mb-4"
                />

                <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <x-forms.text-field
                        name="episode_number"
                        label="Folgenummer"
                        :value="old('episode_number')"
                        required
                    />

                    <x-forms.select-field
                        name="status"
                        label="Status"
                        :options="collect($statuses)->mapWithKeys(fn($s) => [$s => $s])"
                        :value="old('status')"
                        placeholder="-- Status wählen --"
                        required
                    />

                    <x-forms.text-field
                        name="planned_release_date"
                        label="Ziel-EVT"
                        :value="old('planned_release_date')"
                        placeholder="JJJJ, MM.JJJJ oder TT.MM.JJJJ"
                        required
                    />
                </div>

                <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <x-forms.text-field
                        name="author"
                        label="Autor"
                        :value="old('author')"
                        required
                    />

                    <x-forms.select-field
                        name="responsible_user_id"
                        label="Verantwortlicher Bearbeiter"
                        :options="$users->pluck('name', 'id')"
                        :value="old('responsible_user_id')"
                        placeholder="-- Mitglied wählen --"
                    />

                    <x-forms.number-field
                        name="progress"
                        label="Fortschritt (%)"
                        :value="old('progress', 0)"
                        :min="0"
                        :max="100"
                        required
                    />
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rollen</label>
                    <div
                        id="roles_list"
                        data-members-target="#members"
                        data-previous-speaker-url="{{ route('hoerbuecher.previous-speaker') }}"
                        data-role-index="{{ count(old('roles', [])) }}"
                    ></div>
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

                <x-forms.textarea-field
                    name="notes"
                    label="Anmerkungen"
                    :value="old('notes')"
                    rows="4"
                    class="mb-6"
                />

                <div class="flex justify-end">
                    <a href="{{ route('dashboard') }}" class="mr-3 inline-flex items-center px-4 py-2 bg-gray-300 dark:bg-gray-600 border border-transparent rounded-md font-semibold text-gray-800 dark:text-white hover:bg-gray-400 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">Abbrechen</a>
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-[#8B0116] dark:bg-[#C41E3A] border border-transparent rounded-md font-semibold text-white hover:bg-[#A50019] dark:hover:bg-[#D63A4D] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#8B0116] dark:focus:ring-[#FF6B81]">Speichern</button>
                </div>
            </form>
        </div>
    </x-member-page>
    @vite(['resources/js/hoerbuch-role-form.js'])
</x-app-layout>
