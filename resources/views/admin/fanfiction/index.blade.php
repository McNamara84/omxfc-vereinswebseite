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
                <div class="text-center py-12 text-base-content/60">
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
                <div class="overflow-x-auto">
                    <table class="table table-zebra">
                        <thead>
                            <tr>
                                <th>Titel</th>
                                <th>Autor</th>
                                <th>Status</th>
                                <th>Erstellt</th>
                                <th class="text-right">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($fanfictions as $fanfiction)
                                <tr>
                                    <td>
                                        <span class="font-medium">{{ $fanfiction->title }}</span>
                                        @if($fanfiction->photos && count($fanfiction->photos) > 0)
                                            <span class="text-xs text-base-content/50 ml-2">
                                                <x-icon name="o-photo" class="w-3 h-3 inline" /> {{ count($fanfiction->photos) }}
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <span>{{ $fanfiction->author_name }}</span>
                                        @if($fanfiction->author)
                                            <a href="{{ route('profile.view', $fanfiction->author->id) }}"
                                               class="text-xs text-primary hover:underline block">
                                                Mitglied
                                            </a>
                                        @else
                                            <span class="text-xs text-base-content/50 block">Externer Autor</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($fanfiction->status === \App\Enums\FanfictionStatus::Published)
                                            <x-badge value="Veröffentlicht" class="badge-success" />
                                        @else
                                            <x-badge value="Entwurf" class="badge-warning" />
                                        @endif
                                    </td>
                                    <td>
                                        <span class="text-sm text-base-content/70">
                                            {{ $fanfiction->created_at->format('d.m.Y H:i') }}
                                        </span>
                                    </td>
                                    <td>
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
                                            <form action="{{ route('admin.fanfiction.destroy', $fanfiction) }}" method="POST" class="inline" onsubmit="return confirm('Fanfiction wirklich löschen?');">
                                                @csrf
                                                @method('DELETE')
                                                <x-button
                                                    type="submit"
                                                    icon="o-trash"
                                                    class="btn-ghost btn-sm text-error"
                                                    tooltip="Löschen"
                                                />
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-card>
    </x-member-page>
</x-app-layout>
