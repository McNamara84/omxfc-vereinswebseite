<x-app-layout>
    <x-member-page class="max-w-4xl">
        <x-header title="Neue Fanfiction erstellen" separator>
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
            <form action="{{ route('admin.fanfiction.store') }}" method="POST" enctype="multipart/form-data"
                  x-data="fanfictionForm()"
                  data-author-type="{{ old('author_type', 'member') }}"
                  data-author-name="{{ old('author_name', '') }}">
                @csrf

                <x-input
                    id="title"
                    name="title"
                    label="Titel der Geschichte"
                    value="{{ old('title') }}"
                    required
                />

                <div class="mt-4">
                    <label class="label">
                        <span class="label-text font-medium">Autor-Typ</span>
                    </label>
                    <div class="flex gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="author_type" value="member" x-model="authorType"
                                   class="radio radio-primary" {{ old('author_type', 'member') === 'member' ? 'checked' : '' }}>
                            <span>Vereinsmitglied</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="author_type" value="external" x-model="authorType"
                                   class="radio radio-primary" {{ old('author_type') === 'external' ? 'checked' : '' }}>
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
                >{{ old('content') }}</x-textarea>

                <div class="mt-4">
                    <label class="label">
                        <span class="label-text font-medium">Bilder (optional, max. 5)</span>
                    </label>
                    <x-file
                        name="photos[]"
                        multiple
                        accept=".jpg,.jpeg,.png,.webp"
                        hint="Erlaubte Formate: JPG, PNG, WebP. Max. 2 MB pro Bild."
                    />
                    @error('photos')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                    @error('photos.*')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-4">
                    <label class="label">
                        <span class="label-text font-medium">Status</span>
                    </label>
                    <div class="flex gap-6">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="status" value="draft" class="radio radio-primary" {{ old('status', 'draft') === 'draft' ? 'checked' : '' }}>
                            <span>Als Entwurf speichern</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="status" value="published" class="radio radio-primary" {{ old('status') === 'published' ? 'checked' : '' }}>
                            <span>Sofort veröffentlichen</span>
                        </label>
                    </div>
                    @error('status')
                        <p class="text-error text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <x-slot:actions>
                    <x-button
                        label="Abbrechen"
                        link="{{ route('admin.fanfiction.index') }}"
                        class="btn-ghost"
                    />
                    <x-button
                        type="submit"
                        label="Fanfiction speichern"
                        icon="o-document-plus"
                        class="btn-primary"
                    />
                </x-slot:actions>
            </form>
        </x-card>
    </x-member-page>

    <script>
        function fanfictionForm() {
            const formEl = document.querySelector('[x-data="fanfictionForm()"]');
            return {
                authorType: formEl?.dataset.authorType || 'member',
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
