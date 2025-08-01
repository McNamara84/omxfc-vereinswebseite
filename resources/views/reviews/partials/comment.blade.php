<div class="mt-4 bg-gray-50 dark:bg-gray-700 p-4 rounded">
    <p class="text-sm text-maddrax-sand dark:text-gray-300">
        {{ $comment->user->name }} am {{ $comment->created_at->format('d.m.Y H:i') }}
    </p>
    <div class="mt-2 text-gray-800 dark:text-gray-200 whitespace-pre-line">
        {{ $comment->content }}
    </div>

    @foreach($comment->children as $child)
        <div class="ml-6">
            @include('reviews.partials.comment', ['comment' => $child])
        </div>
    @endforeach

    <form method="POST" action="{{ route('reviews.comments.store', $comment->review) }}" class="mt-2">
        @csrf
        <input type="hidden" name="parent_id" value="{{ $comment->id }}">
        <textarea name="content" rows="2" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 rounded mt-1" required></textarea>
        <button type="submit" class="mt-2 bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded">Antworten</button>
    </form>
</div>