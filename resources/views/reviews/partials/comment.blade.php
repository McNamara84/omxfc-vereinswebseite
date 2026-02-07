@php
    $depth = $depth ?? 0;
@endphp
<div class="mt-4 bg-base-200 p-4 rounded">
    <p class="text-sm text-base-content/60">
        <a href="{{ route('profile.view', $comment->user->id) }}" class="text-primary hover:underline">{{ $comment->user->name }}</a> am {{ $comment->created_at->format('d.m.Y H:i') }}
    </p>
    @isset($parentAuthor)
        <p class="text-xs text-base-content/60 md:hidden">
            Antwort auf {{ $parentAuthor }}
        </p>
    @endisset
    <div class="mt-2 text-base-content whitespace-pre-line">
        {{ $comment->content }}
    </div>

    @if(auth()->id() === $comment->user_id)
        @php $editId = 'edit-content-' . $comment->id; @endphp
        <div x-data="{ editing: false }" class="mt-2">
            <x-button label="Bearbeiten" @click="editing = !editing" class="btn-info btn-sm" />

            <form x-show="editing" method="POST" action="{{ route('reviews.comments.update', $comment) }}" class="mt-2">
                @csrf
                @method('PUT')
                <x-field-group name="content" label="Kommentar" :id="$editId">
                    <textarea id="{{ $editId }}" name="content" aria-describedby="{{ $editId }}-error" rows="2" class="textarea textarea-bordered w-full" required>{{ old('content', $comment->content) }}</textarea>
                </x-field-group>
                <div class="mt-2 flex flex-col sm:flex-row gap-2">
                    <x-button label="Speichern" type="submit" class="btn-info btn-sm" />
                    <x-button label="Abbrechen" @click="editing = false" class="btn-ghost btn-sm" />
                </div>
            </form>
        </div>
    @endif

    @if(auth()->id() === $comment->user_id || in_array($role ?? null, [\App\Enums\Role::Vorstand, \App\Enums\Role::Admin], true))
        <form method="POST" action="{{ route('reviews.comments.destroy', $comment) }}" class="mt-2">
            @csrf
            @method('DELETE')
            <x-button label="Löschen" type="submit" class="btn-error btn-sm" />
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
        <x-field-group name="content" label="Kommentar" :id="$replyId">
            <textarea id="{{ $replyId }}" name="content" aria-describedby="{{ $replyId }}-error" rows="2" class="textarea textarea-bordered w-full" required></textarea>
        </x-field-group>
        <x-button label="Antworten" type="submit" class="btn-info btn-sm mt-2" />
    </form>
</div>
