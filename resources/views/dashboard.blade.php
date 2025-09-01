<x-app-layout>
    <x-member-page>
            @if(session('status'))
                <div
                    class="mb-4 p-4 bg-green-100 dark:bg-green-800 border border-green-400 dark:border-green-600 text-green-800 dark:text-green-100 rounded">
                    {{ session('status') }}
                </div>
            @endif
            <!-- Dashboard Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <!-- Mitgliederzahl Card -->
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 flex flex-col">
                    <h2 class="text-lg font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-2">Aktuelle Mitgliederzahl</h2>
                    <div class="text-4xl font-bold text-gray-800 dark:text-gray-200 mt-auto">
                        {{ $memberCount }}
                    </div>
                </div>
                <!-- Offene Aufgaben Card -->
                <a href="{{ route('todos.index') }}" class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 flex flex-col hover:bg-gray-50 dark:hover:bg-gray-700 transition duration-200">
                    <h2 class="text-lg font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-2">Offene Challenges</h2>
                    <div class="text-4xl font-bold text-gray-800 dark:text-gray-200 mt-auto">
                        {{ $openTodos }}
                    </div>
                </a>
                <!-- Baxx Card -->
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 flex flex-col">
                    <h2 class="text-lg font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-2">Meine Baxx</h2>
                    <div class="text-4xl font-bold text-gray-800 dark:text-gray-200 mt-auto">
                        {{ $userPoints }}
                    </div>
                </div>
                <!-- Erledigte Aufgaben Card -->
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 flex flex-col">
                    <h2 class="text-lg font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-2">Abgeschlossene Challenges</h2>
                    <div class="text-4xl font-bold text-gray-800 dark:text-gray-200 mt-auto">
                        {{ $completedTodos }}
                    </div>
                </div>
            </div>
            <!-- Anwärter-Liste für Kassenwart, Vorstand und Admin -->
            @if($anwaerter->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-8">
                    <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-4">Mitgliedsanträge</h2>
                    <!-- Desktop-Ansicht (versteckt auf Mobilgeräten) -->
                    <div class="hidden md:block overflow-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-gray-700 dark:text-gray-300">Name</th>
                                    <th class="px-4 py-2 text-left text-gray-700 dark:text-gray-300">E-Mail</th>
                                    <th class="px-4 py-2 text-left text-gray-700 dark:text-gray-300">Beitrag</th>
                                    <th class="px-4 py-2 text-center text-gray-700 dark:text-gray-300">Aktion</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($anwaerter as $person)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-800 dark:text-gray-200"><a href="{{ route('profile.view', $person->id) }}" class="text-[#8B0116] hover:underline">{{ $person->name }}</a></td>
                                        <td class="px-4 py-2 text-gray-800 dark:text-gray-200">{{ $person->email }}</td>
                                        <td class="px-4 py-2 text-gray-800 dark:text-gray-200">
                                            {{ $person->mitgliedsbeitrag }}</td>
                                        <td class="px-4 py-2 flex justify-center gap-2">
                                            <form action="{{ route('anwaerter.approve', $person->id) }}" method="POST">
                                                @csrf
                                                <button type="submit"
                                                    class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded">
                                                    Genehmigen
                                                </button>
                                            </form>
                                            <form action="{{ route('anwaerter.reject', $person->id) }}" method="POST"
                                                onsubmit="return confirm('Antrag wirklich ablehnen und Nutzer löschen?');">
                                                @csrf
                                                <button type="submit"
                                                    class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded">
                                                    Ablehnen
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <!-- Mobile-Ansicht (nur auf Mobilgeräten sichtbar) -->
                    <div class="md:hidden space-y-6">
                        @foreach($anwaerter as $person)
                            <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow">
                                <div class="mb-2">
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">Name:</span>
                                    <span class="block mt-1 text-gray-800 dark:text-gray-200"><a href="{{ route('profile.view', $person->id) }}" class="text-[#8B0116] hover:underline">{{ $person->name }}</a></span>
                                </div>
                                <div class="mb-2">
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">E-Mail:</span>
                                    <span class="block mt-1 break-words text-gray-800 dark:text-gray-200">{{ $person->email }}</span>
                                </div>
                                <div class="mb-4">
                                    <span class="font-semibold text-gray-700 dark:text-gray-300">Beitrag:</span>
                                    <span class="block mt-1 text-gray-800 dark:text-gray-200">{{ $person->mitgliedsbeitrag }}</span>
                                </div>
                                <div class="flex gap-2 mt-4">
                                    <form action="{{ route('anwaerter.approve', $person->id) }}" method="POST"
                                        class="w-1/2">
                                        @csrf
                                        <button type="submit"
                                            class="w-full bg-green-500 hover:bg-green-600 text-white py-2 px-4 rounded">
                                            Genehmigen
                                        </button>
                                    </form>
                                    <form action="{{ route('anwaerter.reject', $person->id) }}" method="POST" class="w-1/2"
                                        onsubmit="return confirm('Antrag wirklich ablehnen und Nutzer löschen?');">
                                        @csrf
                                        <button type="submit"
                                            class="w-full bg-red-500 hover:bg-red-600 text-white py-2 px-4 rounded">
                                            Ablehnen
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            <!-- Weitere Dashboard Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
                <!-- Gesamtanzahl Rezensionen Card -->
                <a href="{{ route('reviews.index') }}" class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 flex flex-col hover:bg-gray-50 dark:hover:bg-gray-700 transition duration-200">
                    <h2 class="text-lg font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-2">Alle Rezensionen</h2>
                    <div class="text-4xl font-bold text-gray-800 dark:text-gray-200 mt-auto">
                        {{ $allReviews }}
                    </div>
                </a>
                <!-- Meine Rezensionen Card -->
                <a href="{{ route('reviews.index') }}" class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 flex flex-col hover:bg-gray-50 dark:hover:bg-gray-700 transition duration-200">
                    <h2 class="text-lg font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-2">Meine Rezensionen</h2>
                    <div class="text-4xl font-bold text-gray-800 dark:text-gray-200 mt-auto">
                        {{ $myReviews }}
                    </div>
                </a>
                <!-- Meine Kommentare Card -->
                <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 flex flex-col">
                    <h2 class="text-lg font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-2">Meine Kommentare</h2>
                    <div class="text-4xl font-bold text-gray-800 dark:text-gray-200 mt-auto">
                        {{ $myReviewComments }}
                    </div>
                </div>
            </div>
            <!-- Aktivitäten Card -->
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-8">
                <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-4">Aktivitäten</h2>
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($activities as $activity)
                        <li class="py-2 flex justify-between">
                            <span class="text-sm text-gray-600 dark:text-gray-400">
                                {{ $activity->created_at->format('d.m.Y H:i') }} - <a href="{{ route('profile.view', $activity->user->id) }}" class="text-[#8B0116] hover:underline">{{ $activity->user->name }}</a>
                            </span>
                            @if($activity->subject_type === \App\Models\Review::class)
                                <a href="{{ route('reviews.show', $activity->subject->book_id) }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                    Neue Rezension: {{ $activity->subject->title }}
                                </a>
                            @elseif($activity->subject_type === \App\Models\BookOffer::class)
                                <a href="{{ route('romantausch.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                    Neues Angebot: {{ $activity->subject->book_title }}
                                </a>
                            @elseif($activity->subject_type === \App\Models\BookRequest::class)
                                <a href="{{ route('romantausch.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                                    Neues Gesuch: {{ $activity->subject->book_title }}
                                </a>
                            @elseif($activity->subject_type === \App\Models\ReviewComment::class)
                                <span class="text-sm">
                                    Kommentar zu <a href="{{ route('reviews.show', $activity->subject->review->book_id) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $activity->subject->review->title }}</a> von <a href="{{ route('profile.view', $activity->user->id) }}" class="text-[#8B0116] hover:underline">{{ $activity->user->name }}</a>
                                </span>
                            @elseif($activity->subject_type === \App\Models\Todo::class && $activity->action === 'accepted')
                                <span class="text-sm">hat die Challenge <a href="{{ route('todos.show', $activity->subject->id) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $activity->subject->title }}</a> angenommen</span>
                            @elseif($activity->subject_type === \App\Models\Todo::class && $activity->action === 'completed')
                                <span class="text-sm">hat die Challenge <a href="{{ route('todos.show', $activity->subject->id) }}" class="text-blue-600 dark:text-blue-400 hover:underline">{{ $activity->subject->title }}</a> erfolgreich abgeschlossen</span>
                            @elseif($activity->subject_type === \App\Models\User::class && $activity->action === 'member_approved')
                                <span class="text-sm">Wir begrüßen unser neues Mitglied <a href="{{ route('profile.view', $activity->subject->id) }}" class="text-[#8B0116] hover:underline">{{ $activity->subject->name }}</a></span>
                            @endif
                        </li>
                    @empty
                        <li class="py-2 text-gray-600 dark:text-gray-400">Keine Aktivitäten vorhanden.</li>
                    @endforelse
                </ul>
            </div>
            <!-- TOP 3 Mitglieder -->
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-8">
                <h2 class="text-xl font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-6">TOP 3 Baxx-Sammler</h2>
                
                @if(count($topUsers) > 0)
                    <div class="flex flex-col md:flex-row items-center md:items-start justify-center gap-6 md:gap-10">
                        @foreach($topUsers as $index => $topUser)
                            <a href="{{ route('profile.view', $topUser['id']) }}" class="flex flex-col items-center group">
                                @if($index === 0)
                                    <!-- Gold Medaille für Platz 1 -->
                                    <div class="relative mb-2">
                                        <div class="absolute -top-3 -right-2 w-8 h-8 bg-yellow-400 dark:bg-yellow-500 rounded-full flex items-center justify-center text-white font-bold shadow-md transform group-hover:scale-110 transition-transform">1</div>
                                        <div class="h-20 w-20 rounded-full overflow-hidden border-4 border-yellow-400 dark:border-yellow-500 shadow-lg group-hover:shadow-xl transition-shadow">
                                            <img loading="lazy" src="{{ $topUser['profile_photo_url'] }}" alt="{{ $topUser['name'] }}" class="h-full w-full object-cover">
                                        </div>
                                    </div>
                                @elseif($index === 1)
                                    <!-- Silber Medaille für Platz 2 -->
                                    <div class="relative mb-2">
                                        <div class="absolute -top-3 -right-2 w-8 h-8 bg-gray-300 dark:bg-gray-400 rounded-full flex items-center justify-center text-gray-700 dark:text-gray-800 font-bold shadow-md transform group-hover:scale-110 transition-transform">2</div>
                                        <div class="h-20 w-20 rounded-full overflow-hidden border-4 border-gray-300 dark:border-gray-400 shadow-lg group-hover:shadow-xl transition-shadow">
                                            <img loading="lazy" src="{{ $topUser['profile_photo_url'] }}" alt="{{ $topUser['name'] }}" class="h-full w-full object-cover">
                                        </div>
                                    </div>
                                @else
                                    <!-- Bronze Medaille für Platz 3 -->
                                    <div class="relative mb-2">
                                        <div class="absolute -top-3 -right-2 w-8 h-8 bg-yellow-700 dark:bg-yellow-800 rounded-full flex items-center justify-center text-white font-bold shadow-md transform group-hover:scale-110 transition-transform">3</div>
                                        <div class="h-20 w-20 rounded-full overflow-hidden border-4 border-yellow-700 dark:border-yellow-800 shadow-lg group-hover:shadow-xl transition-shadow">
                                            <img loading="lazy" src="{{ $topUser['profile_photo_url'] }}" alt="{{ $topUser['name'] }}" class="h-full w-full object-cover">
                                        </div>
                                    </div>
                                @endif
                                
                                <h3 class="text-lg font-semibold text-gray-800 dark:text-white mt-2 group-hover:text-[#8B0116] dark:group-hover:text-[#FCA5A5] transition-colors">{{ $topUser['name'] }}</h3>
                                <p class="font-bold text-xl text-[#8B0116] dark:text-[#FCA5A5]">{{ $topUser['points'] }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Baxx</p>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="text-gray-600 dark:text-gray-400 text-center">Noch keine Baxx vergeben.</p>
                @endif
            </div>
            <!-- Zu verifizierende Aufgaben Card (nur für Admin) -->
            @if(in_array($userRole, $allowedRoles) && $pendingVerification > 0)
                <a href="{{ route('todos.index') }}?filter=pending" class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6 mb-8 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700 transition duration-200">
                    <div>
                        <h2 class="text-lg font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-1">Auf Verifizierung wartende Challenges</h2>
                        <p class="text-gray-600 dark:text-gray-400 text-sm">Es gibt {{ $pendingVerification }} Challenge(s), die auf Bestätigung warten</p>
                    </div>
                    <div class="flex items-center">
                        <div class="text-3xl font-bold text-[#8B0116] dark:text-[#FCA5A5] mr-4">{{ $pendingVerification }}</div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-500 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </div>
                </a>
            @endif
    </x-member-page>
</x-app-layout>