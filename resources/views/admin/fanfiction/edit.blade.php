<x-app-layout>
    <x-member-page class="max-w-4xl">
        <x-header title="Fanfiction bearbeiten" separator>
            <x-slot:actions>
                <x-button
                    label="Zurück"
                    icon="o-arrow-left"
                    link="{{ route('admin.fanfiction.index') }}"
                    class="btn-ghost"
                />
            </x-slot:actions>
        </x-header>

        <x-card>
            <form action="{{ route('admin.fanfiction.update', $fanfiction) }}" method="POST" enctype="multipart/form-data"
                  x-data="fanfictionEditForm()"
                  data-author-type="{{ old('author_type', $fanfiction->user_id ? 'member' : 'external') }}"
                  data-author-name="{{ old('author_name', $fanfiction->author_name) }}">
                @csrf
                @method('PUT')

                <x-input
                    id="title"
                    name="title"
                    label="Titel der Geschichte"
                    value="{{ old('title', $fanfiction->title) }}"
                    required
                />

                <div class="mt-4">
                    <label class="label">
                        <span class="label-text font-medium">Autor-Typ</span>
                    </label>
                    <div class="flex gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="author_type" value="member" x-model="authorType"
                                   class="radio radio-primary">
                            <span>Vereinsmitglied</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="author_type" value="external" x-model="authorType"
                                   class="radio radio-primary">
                            <span>Externer Autor</span>
                        </label>
                    </div>
                    @error('author_type')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-4" x-show="authorType === 'member'" x-transition>
                    <x-select
                        id="user_id"
                        name="user_id"
                        label="Mitglied auswählen"
                        :options="$members->map(fn($m) => ['id' => $m->id, 'name' => $m->name])->toArray()"
                        option-value="id"
                        option-label="name"
                        placeholder="-- Mitglied wählen --"
                        :selected="old('user_id', $fanfiction->user_id)"
                        x-on:change="updateAuthorName($event)"
                    />
                </div>

                <x-input
                    id="author_name"
                    name="author_name"
                    label="Autor-Name"
                    x-model="authorName"
                    required
                    :readonly="false"
                    x-bind:readonly="authorType === 'member' && authorName !== ''"
                    hint="Wird automatisch vom gewählten Mitglied übernommen."
                    class="mt-4"
                />

                <x-textarea
                    id="content"
                    name="content"
                    label="Geschichte"
                    rows="15"
                    required
                    hint="Markdown-Formatierung wird unterstützt: **fett**, *kursiv*, > Zitat, etc."
                    class="mt-4 font-mono"
                >{{ old('content', $fanfiction->content) }}</x-textarea>

                {{-- Vorhandene Bilder --}}
                @if($fanfiction->photos && count($fanfiction->photos) > 0)
                    <div class="mt-4">
                        <label class="label">
                            <span class="label-text font-medium">Vorhandene Bilder</span>
                        </label>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4">
                            @foreach($fanfiction->photos as $index => $photo)
                                <div class="relative group" x-data="{ checked: false }">
                                    <img src="{{ Storage::url($photo) }}" alt="Fanfiction Bild"
                                         class="w-full h-24 object-cover rounded border border-base-200"
                                         :class="{ 'opacity-50 border-error': checked }">
                                    <label class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition cursor-pointer rounded"
                                           :class="{ 'opacity-100': checked }">
                                        <input type="checkbox" name="remove_photos[]" value="{{ $photo }}" class="sr-only" x-model="checked">
                                        <span class="text-white text-sm">
                                            <span x-show="!checked">🗑️ Löschen</span>
                                            <span x-show="checked" class="text-error">✓ Wird gelöscht</span>
                                        </span>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                        <p class="text-sm text-base-content/50 mt-1">Klicke auf ein Bild, um es zum Löschen zu markieren.</p>
                    </div>
                @endif

                <div class="mt-4">
                    <label class="label">
                        <span class="label-text font-medium">Neue Bilder hinzufügen (optional)</span>
                    </label>
                    <x-file
                        name="photos[]"
                        multiple
                        accept=".jpg,.jpeg,.png,.webp"
                        hint="Erlaubte Formate: JPG, PNG, WebP. Max. 2 MB pro Bild. Insgesamt max. 5 Bilder."
                    />
                    @error('photos')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                    @error('photos.*')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <x-alert icon="o-information-circle" class="mt-4">
                    <strong>Status:</strong>
                    @if($fanfiction->status === \App\Enums\FanfictionStatus::Published)
                        <x-badge value="Veröffentlicht" class="badge-success ml-2" />
                        ({{ $fanfiction->published_at->format('d.m.Y H:i') }})
                    @else
                        <x-badge value="Entwurf" class="badge-warning ml-2" />
                    @endif
                </x-alert>

                <x-slot:actions>
                    <x-button
                        label="Abbrechen"
                        link="{{ route('admin.fanfiction.index') }}"
                        class="btn-ghost"
                    />
                    <x-button
                        type="submit"
                        label="Änderungen speichern"
                        icon="o-check"
                        class="btn-primary"
                    />
                </x-slot:actions>
            </form>
        </x-card>
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
