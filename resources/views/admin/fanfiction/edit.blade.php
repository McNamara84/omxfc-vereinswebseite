<x-app-layout>
    <x-member-page class="max-w-4xl">
        <x-header title="Fanfiction bearbeiten" separator class="mb-6">
            <x-slot:actions>
                <a href="{{ route('admin.fanfiction.index') }}" class="btn btn-ghost">
                    <x-icon name="o-arrow-left" class="w-4 h-4" />
                    Zurück
                </a>
            </x-slot:actions>
        </x-header>

        <x-card>
            <form action="{{ route('admin.fanfiction.update', $fanfiction) }}" method="POST" enctype="multipart/form-data"
                  x-data="fanfictionEditForm()"
                  data-author-type="{{ old('author_type', $fanfiction->user_id ? 'member' : 'external') }}"
                  data-author-name="{{ old('author_name', $fanfiction->author_name) }}">
                @csrf
                @method('PUT')

                <div class="form-control w-full">
                    <label class="label" for="title">
                        <span class="label-text font-medium">Titel der Geschichte <span class="text-error">*</span></span>
                    </label>
                    <input type="text" id="title" name="title" value="{{ old('title', $fanfiction->title) }}"
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
                                {{ old('user_id', $fanfiction->user_id) == $member->id ? 'selected' : '' }}>
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
                              required>{{ old('content', $fanfiction->content) }}</textarea>
                    <label class="label">
                        <span class="label-text-alt">Markdown-Formatierung wird unterstützt: **fett**, *kursiv*, > Zitat, etc.</span>
                    </label>
                    @error('content')
                        <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                    @enderror
                </div>

                {{-- Vorhandene Bilder --}}
                @if($fanfiction->photos && count($fanfiction->photos) > 0)
                    <div class="form-control w-full mt-4">
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
                        <label class="label">
                            <span class="label-text-alt">Klicke auf ein Bild, um es zum Löschen zu markieren.</span>
                        </label>
                    </div>
                @endif

                <div class="form-control w-full mt-4">
                    <label class="label">
                        <span class="label-text font-medium">Neue Bilder hinzufügen (optional)</span>
                    </label>
                    <input type="file" name="photos[]" multiple accept=".jpg,.jpeg,.png,.webp"
                           class="file-input file-input-bordered w-full">
                    <label class="label">
                        <span class="label-text-alt">Erlaubte Formate: JPG, PNG, WebP. Max. 2 MB pro Bild. Insgesamt max. 5 Bilder.</span>
                    </label>
                    @error('photos')
                        <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                    @enderror
                    @error('photos.*')
                        <label class="label"><span class="label-text-alt text-error">{{ $message }}</span></label>
                    @enderror
                </div>

                <div class="alert mt-4">
                    <x-icon name="o-information-circle" class="w-5 h-5" />
                    <div>
                        <strong>Status:</strong>
                        @if($fanfiction->status === \App\Enums\FanfictionStatus::Published)
                            <span class="badge badge-success ml-2">Veröffentlicht</span>
                            ({{ $fanfiction->published_at->format('d.m.Y H:i') }})
                        @else
                            <span class="badge badge-warning ml-2">Entwurf</span>
                        @endif
                    </div>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <a href="{{ route('admin.fanfiction.index') }}" class="btn btn-ghost">Abbrechen</a>
                    <button type="submit" class="btn btn-primary">
                        <x-icon name="o-check" class="w-4 h-4" />
                        Änderungen speichern
                    </button>
                </div>
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
