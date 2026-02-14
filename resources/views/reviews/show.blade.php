<x-app-layout :title="$title" :description="$description">
    <x-member-page class="max-w-3xl">
            @if(session('success'))
                <x-alert icon="o-check-circle" class="alert-success mb-4">
                    {{ session('success') }}
                </x-alert>
            @endif

            <x-header title="Rezensionen zu „{{ $book->title }}“ (Nr. {{ $book->roman_number }})" size="text-3xl" separator class="mb-6" />

            @forelse($reviews as $review)
                <x-card shadow class="mb-6">
                    <h2 class="text-lg font-semibold text-base-content">{{ $review->title }}</h2>
                    <p class="text-sm text-base-content">
                        von
                        <a href="{{ route('profile.view', $review->user->id) }}" class="text-primary hover:underline">{{ $review->user->name }}</a>
                        am {{ $review->created_at->format('d.m.Y H:i') }} Uhr
                        @if(!$review->created_at->eq($review->updated_at))
                            , geändert am {{ $review->updated_at->format('d.m.Y') }} um {{ $review->updated_at->format('H:i') }} Uhr
                        @endif
                    </p>
                    <div class="mt-4 prose prose-slate dark:prose-invert max-w-none prose-a:text-primary prose-a:font-semibold prose-a:underline-offset-2">
                        {!! $review->formatted_content !!}
                    </div>

                    @if(in_array($role ?? null, [\App\Enums\Role::Vorstand, \App\Enums\Role::Admin], true) || auth()->id() === $review->user_id)
                        <div class="mt-4 flex flex-col sm:flex-row gap-2">
                            <x-button label="Rezension bearbeiten" link="{{ route('reviews.edit', $review) }}" icon="o-pencil" class="btn-info btn-sm" />
                            <form action="{{ route('reviews.destroy', $review) }}" method="POST">
                                @csrf
                                @method('DELETE')
                                <x-button label="Rezension löschen" type="submit" icon="o-trash" class="btn-error btn-sm" />
                            </form>
                        </div>
                    @endif
                    <div class="mt-6">
                        @foreach($review->comments->whereNull('parent_id') as $comment)
                            @include('reviews.partials.comment', ['comment' => $comment, 'role' => $role, 'depth' => 0])
                        @endforeach

                        <form method="POST" action="{{ route('reviews.comments.store', $review) }}" class="mt-4">
                            @csrf
                            <fieldset class="fieldset py-0">
                                <legend class="fieldset-legend mb-0.5">Kommentar</legend>
                                <textarea id="content" name="content" aria-describedby="content-error" rows="2" class="textarea textarea-bordered w-full" placeholder="Kommentieren..." required></textarea>
                            </fieldset>
                            <x-button label="Kommentar hinzufügen" type="submit" class="btn-info btn-sm mt-2" />
                        </form>
                    </div>
                </x-card>
            @empty
                <p class="text-base-content">Noch keine Rezensionen vorhanden.</p>
            @endforelse

            <x-button label="← Zurück zur Übersicht" link="{{ route('reviews.index') }}" class="btn-ghost btn-sm text-primary" />
    </x-member-page>
</x-app-layout>
