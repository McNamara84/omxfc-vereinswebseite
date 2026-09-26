<x-member-layout>
    <x-member-page class="max-w-5xl">
        <x-ui.page-header title="Verbesserungsantrag" eyebrow="Rollenspiel" :description="$character->displayName()" />
        <div class="flex flex-wrap gap-3 my-4"><a class="btn btn-ghost" href="{{ route('rpg.characters.history', $character) }}">Charakterverlauf</a>@can('manage-rpg-experience')<a class="btn btn-ghost" href="{{ route('rpg.advancements.index') }}">Offene Anträge</a>@endcan</div>
        @include('rpg.progression.partials.messages')
        <p class="font-semibold" data-testid="advancement-status">{{ $advancement->statusLabel() }} · Kosten: {{ $advancement->cost }} EP · Aktuell verfügbar: {{ $balance }} EP</p>
        <p class="text-sm my-3">Beantragt am {{ $advancement->created_at->format('d.m.Y H:i') }}.</p>
        @include('rpg.progression.partials.changes', ['changes' => $advancement->changes])
        @include('rpg.progression.partials.state')
        @if($advancement->decided_at)
            <p>Entschieden am {{ $advancement->decided_at->format('d.m.Y H:i') }} von {{ $advancement->reviewer?->nicknameOrName() ?? 'Ehemaliges Mitglied' }}.</p>
            @if($advancement->decision_reason)<p class="whitespace-pre-wrap">{{ $advancement->decision_reason }}</p>@endif
        @else
            @can('manage-rpg-experience')
                <div class="grid gap-5 sm:grid-cols-2 my-5">
                    <form method="POST" action="{{ route('rpg.advancements.approve', $advancement) }}">@csrf<p class="mb-3">Mit der Genehmigung werden alle Änderungen angewendet und {{ $advancement->cost }} EP ausgegeben.</p><button class="btn btn-primary">Verbesserung genehmigen</button></form>
                    <form method="POST" action="{{ route('rpg.advancements.reject', $advancement) }}" class="space-y-3">@csrf<label class="fieldset">Begründung der Ablehnung<textarea name="reason" class="textarea w-full" required maxlength="4000">{{ old('reason') }}</textarea></label><button class="btn btn-outline">Antrag ablehnen</button></form>
                </div>
            @endcan
            @can('improve', $character)
                <form method="POST" action="{{ route('rpg.advancements.withdraw', $advancement) }}" class="my-4">@csrf<button class="btn btn-ghost">Antrag zurückziehen</button></form>
            @endcan
        @endif
    </x-member-page>
</x-member-layout>
