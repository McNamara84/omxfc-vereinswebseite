@php
    $reviewCount = $books->sum('reviews_count');
    $buttonId = 'accordion-button-' . $id;
    $contentId = 'content-' . $id;
@endphp
<div class="mb-4 border border-base-content/10 rounded-lg">
    <h2>
        <button
            id="{{ $buttonId }}"
            data-accordion-button="{{ $id }}"
            type="button"
            class="w-full flex justify-between items-center bg-base-200 px-4 py-3 rounded-t-lg font-semibold"
            onclick="toggleAccordion('{{ $id }}')"
            aria-expanded="{{ $initiallyOpen ? 'true' : 'false' }}"
            aria-controls="{{ $contentId }}"
        >
            {{ $title }} ({{ $reviewCount }} {{ $reviewCount === 1 ? 'Rezension' : 'Rezensionen' }})
            <span id="icon-{{ $id }}">{{ $initiallyOpen ? '-' : '+' }}</span>
        </button>
    </h2>
    <div
        id="{{ $contentId }}"
        class="{{ $initiallyOpen ? '' : 'hidden' }} bg-base-100 px-4 py-2 rounded-b-lg"
        role="region"
        aria-labelledby="{{ $buttonId }}"
    >
        <x-book-list :books="$books" />
    </div>
</div>
