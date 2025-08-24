{{-- resources/views/statistik/lock-message.blade.php --}}
<div class="absolute inset-0 flex items-center justify-center bg-white/70 dark:bg-gray-800/70 backdrop-blur-sm text-sm text-gray-600 dark:text-gray-400 text-center">
    Diese Statistik wird ab <strong>{{ $min }} Baxx</strong> freigeschaltet.<br>
    Dein aktueller Stand: <span class="font-semibold">{{ $userPoints }}</span>.
</div>
