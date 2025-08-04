<div class="mt-4 bg-gray-50 dark:bg-gray-700 p-4 rounded">
    <p class="text-sm text-gray-500 dark:text-gray-300">
        {{ $comment->user->name }} am {{ $comment->created_at->format('d.m.Y H:i') }}
    </p>
    <div class="mt-2 text-gray-800 dark:text-gray-200 whitespace-pre-line">
        {{ $comment->content }}
    </div>

    @if(auth()->id() === $comment->user_id)
        <div x-data="{ editing: false }" class="mt-2">
            <button type="button" @click="editing = !editing" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded">Bearbeiten</button>

            <form x-show="editing" method="POST" action="{{ route('reviews.comments.update', $comment) }}" class="mt-2">
                @csrf
                @method('PUT')
                <textarea name="content" rows="2" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 rounded mt-1" required>{{ old('content', $comment->content) }}</textarea>
                <div class="mt-2 flex flex-col sm:flex-row gap-2">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded">Speichern</button>
                    <button type="button" @click="editing = false" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded">Abbrechen</button>
                </div>
            </form>
        </div>
    @endif

    @if(auth()->id() === $comment->user_id || in_array($role ?? null, ['Vorstand','Admin'], true))
        <form method="POST" action="{{ route('reviews.comments.destroy', $comment) }}" class="mt-2">
            @csrf
            @method('DELETE')
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded">Löschen</button>
        </form>
    @endif

    @foreach($comment->children as $child)
        <div class="ml-6">
            @include('reviews.partials.comment', ['comment' => $child, 'role' => $role])
        </div>
    @endforeach

    <form method="POST" action="{{ route('reviews.comments.store', $comment->review) }}" class="mt-2">
        @csrf
        <input type="hidden" name="parent_id" value="{{ $comment->id }}">
        <textarea name="content" rows="2" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 rounded mt-1" required></textarea>
        <button type="submit" class="mt-2 bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded">Antworten</button>
    </form>
</div>
