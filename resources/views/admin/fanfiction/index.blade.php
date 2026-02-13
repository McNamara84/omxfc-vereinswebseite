<x-app-layout>
    <x-member-page>
        <x-header title="Fanfiction verwalten" separator class="mb-6">
            <x-slot:actions>
                <x-button
                    label="Neue Fanfiction"
                    icon="o-plus"
                    link="{{ route('admin.fanfiction.create') }}"
                    class="btn-primary"
                />
            </x-slot:actions>
        </x-header>

        @if(session('success'))
            <x-alert icon="o-check-circle" class="alert-success mb-4" dismissible>
                {{ session('success') }}
            </x-alert>
        @endif

        @if(session('info'))
            <x-alert icon="o-information-circle" class="alert-info mb-4" dismissible>
                {{ session('info') }}
            </x-alert>
        @endif

        <x-card>
            @if($fanfictions->isEmpty())
                <div class="text-center py-12 text-base-content">
                    <x-icon name="o-document-text" class="w-16 h-16 mx-auto mb-4" />
                    <p class="mb-2">Noch keine Fanfiction vorhanden.</p>
                    <x-button
                        label="Jetzt die erste Geschichte erstellen"
                        icon="o-plus"
                        link="{{ route('admin.fanfiction.create') }}"
                        class="btn-primary btn-sm"
                    />
                </div>
            @else
                @php
                    $headers = [
                        ['key' => 'title', 'label' => 'Titel'],
                        ['key' => 'author_name', 'label' => 'Autor'],
                        ['key' => 'status', 'label' => 'Status'],
                        ['key' => 'created_at', 'label' => 'Erstellt'],
                        ['key' => 'actions', 'label' => 'Aktionen', 'class' => 'text-right'],
                    ];
                @endphp

                <div x-data="{ deleteUrl: '' }">
                    <x-table :headers="$headers" :rows="$fanfictions" striped>
                        @scope('cell_title', $fanfiction)
                            <span class="font-medium">{{ $fanfiction->title }}</span>
                            @if($fanfiction->photos && count($fanfiction->photos) > 0)
                                <span class="text-xs text-base-content ml-2">
                                    <x-icon name="o-photo" class="w-3 h-3 inline" /> {{ count($fanfiction->photos) }}
                                </span>
                            @endif
                        @endscope

                        @scope('cell_author_name', $fanfiction)
                            <span>{{ $fanfiction->author_name }}</span>
                            @if($fanfiction->author)
                                <a href="{{ route('profile.view', $fanfiction->author->id) }}"
                                   class="text-xs text-primary hover:underline block">
                                    Mitglied
                                </a>
                            @else
                                <span class="text-xs text-base-content block">Externer Autor</span>
                            @endif
                        @endscope

                        @scope('cell_status', $fanfiction)
                            @if($fanfiction->status === \App\Enums\FanfictionStatus::Published)
                                <x-badge value="Veröffentlicht" class="badge-success" />
                            @else
                                <x-badge value="Entwurf" class="badge-warning" />
                            @endif
                        @endscope

                        @scope('cell_created_at', $fanfiction)
                            <span class="text-sm text-base-content">
                                {{ $fanfiction->created_at->format('d.m.Y H:i') }}
                            </span>
                        @endscope

                        @scope('cell_actions', $fanfiction)
                            <div class="flex items-center justify-end gap-1">
                                @if($fanfiction->status === \App\Enums\FanfictionStatus::Draft)
                                    <form action="{{ route('admin.fanfiction.publish', $fanfiction) }}" method="POST" class="inline">
                                        @csrf
                                        <x-button
                                            type="submit"
                                            icon="o-check"
                                            class="btn-ghost btn-sm text-success"
                                            tooltip="Veröffentlichen"
                                        />
                                    </form>
                                @endif
                                <x-button
                                    icon="o-pencil-square"
                                    link="{{ route('admin.fanfiction.edit', $fanfiction) }}"
                                    class="btn-ghost btn-sm text-info"
                                    tooltip="Bearbeiten"
                                />
                                <x-button
                                    icon="o-trash"
                                    class="btn-ghost btn-sm text-error"
                                    tooltip="Löschen"
                                    @click="deleteUrl = '{{ route('admin.fanfiction.destroy', $fanfiction) }}'; document.getElementById('delete-fanfiction-modal').showModal()"
                                />
                            </div>
                        @endscope
                    </x-table>

                    {{-- Lösch-Bestätigungsdialog --}}
                    <x-mary-modal id="delete-fanfiction-modal" title="Fanfiction löschen" separator>
                        <p class="text-base-content">
                            Möchtest du diese Fanfiction wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.
                        </p>

                        <x-slot:actions>
                            <x-button label="Abbrechen" @click="document.getElementById('delete-fanfiction-modal').close()" />
                            <form :action="deleteUrl" method="POST" class="inline">
                                @csrf
                                @method('DELETE')
                                <x-button type="submit" label="Löschen" class="btn-error" icon="o-trash" />
                            </form>
                        </x-slot:actions>
                    </x-mary-modal>
                </div>
            @endif
        </x-card>
    </x-member-page>
</x-app-layout>
