<x-app-layout>
    <div class="max-w-3xl mx-auto px-6 py-10 text-center bg-gray-100 dark:bg-gray-800 rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-green-700 dark:text-green-400 mb-4">🎉 Antrag erfolgreich eingereicht!</h2>
        <p class="text-gray-700 dark:text-gray-300 mb-4">
            Wir haben dir eine E-Mail zur Bestätigung deiner Mailadresse geschickt.
            Bitte klicke auf den Link in dieser Mail, um deinen Antrag zu bestätigen.
        </p>
        <p class="text-gray-700 dark:text-gray-300">
            Sobald dein Antrag durch den Vorstand geprüft wurde, erhältst du vom Kassenwart alle nötigen Infos zur
            Zahlung deines Mitgliedsbeitrags.
            Erst danach kannst du dich in den internen Mitgliederbereich einloggen.
        </p>
        <a href="{{ route('home') }}"
            class="inline-block mt-6 px-4 py-2 bg-[#8B0116] text-white rounded hover:bg-[#7a0113] transition">
            Zurück zur Startseite
        </a>
    </div>
</x-app-layout>