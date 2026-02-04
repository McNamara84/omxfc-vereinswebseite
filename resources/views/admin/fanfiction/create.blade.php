<x-app-layout>
    <x-member-page class="max-w-4xl">
        <x-header title="Neue Fanfiction erstellen" separator class="mb-6">
            <x-slot:actions>
                <a href="{{ route('admin.fanfiction.index') }}" class="btn btn-ghost">
                    <x-icon name="o-arrow-left" class="w-4 h-4" />
                    Zurück
                </a>
            </x-slot:actions>
        </x-header>

        <x-card>
            <form action="{{ route('admin.fanfiction.store') }}" method="POST" enctype="multipart/form-data"
                  x-data="fanfictionForm()"
                  data-author-type="{{ old('author_type', 'member') }}"
                  data-author-name="{{ old('author_name', '') }}">
                @csrf

                <div class="form-control w-full">
                    <label class="label" for="title">
                        <span class="label-text font-medium">Titel der Geschichte <span class="text-error">*</span></span>
                    </label>
                    <input type="text" id="title" name="title" value="{{ old('title') }}"
                           class="input input-bordered w-full @error('title') input-error @enderror" required>
                    @error('title')
                        <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                    @enderror
                </div>

                <div class="form-control w-full mt-4">
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
                        <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                    @enderror
                </div>

                <div class="form-control w-full mt-4" x-show="authorType === 'member'" x-transition>
                    <label class="label" for="user_id">
                        <span class="label-text font-medium">Mitglied auswählen</span>
                    </label>
                    <select id="user_id" name="user_id"
                            class="select select-bordered w-full"
                            x-on:change="updateAuthorName($event)">
                        <option value="">-- Mitglied wählen --</option>
                        @foreach($members as $member)
                            <option value="{{ $member->id }}" data-name="{{ $member->name }}"
                                {{ old('user_id') == $member->id ? 'selected' : '' }}>
                                {{ $member->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('user_id')
                        <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                    @enderror
                </div>

                <div class="form-control w-full mt-4">
                    <label class="label" for="author_name">
                        <span class="label-text font-medium">Autor-Name <span class="text-error">*</span></span>
                    </label>
                    <input type="text" id="author_name" name="author_name"
                           x-model="authorName"
                           x-bind:readonly="authorType === 'member' && authorName !== ''"
                           class="input input-bordered w-full @error('author_name') input-error @enderror" required>
                    <label class="label">
                        <span class="label-text-alt">Wird automatisch vom gewählten Mitglied übernommen.</span>
                    </label>
                    @error('author_name')
                        <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                    @enderror
                </div>

                <div class="form-control w-full mt-4">
                    <label class="label" for="content">
                        <span class="label-text font-medium">Geschichte <span class="text-error">*</span></span>
                    </label>
                    <textarea id="content" name="content" rows="15"
                              class="textarea textarea-bordered w-full font-mono @error('content') textarea-error @enderror"
                              required>{{ old('content') }}</textarea>
                    <label class="label">
                        <span class="label-text-alt">Markdown-Formatierung wird unterstützt: **fett**, *kursiv*, > Zitat, etc.</span>
                    </label>
                    @error('content')
                        <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                    @enderror
                </div>

                <div class="form-control w-full mt-4">
                    <label class="label">
                        <span class="label-text font-medium">Bilder (optional, max. 5)</span>
                    </label>
                    <input type="file" name="photos[]" multiple accept=".jpg,.jpeg,.png,.webp"
                           class="file-input file-input-bordered w-full">
                    <label class="label">
                        <span class="label-text-alt">Erlaubte Formate: JPG, PNG, WebP. Max. 2 MB pro Bild.</span>
                    </label>
                    @error('photos')
                        <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                    @enderror
                    @error('photos.*')
                        <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                    @enderror
                </div>

                <div class="form-control w-full mt-4">
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
                        <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                    @enderror
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <a href="{{ route('admin.fanfiction.index') }}" class="btn btn-ghost">Abbrechen</a>
                    <button type="submit" class="btn btn-primary">
                        <x-icon name="o-document-plus" class="w-4 h-4" />
                        Fanfiction speichern
                    </button>
                </div>
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
