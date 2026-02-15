@php
    $reviewCount = $books->sum('reviews_count');
@endphp
<div class="collapse collapse-arrow mb-4 border border-base-content/10 rounded-lg bg-base-100">
    <input type="checkbox" {{ $initiallyOpen ? 'checked' : '' }} />
    <div class="collapse-title font-semibold bg-base-200 rounded-t-lg">
        {{ $title }} ({{ $reviewCount }} {{ $reviewCount === 1 ? 'Rezension' : 'Rezensionen' }})
    </div>
    <div class="collapse-content">
        <x-book-list :books="$books" />
    </div>
</div>
