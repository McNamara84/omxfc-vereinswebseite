<x-mail::message>
Wudan zum Gruße, {{ $review->user->name }}!

Deine Rezension zu Band {{ $review->book->roman_number }} {{ $review->book->title }} wurde durch {{ $comment->user->name }} kommentiert. Hier ({{ $reviewUrl }}) kannst du den Kommentar lesen und ihn beantworten.
<x-mail::button :url="$reviewUrl">
    Kommentar lesen
</x-mail::button>
Tuma sa feesa,
Der Archivar des Offiziellen MADDRAX Fanclub e. V.
</x-mail::message>