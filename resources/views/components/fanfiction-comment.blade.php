@props(['comment', 'fanfiction', 'depth' => 0])

@php
    $maxDepth = 3;
    $canEdit = auth()->id() === $comment->user_id;
    $canDelete = auth()->id() === $comment->user_id || auth()->user()?->hasTeamRole(auth()->user()?->currentTeam, 'Vorstand') || auth()->user()?->hasTeamRole(auth()->user()?->currentTeam, 'Admin');
@endphp

<div class="@if($depth > 0) ml-8 pl-4 border-l-2 border-gray-200 dark:border-gray-700 @endif" x-data="{ editing: false, replying: false }">
    <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
        <div class="flex items-start justify-between mb-2">
            <div class="flex items-center gap-2">
                @if($comment->user && $comment->user->profile_photo_path)
                    <img src="{{ $comment->user->profile_photo_url }}" alt="{{ $comment->user->name }}" class="w-8 h-8 rounded-full">
                @else
                    <div class="w-8 h-8 rounded-full bg-indigo-500 flex items-center justify-center text-white text-sm font-medium">
                        {{ $comment->user ? substr($comment->user->name, 0, 1) : '?' }}
                    </div>
                @endif
                <div>
                    <span class="font-medium text-gray-900 dark:text-gray-100">
                        {{ $comment->user?->name ?? 'Gelöschter Benutzer' }}
                    </span>
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        • {{ $comment->created_at->diffForHumans() }}
                        @if($comment->updated_at->gt($comment->created_at))
                            (bearbeitet)
                        @endif
                    </span>
                </div>
            </div>
            @if($canEdit || $canDelete)
                <div class="flex items-center gap-2">
                    @if($canEdit)
                        <button @click="editing = true" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                            </svg>
                        </button>
                    @endif
                    @if($canDelete)
                        <form action="{{ route('fanfiction.comments.destroy', $comment) }}" method="POST" class="inline" onsubmit="return confirm('Kommentar wirklich löschen?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-gray-400 hover:text-red-600 dark:hover:text-red-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                </svg>
                            </button>
                        </form>
                    @endif
                </div>
            @endif
        </div>

        {{-- Anzeige-Modus --}}
        <div x-show="!editing" class="text-gray-700 dark:text-gray-300">
            {{ $comment->content }}
        </div>

        {{-- Bearbeitungs-Modus --}}
        <form x-show="editing" x-cloak action="{{ route('fanfiction.comments.update', $comment) }}" method="POST">
            @csrf
            @method('PUT')
            <textarea name="content" rows="3"
                class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
            >{{ $comment->content }}</textarea>
            <div class="mt-2 flex justify-end gap-2">
                <button type="button" @click="editing = false" class="px-3 py-1 text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">
                    Abbrechen
                </button>
                <button type="submit" class="px-3 py-1 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700">
                    Speichern
                </button>
            </div>
        </form>

        {{-- Antworten-Button --}}
        @if($depth < $maxDepth)
            <div class="mt-2">
                <button @click="replying = !replying" class="text-sm text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300">
                    <span x-text="replying ? 'Abbrechen' : 'Antworten'"></span>
                </button>
            </div>

            {{-- Antwort-Formular --}}
            <form x-show="replying" x-cloak action="{{ route('fanfiction.comments.store', $fanfiction) }}" method="POST" class="mt-3">
                @csrf
                <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                <textarea name="content" rows="2"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                    placeholder="Deine Antwort..."
                    required
                ></textarea>
                <div class="mt-2 flex justify-end">
                    <button type="submit" class="px-3 py-1 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700">
                        Antworten
                    </button>
                </div>
            </form>
        @endif
    </div>

    {{-- Verschachtelte Antworten --}}
    @if($comment->replies && $comment->replies->count() > 0)
        <div class="mt-4 space-y-4">
            @foreach($comment->replies as $reply)
                <x-fanfiction-comment :comment="$reply" :fanfiction="$fanfiction" :depth="$depth + 1" />
            @endforeach
        </div>
    @endif
</div>
