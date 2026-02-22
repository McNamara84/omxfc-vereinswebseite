<x-guest-layout title="Nutzungsbedingungen – Offizieller MADDRAX Fanclub e. V." description="Rechtliche Hinweise zur Nutzung unserer Webseiten und Angebote">
    <div class="max-w-2xl mx-auto px-6 py-12">
        <div class="flex justify-center mb-6">
            <a href="/">
                <x-application-logo class="w-16 h-16" />
            </a>
        </div>
        <x-card shadow>
            <x-header title="Nutzungsbedingungen" class="mb-4" />
            <div class="prose dark:prose-invert max-w-none">
                {!! $terms !!}
            </div>
        </x-card>
    </div>
</x-guest-layout>
