@php
    $depth = $depth ?? 0;
@endphp
<div class="mt-4 bg-gray-50 dark:bg-gray-700 p-4 rounded">
    <p class="text-sm text-gray-500 dark:text-gray-300">
        <a href="{{ route('profile.view', $comment->user->id) }}" class="text-[#8B0116] hover:underline">{{ $comment->user->name }}</a> am {{ $comment->created_at->format('d.m.Y H:i') }}
    </p>
    @isset($parentAuthor)
        <p class="text-xs text-gray-500 dark:text-gray-400 md:hidden">
            Antwort auf {{ $parentAuthor }}
        </p>
    @endisset
    <div class="mt-2 text-gray-800 dark:text-gray-200 whitespace-pre-line">
        {{ $comment->content }}
    </div>

    @if(auth()->id() === $comment->user_id)
        @php $editId = 'edit-content-' . $comment->id; @endphp
        <div x-data="{ editing: false }" class="mt-2">
            <button type="button" @click="editing = !editing" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded">Bearbeiten</button>

            <form x-show="editing" method="POST" action="{{ route('reviews.comments.update', $comment) }}" class="mt-2">
                @csrf
                @method('PUT')
                <x-form name="content" label="Kommentar" :id="$editId">
                    <textarea id="{{ $editId }}" name="content" aria-describedby="{{ $editId }}-error" rows="2" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 rounded mt-1" required>{{ old('content', $comment->content) }}</textarea>
                </x-form>
                <div class="mt-2 flex flex-col sm:flex-row gap-2">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded">Speichern</button>
                    <button type="button" @click="editing = false" class="bg-gray-600 hover:bg-gray-700 text-white px-3 py-1 rounded">Abbrechen</button>
                </div>
            </form>
        </div>
    @endif

    @if(auth()->id() === $comment->user_id || in_array($role ?? null, [\App\Enums\Role::Vorstand, \App\Enums\Role::Admin], true))
        <form method="POST" action="{{ route('reviews.comments.destroy', $comment) }}" class="mt-2">
            @csrf
            @method('DELETE')
            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded">Löschen</button>
        </form>
    @endif

    @foreach($comment->children as $child)
        @php
            $nextDepth = $depth + 1;
            $mobileIndent = match (true) {
                $nextDepth === 1 => 'ml-4',
                $nextDepth === 2 => 'ml-8',
                $nextDepth === 3 => 'ml-12',
                default => '',
            };
        @endphp
        <div class="{{ $mobileIndent }} md:ml-6">
            @include('reviews.partials.comment', [
                'comment' => $child,
                'role' => $role,
                'parentAuthor' => $comment->user->name,
                'depth' => $nextDepth,
            ])
        </div>
    @endforeach

    @php $replyId = 'reply-content-' . $comment->id; @endphp
    <form method="POST" action="{{ route('reviews.comments.store', $comment->review) }}" class="mt-2">
        @csrf
        <input type="hidden" name="parent_id" value="{{ $comment->id }}">
        <x-form name="content" label="Kommentar" :id="$replyId">
            <textarea id="{{ $replyId }}" name="content" aria-describedby="{{ $replyId }}-error" rows="2" class="w-full border-gray-300 dark:border-gray-600 dark:bg-gray-800 rounded mt-1" required></textarea>
        </x-form>
        <button type="submit" class="mt-2 bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded">Antworten</button>
    </form>
</div>
