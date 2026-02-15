{{--
    Component: book-list

    Renders a responsive table of books (uses overflow-x-auto for small screens).

    Props:
    - $books: iterable collection of book models or arrays. Each book is expected to provide:
        - roman_number: string or integer displayed as the book number
        - title: book title
        - author: book author
        - has_review: boolean indicating if at least one review exists
        - reviews_count: integer number of reviews
--}}
<div class="overflow-x-auto">
    <table class="table table-zebra">
        <thead>
            <tr>
                <th>Nr.</th>
                <th>Titel</th>
                <th>Autor</th>
                <th>Status</th>
                <th>Rezensionen</th>
            </tr>
        </thead>
        <tbody>
            @foreach($books as $book)
                <tr>
                    <td>
                        <a href="{{ route('reviews.show', $book) }}" class="text-primary hover:underline">
                            {{ $book->roman_number }}
                        </a>
                    </td>
                    <td class="text-base-content">{{ $book->title }}</td>
                    <td class="text-base-content">{{ $book->author }}</td>
                    <td>
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
                    </td>
                    <td>
                        <a href="{{ route('reviews.show', $book) }}" class="text-primary hover:underline">
                            {{ $book->reviews_count }} {{ $book->reviews_count === 1 ? 'Rezension' : 'Rezensionen' }}
                        </a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
