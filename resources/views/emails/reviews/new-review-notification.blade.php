<x-mail::message>
Wudan zum Gruße, {{ $user->name }}!

Zu deinem Roman {{ $review->book->roman_number }} {{ $review->book->title }} wurde eine neue Rezension von {{ $review->user->name }} verfasst. Hier ({{ $reviewUrl }}) kannst du sie lesen.

<x-mail::button :url="$reviewUrl">
    Rezension lesen
</x-mail::button>
Tuma sa feesa,
Der Archivar des Offiziellen MADDRAX Fanclub e. V.
</x-mail::message>