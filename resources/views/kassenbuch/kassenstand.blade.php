<x-app-layout>
    <x-member-page>
        {{-- Header --}}
        <x-header title="Kassenstand" separator data-testid="page-header" />
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @include('kassenbuch.partials.mitgliedsbeitrag-kassenstand')
        </div>
    </x-member-page>
</x-app-layout>
