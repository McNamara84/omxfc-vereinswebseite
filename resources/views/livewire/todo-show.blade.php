<x-member-page class="max-w-6xl space-y-8">
    @php
        $statusBadge = match ($this->todo->status->value) {
            'open' => ['label' => 'Offen', 'class' => 'badge-ghost', 'icon' => 'o-clock'],
            'assigned' => ['label' => 'In Bearbeitung', 'class' => 'badge-info', 'icon' => 'o-arrow-path'],
            'completed' => ['label' => 'Wartet auf Verifizierung', 'class' => 'badge-warning', 'icon' => 'o-eye'],
            'verified' => ['label' => 'Verifiziert', 'class' => 'badge-success', 'icon' => 'o-check-circle'],
        };
    @endphp

    <x-ui.page-header
        eyebrow="{{ $this->todo->category?->name ?? 'Challenge-Details' }}"
        title="{{ $this->todo->title }}"
        description="Alle relevanten Infos zur Challenge, ihr aktueller Status und die verfügbaren nächsten Schritte auf einer Seite."
    >
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
                <x-badge :value="$statusBadge['label']" :class="$statusBadge['class']" :icon="$statusBadge['icon']" />
                <span class="badge badge-outline rounded-full px-3 py-3">{{ $this->todo->points }} Baxx</span>
            </div>
        </x-slot:actions>
    </x-ui.page-header>

    <section class="grid gap-8 xl:grid-cols-[minmax(0,1.2fr)_minmax(18rem,0.8fr)] xl:items-start">
        <div class="space-y-6">
            <x-ui.panel title="Beschreibung" description="Worum es bei dieser Challenge geht und was für die Umsetzung relevant ist.">
                <div class="rounded-[1.5rem] border border-base-content/10 bg-base-100/78 px-5 py-5 text-sm leading-relaxed text-base-content/78 sm:text-base">
                    @if($this->todo->description)
                        {!! nl2br(e($this->todo->description)) !!}
                    @else
                        <span class="italic text-base-content/58">Keine Beschreibung vorhanden</span>
                    @endif
                </div>
            </x-ui.panel>

            <x-ui.panel title="Aktionen" description="Je nach Rolle und Status stehen dir hier die passenden nächsten Schritte direkt zur Verfügung.">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-button link="{{ route('todos.index') }}" wire:navigate icon="o-arrow-left" class="btn-ghost">
                            Zurück zur Übersicht
                        </x-button>
                        @if($this->canEdit)
                            <x-button link="{{ route('todos.edit', $this->todo) }}" wire:navigate icon="o-pencil" class="btn-info">
                                Bearbeiten
                            </x-button>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if($this->canAssign)
                            <x-button label="Challenge übernehmen" wire:click="assign" class="btn-info" wire:loading.attr="disabled" wire:target="assign" />
                        @endif

                        @if($this->canComplete)
                            <x-button label="Als erledigt markieren" wire:click="complete" class="btn-warning" wire:loading.attr="disabled" wire:target="complete" />
                        @endif

                        @if($this->canVerify)
                            <x-button label="Verifizieren und Baxx vergeben" wire:click="verify" class="btn-success" wire:loading.attr="disabled" wire:target="verify" />
                        @endif

                        @if($this->canRelease)
                            <x-button label="Challenge freigeben" wire:click="release" class="btn-ghost" wire:loading.attr="disabled" wire:target="release" />
                        @endif

                        @if($this->canDelete)
                            <x-button label="Challenge löschen" wire:click="$set('confirmingDelete', true)" icon="o-trash" class="btn-error" />
                        @endif
                    </div>
                </div>
            </x-ui.panel>
        </div>

        <div class="space-y-6 xl:sticky xl:top-6">
            <x-ui.panel title="Details" description="Stammdaten, Punkte und Ursprung der Challenge im Überblick.">
                <dl class="space-y-3">
                    <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/78 px-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-base-content/45">Baxx</dt>
                        <dd class="mt-1 text-sm font-semibold text-base-content sm:text-base">{{ $this->todo->points }}</dd>
                    </div>
                    <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/78 px-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-base-content/45">Kategorie</dt>
                        <dd class="mt-1 text-sm text-base-content sm:text-base">{{ $this->todo->category ? $this->todo->category->name : 'Keine Kategorie' }}</dd>
                    </div>
                    <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/78 px-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-base-content/45">Erstellt von</dt>
                        <dd class="mt-1 text-sm sm:text-base"><a href="{{ route('profile.view', $this->todo->creator->id) }}" wire:navigate class="text-primary hover:underline">{{ $this->todo->creator->name }}</a></dd>
                    </div>
                    <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/78 px-4 py-3">
                        <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-base-content/45">Erstellt am</dt>
                        <dd class="mt-1 text-sm text-base-content sm:text-base">{{ $this->todo->created_at->format('d.m.Y H:i') }}</dd>
                    </div>
                </dl>
            </x-ui.panel>

            <x-ui.panel title="Status" description="Zeigt, wer aktuell zuständig ist und welche Meilensteine bereits erreicht wurden.">
                <dl class="space-y-3">
                    @if($this->todo->assigned_to)
                        <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/78 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-base-content/45">Zugewiesen an</dt>
                            <dd class="mt-1 text-sm sm:text-base"><a href="{{ route('profile.view', $this->todo->assignee->id) }}" wire:navigate class="text-primary hover:underline">{{ $this->todo->assignee->name }}</a></dd>
                        </div>
                    @endif

                    @if($this->todo->completed_at)
                        <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/78 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-base-content/45">Erledigt am</dt>
                            <dd class="mt-1 text-sm text-base-content sm:text-base">{{ $this->todo->completed_at->format('d.m.Y H:i') }}</dd>
                        </div>
                    @endif

                    @if($this->todo->verified_by)
                        <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/78 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-base-content/45">Verifiziert von</dt>
                            <dd class="mt-1 text-sm sm:text-base"><a href="{{ route('profile.view', $this->todo->verifier->id) }}" wire:navigate class="text-primary hover:underline">{{ $this->todo->verifier->name }}</a></dd>
                        </div>
                        <div class="rounded-[1.25rem] border border-base-content/10 bg-base-100/78 px-4 py-3">
                            <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-base-content/45">Verifiziert am</dt>
                            <dd class="mt-1 text-sm text-base-content sm:text-base">{{ $this->todo->verified_at->format('d.m.Y H:i') }}</dd>
                        </div>
                    @endif

                    @if(! $this->todo->assigned_to && ! $this->todo->completed_at && ! $this->todo->verified_by)
                        <div class="rounded-[1.25rem] border border-dashed border-base-content/15 bg-base-100/65 px-4 py-4 text-sm leading-relaxed text-base-content/68">
                            Diese Challenge ist noch offen und wartet auf eine Übernahme.
                        </div>
                    @endif
                </dl>
            </x-ui.panel>
        </div>
    </section>

    {{-- Lösch-Bestätigung --}}
    <x-modal wire:model="confirmingDelete" title="Challenge löschen" separator>
        <div class="flex items-start gap-4">
            <div class="flex items-center justify-center size-10 rounded-full bg-error/10 shrink-0">
                <x-icon name="o-exclamation-triangle" class="size-6 text-error" />
            </div>
            <div class="text-sm text-base-content">
                @if($this->todo->status->value === 'verified')
                    <strong>Achtung:</strong> Die gutgeschriebenen {{ $this->todo->points }} Baxx werden dem Mitglied abgezogen!<br><br>
                @endif
                Möchtest du diese Challenge wirklich löschen? Diese Aktion kann nicht rückgängig gemacht werden.
            </div>
        </div>

        <x-slot:actions>
            <x-button label="Abbrechen" class="btn-ghost" wire:click="$set('confirmingDelete', false)" />
            <x-button label="Challenge löschen" class="btn-error ms-3" wire:click="deleteTodo"
                wire:loading.attr="disabled" wire:target="deleteTodo" />
        </x-slot:actions>
    </x-modal>
</x-member-page>
