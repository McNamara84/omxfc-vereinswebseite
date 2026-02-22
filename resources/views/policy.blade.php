<x-guest-layout title="Datenschutzrichtlinie – Offizieller MADDRAX Fanclub e. V." description="Informationen zur Verarbeitung personenbezogener Daten und deinen Rechten">
    <div class="max-w-2xl mx-auto px-6 py-12">
        <div class="flex justify-center mb-6">
            <a href="/">
                <x-application-logo class="w-16 h-16" />
            </a>
        </div>
        <x-card shadow>
            <x-header title="Datenschutzrichtlinie" class="mb-4" />
            <div class="prose dark:prose-invert max-w-none">
                {!! $policy !!}
            </div>
        </x-card>
    </div>
</x-guest-layout>
