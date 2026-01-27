<x-app-layout>
    <x-member-page class="max-w-4xl">
        <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-6">Neue Fanfiction erstellen</h1>

        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
            <form action="{{ route('admin.fanfiction.store') }}" method="POST" enctype="multipart/form-data" x-data="fanfictionForm()">
                @csrf

                <x-form name="title" label="Titel der Geschichte" class="mb-4">
                    <input id="title" name="title" aria-describedby="title-error" type="text" value="{{ old('title') }}" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded" required />
                </x-form>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Autor-Typ</label>
                    <div class="flex gap-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="author_type" value="member" x-model="authorType" class="text-[#8B0116] focus:ring-[#8B0116]" {{ old('author_type', 'member') === 'member' ? 'checked' : '' }}>
                            <span class="ml-2 text-gray-700 dark:text-gray-300">Vereinsmitglied</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="author_type" value="external" x-model="authorType" class="text-[#8B0116] focus:ring-[#8B0116]" {{ old('author_type') === 'external' ? 'checked' : '' }}>
                            <span class="ml-2 text-gray-700 dark:text-gray-300">Externer Autor</span>
                        </label>
                    </div>
                    @error('author_type')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-4" x-show="authorType === 'member'" x-transition>
                    <x-form name="user_id" label="Mitglied auswählen">
                        <select id="user_id" name="user_id" aria-describedby="user_id-error" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded" x-on:change="updateAuthorName($event)">
                            <option value="">-- Mitglied wählen --</option>
                            @foreach($members as $member)
                                <option value="{{ $member->id }}" data-name="{{ $member->name }}" {{ old('user_id') == $member->id ? 'selected' : '' }}>
                                    {{ $member->name }}
                                </option>
                            @endforeach
                        </select>
                    </x-form>
                </div>

                <x-form name="author_name" label="Autor-Name" class="mb-4">
                    <input id="author_name" name="author_name" aria-describedby="author_name-error" type="text" x-model="authorName" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded" required :readonly="authorType === 'member' && authorName !== ''" />
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1" x-show="authorType === 'member'">Wird automatisch vom gewählten Mitglied übernommen.</p>
                </x-form>

                <x-form name="content" label="Geschichte" class="mb-4">
                    <textarea id="content" name="content" aria-describedby="content-error" rows="15" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded font-mono" required>{{ old('content') }}</textarea>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Markdown-Formatierung wird unterstützt: **fett**, *kursiv*, > Zitat, etc.</p>
                </x-form>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Bilder (optional, max. 5)</label>
                    <input type="file" name="photos[]" multiple accept=".jpg,.jpeg,.png,.webp" class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-[#8B0116] file:text-white hover:file:bg-[#6B0112]" />
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Erlaubte Formate: JPG, PNG, WebP. Max. 2 MB pro Bild.</p>
                    @error('photos')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    @error('photos.*')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Status</label>
                    <div class="flex gap-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="status" value="draft" class="text-[#8B0116] focus:ring-[#8B0116]" {{ old('status', 'draft') === 'draft' ? 'checked' : '' }}>
                            <span class="ml-2 text-gray-700 dark:text-gray-300">Als Entwurf speichern</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="status" value="published" class="text-[#8B0116] focus:ring-[#8B0116]" {{ old('status') === 'published' ? 'checked' : '' }}>
                            <span class="ml-2 text-gray-700 dark:text-gray-300">Sofort veröffentlichen</span>
                        </label>
                    </div>
                    @error('status')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col sm:flex-row sm:justify-between gap-4">
                    <a href="{{ route('admin.fanfiction.index') }}" class="text-gray-600 dark:text-gray-400 hover:underline">Abbrechen</a>
                    <button type="submit" class="bg-[#8B0116] dark:bg-[#FCA5A5] text-white px-4 py-2 rounded hover:bg-[#6B0112] dark:hover:bg-[#FCA5A5]/80">
                        Fanfiction speichern
                    </button>
                </div>
            </form>
        </div>
    </x-member-page>

    <script>
        function fanfictionForm() {
            return {
                authorType: '{{ old('author_type', 'member') }}',
                authorName: '{{ old('author_name', '') }}',
                updateAuthorName(event) {
                    const selectedOption = event.target.selectedOptions[0];
                    if (selectedOption && selectedOption.dataset.name) {
                        this.authorName = selectedOption.dataset.name;
                    } else {
                        this.authorName = '';
                    }
                }
            }
        }
    </script>
</x-app-layout>
