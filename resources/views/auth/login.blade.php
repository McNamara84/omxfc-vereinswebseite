<x-app-layout>
    <div class="max-w-md mx-auto px-6 py-12 bg-maddrax-dark border border-maddrax-red rounded-lg shadow-md">
        <h2 class="text-2xl font-bold text-[#8B0116] dark:text-[#ff4b63] mb-6 text-center">
            {{ __('Login') }}
        </h2>
        <x-validation-errors class="mb-4" />
        @session('status')
            <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
                {{ $value }}
            </div>
        @endsession
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div>
                <x-label for="email" value="{{ __('E-Mail') }}" />
                <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required
                    autofocus autocomplete="username" />
            </div>
            <div class="mt-4">
                <x-label for="password" value="{{ __('Passwort') }}" />
                <x-input id="password" class="block mt-1 w-full" type="password" name="password" required
                    autocomplete="current-password" />
            </div>
            <div class="block mt-4">
                <label for="remember_me" class="flex items-center">
                    <x-checkbox id="remember_me" name="remember" />
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Merken') }}</span>
                </label>
            </div>
            <div class="flex items-center justify-between mt-6">
                @if (Route::has('password.request'))
                    <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-maddrax-sand dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#8B0116] dark:focus:ring-offset-gray-800"
                        href="{{ route('password.request') }}">
                        {{ __('Passwort vergessen?') }}
                    </a>
                @endif
                <x-button class="bg-[#8B0116] dark:bg-[#9f0119] hover:bg-[#7a0113] dark:hover:bg-[#8a0115]">
                    {{ __('Login') }}
                </x-button>
            </div>
        </form>
    </div>
</x-app-layout>