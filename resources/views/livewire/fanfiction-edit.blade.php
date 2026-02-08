<x-member-page class="max-w-4xl">
    <x-header title="Fanfiction bearbeiten" separator class="mb-6">
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
        <x-form wire:submit="save">
            <x-input
                wire:model="title"
                label="Titel der Geschichte"
                placeholder="z.B. Die Rückkehr nach Dorado"
                required
            />

            <div class="form-control w-full">
                <label class="label">
                    <span class="label-text font-medium">Autor-Typ</span>
                </label>
                <div class="flex gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" wire:model.live="authorType" value="member" class="radio radio-primary">
                        <span>Vereinsmitglied</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" wire:model.live="authorType" value="external" class="radio radio-primary">
                        <span>Externer Autor</span>
                    </label>
                </div>
                @error('authorType')
                    <p class="text-sm text-error mt-1">{{ $message }}</p>
                @enderror
            </div>

            @if($authorType === 'member')
                <x-select
                    wire:model.live="userId"
                    label="Mitglied auswählen"
                    :options="$this->memberOptions"
                    option-value="id"
                    option-label="name"
                    placeholder="-- Mitglied wählen --"
                />
            @endif

            <x-input
                wire:model="authorName"
                label="Autor-Name"
                hint="Wird bei Vereinsmitgliedern automatisch übernommen."
                :readonly="$authorType === 'member' && $authorName !== ''"
                required
            />

            <x-textarea
                wire:model="content"
                label="Geschichte"
                rows="15"
                hint="Markdown-Formatierung wird unterstützt: **fett**, *kursiv*, > Zitat, etc."
                class="font-mono"
                required
            />

            {{-- Vorhandene Bilder --}}
            @if(count($existingPhotos) > 0)
                <div class="form-control w-full">
                    <label class="label">
                        <span class="label-text font-medium">Vorhandene Bilder</span>
                    </label>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4">
                        @foreach($existingPhotos as $photo)
                            <div class="relative group cursor-pointer" wire:click="togglePhotoRemoval('{{ $photo }}')">
                                <img src="{{ Storage::url($photo) }}" alt="Fanfiction Bild"
                                     class="w-full h-24 object-cover rounded border transition-all
                                            {{ in_array($photo, $photosToRemove) ? 'opacity-50 border-error border-2' : 'border-base-200' }}">
                                <div class="absolute inset-0 bg-black/50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition rounded
                                            {{ in_array($photo, $photosToRemove) ? 'opacity-100' : '' }}">
                                    <span class="text-white text-sm">
                                        @if(in_array($photo, $photosToRemove))
                                            <x-icon name="o-check" class="w-5 h-5 text-error" /> Wird gelöscht
                                        @else
                                            <x-icon name="o-trash" class="w-5 h-5" /> Löschen
                                        @endif
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <label class="label">
                        <span class="label-text-alt">Klicke auf ein Bild, um es zum Löschen zu markieren.</span>
                    </label>
                </div>
            @endif

            <x-file
                wire:model="newPhotos"
                label="Neue Bilder hinzufügen (optional)"
                accept="image/jpeg,image/png,image/webp"
                hint="Erlaubte Formate: JPG, PNG, WebP. Max. 2 MB pro Bild. Insgesamt max. 5 Bilder."
                multiple
            />

            <x-alert icon="o-information-circle">
                <strong>Status:</strong>
                @if($this->fanfiction->status === \App\Enums\FanfictionStatus::Published)
                    <x-badge value="Veröffentlicht" class="badge-success ml-2" />
                    ({{ $this->fanfiction->published_at->format('d.m.Y H:i') }})
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
                    spinner="save"
                />
            </x-slot:actions>
        </x-form>
    </x-card>
</x-member-page>
