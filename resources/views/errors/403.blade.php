<x-app-layout>
    <div class="container mx-auto py-12 text-center">
        <h1 class="text-5xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-6">403</h1>
        <p class="text-xl text-gray-700 dark:text-gray-300 mb-8">
            {{ __('Der Zugriff auf diese Seite ist Ihnen nicht gestattet. Solltest du versuchen, trotzdem an diese Inhalte zu kommen, wird dich ein Rudel Taratze holen und Orguudoo bringen!') }}
        </p>
        <a href="{{ url('/') }}" class="text-[#8B0116] dark:text-[#ff4b63] underline">
            {{ __('Zur Startseite') }}
        </a>
    </div>
</x-app-layout>