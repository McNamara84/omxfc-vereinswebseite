{{--
    Component: book-list

    Renders a list of books for desktop (table) and mobile (cards) views.

    Props:
    - $books: iterable collection of book models or arrays. Each book is expected to provide:
        - roman_number: string or integer displayed as the book number
        - title: book title
        - author: book author
        - has_review: boolean indicating if at least one review exists
        - reviews_count: integer number of reviews
--}}
@php
    $headers = [
        ['key' => 'roman_number', 'label' => 'Nr.'],
        ['key' => 'title', 'label' => 'Titel'],
        ['key' => 'author', 'label' => 'Autor'],
        ['key' => 'status', 'label' => 'Status'],
        ['key' => 'reviews_count', 'label' => 'Rezensionen'],
    ];
@endphp

<x-table :headers="$headers" :rows="$books" striped>
    @scope('cell_roman_number', $book)
        <a href="{{ route('reviews.show', $book) }}" class="text-primary hover:underline">
            {{ $book->roman_number }}
        </a>
    @endscope

    @scope('cell_title', $book)
        <span class="text-base-content">{{ $book->title }}</span>
    @endscope

    @scope('cell_author', $book)
        <span class="text-base-content">{{ $book->author }}</span>
    @endscope

    @scope('cell_status', $book)
        @if($book->has_review)
            <x-badge value="" class="badge-success">
                <x-slot:prepend>
                    <x-icon name="o-check" class="w-4 h-4" />
                </x-slot:prepend>
            </x-badge>
        @else
            <a href="{{ route('reviews.create', $book) }}" title="Rezension schreiben">
                <x-badge value="" class="badge-info cursor-pointer hover:opacity-80">
                    <x-slot:prepend>
                        <x-icon name="o-pencil" class="w-4 h-4" />
                    </x-slot:prepend>
                </x-badge>
            </a>
        @endif
    @endscope

    @scope('cell_reviews_count', $book)
        <a href="{{ route('reviews.show', $book) }}" class="text-primary hover:underline">
            {{ $book->reviews_count }} {{ $book->reviews_count === 1 ? 'Rezension' : 'Rezensionen' }}
        </a>
    @endscope
</x-table>
