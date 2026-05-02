<x-app-layout>
    <x-member-page>
        {{-- Header --}}
        <x-ui.page-header
            eyebrow="Mitgliederbereich"
            title="Kassenstand"
            description="Sieh deinen Mitgliedsbeitrag und den aktuellen Vereinskassenstand in einer kompakten Übersicht."
            data-testid="page-header"
        />
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @include('kassenbuch.partials.mitgliedsbeitrag-kassenstand')
        </div>
    </x-member-page>
</x-app-layout>
