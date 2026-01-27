<x-app-layout>
    <x-member-page>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-[#FCA5A5]">Fanfiction verwalten</h1>
            <a href="{{ route('admin.fanfiction.create') }}" class="inline-flex items-center bg-[#8B0116] dark:bg-[#FCA5A5] text-white px-4 py-2 rounded hover:bg-[#6B0112] dark:hover:bg-[#FCA5A5]/80 transition">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                Neue Fanfiction
            </a>
        </div>

        @if(session('success'))
            <div class="mb-4 p-4 bg-green-100 dark:bg-green-800 border border-green-400 dark:border-green-600 text-green-800 dark:text-green-100 rounded">
                {{ session('success') }}
            </div>
        @endif

        @if(session('info'))
            <div class="mb-4 p-4 bg-blue-100 dark:bg-blue-800 border border-blue-400 dark:border-blue-600 text-blue-800 dark:text-blue-100 rounded">
                {{ session('info') }}
            </div>
        @endif

        <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg overflow-hidden">
            @if($fanfictions->isEmpty())
                <div class="p-6 text-center text-gray-600 dark:text-gray-400">
                    <p>Noch keine Fanfiction vorhanden.</p>
                    <a href="{{ route('admin.fanfiction.create') }}" class="text-[#8B0116] dark:text-[#FCA5A5] hover:underline">Jetzt die erste Geschichte erstellen</a>
                </div>
            @else
                <!-- Desktop-Ansicht -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Titel</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Autor</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Erstellt</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($fanfictions as $fanfiction)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $fanfiction->title }}</div>
                                        @if($fanfiction->photos && count($fanfiction->photos) > 0)
                                            <span class="text-xs text-gray-500 dark:text-gray-400">{{ count($fanfiction->photos) }} Bild(er)</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900 dark:text-gray-100">{{ $fanfiction->author_name }}</div>
                                        @if($fanfiction->author)
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                <a href="{{ route('profile.view', $fanfiction->author->id) }}" class="hover:underline">Mitglied</a>
                                            </div>
                                        @else
                                            <div class="text-xs text-gray-500 dark:text-gray-400">Externer Autor</div>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        @if($fanfiction->status === \App\Enums\FanfictionStatus::Published)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                                Veröffentlicht
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100">
                                                Entwurf
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                        {{ $fanfiction->created_at->format('d.m.Y H:i') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                        @if($fanfiction->status === \App\Enums\FanfictionStatus::Draft)
                                            <form action="{{ route('admin.fanfiction.publish', $fanfiction) }}" method="POST" class="inline">
                                                @csrf
                                                <button type="submit" class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300" title="Veröffentlichen">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif
                                        <a href="{{ route('admin.fanfiction.edit', $fanfiction) }}" class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300" title="Bearbeiten">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                        </a>
                                        <form action="{{ route('admin.fanfiction.destroy', $fanfiction) }}" method="POST" class="inline" onsubmit="return confirm('Fanfiction wirklich löschen?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300" title="Löschen">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Mobile-Ansicht -->
                <div class="md:hidden divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($fanfictions as $fanfiction)
                        <div class="p-4">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">{{ $fanfiction->title }}</h3>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">von {{ $fanfiction->author_name }}</p>
                                </div>
                                @if($fanfiction->status === \App\Enums\FanfictionStatus::Published)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                        Veröffentlicht
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-800 dark:text-yellow-100">
                                        Entwurf
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">{{ $fanfiction->created_at->format('d.m.Y H:i') }}</p>
                            <div class="flex gap-2">
                                @if($fanfiction->status === \App\Enums\FanfictionStatus::Draft)
                                    <form action="{{ route('admin.fanfiction.publish', $fanfiction) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-sm">Veröffentlichen</button>
                                    </form>
                                @endif
                                <a href="{{ route('admin.fanfiction.edit', $fanfiction) }}" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-sm">Bearbeiten</a>
                                <form action="{{ route('admin.fanfiction.destroy', $fanfiction) }}" method="POST" onsubmit="return confirm('Fanfiction wirklich löschen?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">Löschen</button>
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </x-member-page>
</x-app-layout>
