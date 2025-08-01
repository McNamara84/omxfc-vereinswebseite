<x-app-layout>
    <div class="max-w-md mx-auto px-6 py-12 bg-maddrax-dark border border-maddrax-red rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-6 text-center">
            {{ __('Passwort vergessen') }}
        </h2>

        <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Du hast dein Passwort vergessen? Das ist kein Problem. Teile uns einfach deine E-Mail-Adresse mit und wir senden dir einen Link zum Zurücksetzen des Passworts zu, mit dem du ein neues wählen können.') }}
        </div>

        @session('status')
            <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
                {{ $value }}
            </div>
        @endsession

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('password.email') }}">
            @csrf

            <div>
                <x-label for="email" value="{{ __('E-Mail') }}" />
                <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            </div>

            <div class="flex items-center justify-end mt-6">
                <x-button class="bg-[#8B0116] dark:bg-[#9f0119] hover:bg-[#7a0113] dark:hover:bg-[#8a0115]">
                    {{ __('Link anfordern') }}
                </x-button>
            </div>
        </form>
    </div>
</x-app-layout>