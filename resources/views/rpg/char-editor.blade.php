<x-app-layout>
    <x-member-page class="max-w-3xl">
        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
            <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-red-400 mb-6">Charakter-Editor</h1>

            <form action="#" method="POST" enctype="multipart/form-data">
                @csrf

                <div class="mb-4">
                    <label for="player_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Spielername</label>
                    <input type="text" name="player_name" id="player_name" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                </div>

                <div class="mb-4">
                    <label for="character_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Charaktername</label>
                    <input type="text" name="character_name" id="character_name" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                </div>

                <div class="mb-4">
                    <label for="race" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Rasse</label>
                    <select name="race" id="race" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                        <option value="Barbar">Barbar</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="culture" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Kultur</label>
                    <select name="culture" id="culture" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                        <option value="Landbewohner">Landbewohner</option>
                    </select>
                </div>

                <div class="mb-6">
                    <label for="portrait" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Porträt/Symbol</label>
                    <input type="file" name="portrait" id="portrait" accept="image/*" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50">
                </div>

                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-red-400 mb-2">Beschreibung</h2>
                    <!-- Eingabefelder folgen -->
                </div>

                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-red-400 mb-2">Attribute</h2>
                    <!-- Eingabefelder folgen -->
                </div>

                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-red-400 mb-2">Fertigkeiten</h2>
                    <!-- Eingabefelder folgen -->
                </div>

                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-red-400 mb-2">Besonderheiten</h2>
                    <!-- Eingabefelder folgen -->
                </div>

                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-red-400 mb-2">Ausrüstung</h2>
                    <!-- Eingabefelder folgen -->
                </div>

                <div class="flex justify-end">
                    <button type="submit" disabled class="inline-flex items-center px-4 py-2 bg-gray-400 dark:bg-gray-600 border border-transparent rounded-md font-semibold text-white cursor-not-allowed">
                        Speichern
                    </button>
                </div>
            </form>
        </div>
    </x-member-page>
</x-app-layout>

