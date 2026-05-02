<x-guest-layout title="Datenschutzrichtlinie – Offizieller MADDRAX Fanclub e. V." description="Informationen zur Verarbeitung personenbezogener Daten und deinen Rechten">
    <div class="max-w-2xl mx-auto px-6 py-12">
        <div class="flex justify-center mb-6">
            <a href="/">
                <x-application-logo class="w-16 h-16" />
            </a>
        </div>
        <x-ui.page-header title="Datenschutzrichtlinie" description="Informationen zur Verarbeitung personenbezogener Daten und zu deinen Rechten im Offiziellen MADDRAX Fanclub." class="mb-6" />

        <x-ui.panel>
            <div class="prose dark:prose-invert max-w-none">
                {!! $policy !!}
            </div>
        </x-ui.panel>
    </div>
</x-guest-layout>
