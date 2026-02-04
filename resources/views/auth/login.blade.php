<x-app-layout title="Login – Offizieller MADDRAX Fanclub e. V." description="Melde dich mit deinem Konto beim Offiziellen MADDRAX Fanclub an.">
    <div class="max-w-md mx-auto px-6 py-12 bg-gray-100 dark:bg-gray-800 rounded-lg shadow-md">
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
            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('E-Mail') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
            </div>
            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('Passwort') }}</label>
                <input id="password" name="password" type="password" required autocomplete="current-password" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" />
            </div>
            <div class="block mt-4">
                <label for="remember_me" class="flex items-center">
                    <input type="checkbox" id="remember_me" name="remember" class="checkbox" />
                    <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Merken') }}</span>
                </label>
            </div>
            <div class="flex items-center justify-between mt-6">
                @if (Route::has('password.request'))
                    <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#8B0116] dark:focus:ring-offset-gray-800"
                        href="{{ route('password.request') }}">
                        {{ __('Passwort vergessen?') }}
                    </a>
                @endif
                <button type="submit" class="btn bg-[#8B0116] dark:bg-[#9f0119] hover:bg-[#7a0113] dark:hover:bg-[#8a0115] text-white">
                    {{ __('Login') }}
                </button>
            </div>
        </form>
    </div>
</x-app-layout>