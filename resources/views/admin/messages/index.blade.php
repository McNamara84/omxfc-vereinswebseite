<x-app-layout>
    <x-member-page>
        <x-header title="Kurznachrichten" separator />

        {{-- Neue Nachricht erstellen --}}
        <x-card class="mb-6">
            <form method="POST" action="{{ route('admin.messages.store') }}">
                @csrf
                <x-input
                    id="message"
                    name="message"
                    label="Nachricht"
                    placeholder="Kurznachricht eingeben (max. 140 Zeichen)"
                    maxlength="140"
                    required
                />
                <x-slot:actions>
                    <x-button type="submit" icon="o-paper-airplane" class="btn-primary">
                        Speichern
                    </x-button>
                </x-slot:actions>
            </form>
        </x-card>

        {{-- Bestehende Nachrichten --}}
        <x-card title="Bestehende Nachrichten">
            @forelse($messages as $message)
                <div class="flex items-center justify-between py-3 {{ !$loop->last ? 'border-b border-base-200' : '' }}">
                    <div class="flex-1">
                    <div class="text-sm text-base-content mb-1">
                            {{ $message->created_at->format('d.m.Y H:i') }} &ndash; {{ $message->user->name }}
                        </div>
                        <div class="text-base-content">
                            {{ $message->message }}
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.messages.destroy', $message) }}" class="ml-4">
                        @csrf
                        @method('DELETE')
                        <x-button
                            type="submit"
                            icon="o-trash"
                            class="btn-ghost btn-sm text-error"
                            onclick="return confirm('Nachricht löschen?')"
                            tooltip="Löschen"
                        />
                    </form>
                </div>
            @empty
                <div class="text-center py-8 text-base-content">
                    <x-icon name="o-chat-bubble-left-right" class="w-12 h-12 mx-auto mb-2" />
                    <p>Keine Nachrichten vorhanden.</p>
                </div>
            @endforelse
        </x-card>
    </x-member-page>
</x-app-layout>
