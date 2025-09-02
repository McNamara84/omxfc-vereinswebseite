<x-app-layout>
    <x-member-page>
        <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-6">Kurznachrichten</h1>
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6 mb-8">
            <form method="POST" action="{{ route('admin.messages.store') }}">
                @csrf
                <label for="message" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Nachricht</label>
                <input type="text" id="message" name="message" maxlength="140" class="w-full mb-4 border-gray-300 dark:bg-gray-700 dark:border-gray-600 rounded-md" required>
                <x-primary-button>Speichern</x-primary-button>
            </form>
        </div>
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
            <h2 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">Bestehende Nachrichten</h2>
            <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($messages as $message)
                    <li class="py-2 flex justify-between">
                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ $message->created_at->format('d.m.Y H:i') }} - {{ $message->user->name }}</span>
                        <span class="text-sm flex items-center">
                            {{ $message->message }}
                            <form method="POST" action="{{ route('admin.messages.destroy', $message) }}" class="ml-2">
                                @csrf
                                @method('DELETE')
                                <button class="text-red-500 text-xs" onclick="return confirm('Nachricht löschen?')">Löschen</button>
                            </form>
                        </span>
                    </li>
                @empty
                    <li class="py-2 text-gray-600 dark:text-gray-400">Keine Nachrichten vorhanden.</li>
                @endforelse
            </ul>
        </div>
    </x-member-page>
</x-app-layout>
