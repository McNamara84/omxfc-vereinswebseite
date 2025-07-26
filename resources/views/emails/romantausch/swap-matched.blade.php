<x-mail::message>
Wudan zum Gruße, {{ $swap->request->user->name }}!

Für dein Tauschgesuch zu {{ $swap->request->book_number }} – {{ $swap->request->book_title }} liegt ein passendes Angebot vor. 
Im Mitgliederbereich findest du jetzt ein Match, 
damit ihr euren Tausch besprechen könnt. Klick auf den Button, um direkt dorthin zu gelangen.

<x-mail::button :url="route('romantausch.index', absolute: false)">
Match anzeigen
</x-mail::button>

Tuma sa feesa,<br>
Der Archivar des Offiziellen MADDRAX Fanclub e. V.
</x-mail::message>