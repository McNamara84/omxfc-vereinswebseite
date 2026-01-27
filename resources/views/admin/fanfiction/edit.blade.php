<x-app-layout>
    <x-member-page class="max-w-4xl">
        <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-6">Fanfiction bearbeiten</h1>

        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
            <form action="{{ route('admin.fanfiction.update', $fanfiction) }}" method="POST" enctype="multipart/form-data"
                  x-data="fanfictionEditForm()"
                  data-author-type="{{ old('author_type', $fanfiction->user_id ? 'member' : 'external') }}"
                  data-author-name="{{ old('author_name', $fanfiction->author_name) }}">
                @csrf
                @method('PUT')

                <x-form name="title" label="Titel der Geschichte" class="mb-4">
                    <input id="title" name="title" aria-describedby="title-error" type="text" value="{{ old('title', $fanfiction->title) }}" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded" required />
                </x-form>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Autor-Typ</label>
                    <div class="flex gap-4">
                        <label class="inline-flex items-center">
                            <input type="radio" name="author_type" value="member" x-model="authorType" class="text-[#8B0116] focus:ring-[#8B0116]">
                            <span class="ml-2 text-gray-700 dark:text-gray-300">Vereinsmitglied</span>
                        </label>
                        <label class="inline-flex items-center">
                            <input type="radio" name="author_type" value="external" x-model="authorType" class="text-[#8B0116] focus:ring-[#8B0116]">
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
                                <option value="{{ $member->id }}" data-name="{{ $member->name }}" {{ old('user_id', $fanfiction->user_id) == $member->id ? 'selected' : '' }}>
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
                    <textarea id="content" name="content" aria-describedby="content-error" rows="15" class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 rounded font-mono" required>{{ old('content', $fanfiction->content) }}</textarea>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Markdown-Formatierung wird unterstützt: **fett**, *kursiv*, > Zitat, etc.</p>
                </x-form>

                <!-- Vorhandene Bilder -->
                @if($fanfiction->photos && count($fanfiction->photos) > 0)
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Vorhandene Bilder</label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4">
                            @foreach($fanfiction->photos as $index => $photo)
                                <div class="relative group" x-data="{ checked: false }">
                                    <img src="{{ Storage::url($photo) }}" alt="Fanfiction Bild" class="w-full h-24 object-cover rounded border border-gray-200 dark:border-gray-600" :class="{ 'opacity-50 border-red-500': checked }">
                                    <label class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition cursor-pointer rounded" :class="{ 'opacity-100': checked }">
                                        <input type="checkbox" name="remove_photos[]" value="{{ $photo }}" class="sr-only" x-model="checked">
                                        <span class="text-white text-sm">
                                            <span x-show="!checked">🗑️ Löschen</span>
                                            <span x-show="checked" class="text-red-400">✓ Wird gelöscht</span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Klicke auf ein Bild, um es zum Löschen zu markieren.</p>
                    </div>
                @endif

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Neue Bilder hinzufügen (optional)</label>
                    <input type="file" name="photos[]" multiple accept=".jpg,.jpeg,.png,.webp" class="block w-full text-sm text-gray-500 dark:text-gray-400 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-[#8B0116] file:text-white hover:file:bg-[#6B0112]" />
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Erlaubte Formate: JPG, PNG, WebP. Max. 2 MB pro Bild. Insgesamt max. 5 Bilder.</p>
                    @error('photos')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                    @error('photos.*')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded">
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        <strong>Status:</strong>
                        @if($fanfiction->status === \App\Enums\FanfictionStatus::Published)
                            <span class="text-green-600 dark:text-green-400">Veröffentlicht</span>
                            ({{ $fanfiction->published_at->format('d.m.Y H:i') }})
                        @else
                            <span class="text-yellow-600 dark:text-yellow-400">Entwurf</span>
                        @endif
                    </p>
                </div>

                <div class="flex flex-col sm:flex-row sm:justify-between gap-4">
                    <a href="{{ route('admin.fanfiction.index') }}" class="text-gray-600 dark:text-gray-400 hover:underline">Abbrechen</a>
                    <button type="submit" class="bg-[#8B0116] dark:bg-[#FCA5A5] text-white px-4 py-2 rounded hover:bg-[#6B0112] dark:hover:bg-[#FCA5A5]/80">
                        Änderungen speichern
                    </button>
                </div>
            </form>
        </div>
    </x-member-page>

    <script>
        function fanfictionEditForm() {
            const formEl = document.querySelector('[x-data="fanfictionEditForm()"]');
            return {
                authorType: formEl?.dataset.authorType || 'external',
                authorName: formEl?.dataset.authorName || '',
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
