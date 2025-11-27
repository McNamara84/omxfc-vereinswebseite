@include('reviews.partials.series-accordion', [
    'id' => $id,
    'title' => $title,
    'books' => $books,
    'initiallyOpen' => $initiallyOpen ?? false,
])
