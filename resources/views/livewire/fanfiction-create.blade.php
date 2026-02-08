<x-member-page class="max-w-4xl">
    <x-header title="Neue Fanfiction erstellen" separator class="mb-6">
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

            <x-file
                wire:model="photos"
                label="Bilder (optional, max. 5)"
                accept="image/jpeg,image/png,image/webp"
                hint="Erlaubte Formate: JPG, PNG, WebP. Max. 2 MB pro Bild."
                multiple
            />

            <div class="form-control w-full">
                <label class="label">
                    <span class="label-text font-medium">Status</span>
                </label>
                <div class="flex gap-6">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" wire:model="status" value="draft" class="radio radio-primary">
                        <span>Als Entwurf speichern</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" wire:model="status" value="published" class="radio radio-primary">
                        <span>Sofort veröffentlichen</span>
                    </label>
                </div>
                @error('status')
                    <p class="text-sm text-error mt-1">{{ $message }}</p>
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
                    spinner="save"
                />
            </x-slot:actions>
        </x-form>
    </x-card>
</x-member-page>
