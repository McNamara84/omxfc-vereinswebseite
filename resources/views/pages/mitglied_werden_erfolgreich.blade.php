<x-app-layout title="Antrag versendet – Offizieller MADDRAX Fanclub e. V." description="Bestätige deine E-Mail, damit wir deinen Mitgliedsantrag bearbeiten können.">
    <x-public-page class="max-w-3xl text-center">
        <h2 class="text-2xl font-bold text-success mb-4">🎉 Antrag erfolgreich eingereicht!</h2>
        <p class="text-base-content mb-4">
            Wir haben dir eine E-Mail zur Bestätigung deiner Mailadresse geschickt.
            Bitte klicke auf den Link in dieser Mail, um deinen Antrag zu bestätigen.
        </p>
        <p class="text-base-content">
            Sobald dein Antrag durch den Vorstand geprüft wurde, erhältst du vom Kassenwart alle nötigen Infos zur
            Zahlung deines Mitgliedsbeitrags.
            Erst danach kannst du dich in den internen Mitgliederbereich einloggen.
        </p>
        <a href="{{ route('home') }}"
            class="inline-block mt-6 px-4 py-2 bg-primary text-primary-content rounded hover:bg-primary/80 transition">
            Zurück zur Startseite
        </a>
    </x-public-page>
</x-app-layout>
