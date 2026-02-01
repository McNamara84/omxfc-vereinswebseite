<x-app-layout>
    <x-member-page class="max-w-3xl">
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
                <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FF6B81] mb-6">Neue Challenge erstellen</h2>

                <form action="{{ route('todos.store') }}" method="POST">
                    @csrf

                    <x-forms.text-field
                        name="title"
                        label="Titel"
                        :value="old('title')"
                        required
                        class="mb-4"
                    />

                    <x-forms.select-field
                        name="category_id"
                        label="Kategorie"
                        :options="$categories->pluck('name', 'id')"
                        :value="old('category_id')"
                        placeholder="-- Kategorie wählen --"
                        required
                        class="mb-4"
                    />

                    <x-forms.textarea-field
                        name="description"
                        label="Beschreibung"
                        :value="old('description')"
                        rows="4"
                        class="mb-4"
                    />

                    <x-forms.number-field
                        name="points"
                        label="Baxx"
                        :value="old('points', 1)"
                        :min="1"
                        :max="1000"
                        help="Wie viele Baxx erhält das Mitglied für die Erledigung dieser Challenge?"
                        required
                        class="mb-6"
                    />

                    <div class="flex justify-end">
                        <a href="{{ route('todos.index') }}"
                            class="mr-3 inline-flex items-center px-4 py-2 bg-gray-300 dark:bg-gray-600 border border-transparent rounded-md font-semibold text-gray-800 dark:text-white hover:bg-gray-400 dark:hover:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                            Abbrechen
                        </a>
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-[#8B0116] dark:bg-[#C41E3A] border border-transparent rounded-md font-semibold text-white hover:bg-[#A50019] dark:hover:bg-[#D63A4D] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#8B0116] dark:focus:ring-[#FF6B81]">
                            Challenge erstellen
                        </button>
                    </div>
                </form>
            </div>
    </x-member-page>
</x-app-layout>