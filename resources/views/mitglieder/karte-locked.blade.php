<x-app-layout>
    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-xl sm:rounded-lg p-6">
                <h2 class="text-2xl font-semibold text-apocalypse-accent mb-6">Mitgliederkarte</h2>
                
                <div class="bg-yellow-50 dark:bg-yellow-900 border border-yellow-200 dark:border-yellow-800 rounded-lg p-6 text-center">
                    <div class="flex flex-col items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-yellow-500 dark:text-yellow-600 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m0 0v2m0-2h2m-2 0H9m3-4V9m0 0V7m0 2h2M9 9h2M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        <h3 class="text-xl font-bold text-yellow-800 dark:text-yellow-200 mb-2">Karte noch nicht verfügbar</h3>
                        <p class="text-yellow-700 dark:text-yellow-300 mb-6">
                            Die Mitgliederkarte wird freigeschaltet, sobald du mindestens eine Challenge erfolgreich abgeschlossen hast.
                        </p>
                        
                        <a href="{{ route('todos.index') }}" class="inline-flex items-center px-4 py-2 bg-[#8B0116] dark:bg-[#C41E3A] border border-transparent rounded-md font-semibold text-white hover:bg-[#A50019] dark:hover:bg-[#D63A4D] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#8B0116] dark:focus:ring-[#FF6B81]">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            Zu den verfügbaren Challenges
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>