<x-slot:head>
    <meta name="robots" content="noindex, nofollow">
</x-slot:head>
<x-member-page class="max-w-3xl">
    <x-card shadow>
        <x-header title="{{ $this->episode->title }}" separator />

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div><span class="font-medium">Folge:</span> {{ $this->episode->episode_number }}</div>
            <div><span class="font-medium">Autor:</span> {{ $this->episode->author }}</div>
            <div><span class="font-medium">Ziel-EVT:</span> {{ $this->episode->planned_release_date }}</div>
            <div><span class="font-medium">Status:</span> {{ $this->episode->status->value }}</div>
            <div class="md:col-span-2">
                <span class="font-medium">Fortschritt:</span>
                <div class="w-full bg-base-200 rounded-full h-4 mt-1">
                    <div class="h-4 rounded-full text-xs font-medium text-center leading-none text-white" style="width: {{ $this->episode->progress }}%; background-color: hsl({{ $this->episode->progressHue() }}, 100%, 40%);">
                        {{ $this->episode->progress }}%
                    </div>
                </div>
            </div>
            <div class="md:col-span-2">
                <span class="font-medium">Rollen besetzt:</span>
                <div class="w-full bg-base-200 rounded-full h-4 mt-1">
                    <div class="h-4 rounded-full text-xs font-medium text-center leading-none text-white" style="width: {{ $this->episode->rolesFilledPercent() }}%; background-color: hsl({{ $this->episode->rolesHue() }}, 100%, 40%);">
                        {{ $this->episode->roles_filled }}/{{ $this->episode->roles_total }}
                    </div>
                </div>
            </div>
            @if($this->episode->roles->isNotEmpty())
            <div class="md:col-span-2">
                <span class="font-medium">Rollen:</span>
                <div class="mt-1 overflow-x-auto">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Rolle</th>
                                <th>Beschreibung</th>
                                <th>Takes</th>
                                <th>Sprecher</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($this->episode->roles as $role)
                            @php
                                $rowClasses = $role->uploaded ? 'bg-success/10 text-success-content' : '';
                            @endphp
                            <tr class="{{ $rowClasses }}">
                                <td class="align-top">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span>{{ $role->name }}</span>
                                        @if($role->uploaded)
                                            <x-badge value="Upload vorhanden" class="badge-success badge-sm" role="status" aria-label="Aufnahme für diese Rolle wurde hochgeladen" icon="o-check-circle" />
                                            <span class="sr-only">Für diese Rolle wurde bereits eine Aufnahme hochgeladen.</span>
                                        @endif
                                    </div>
                                </td>
                                <td>{{ $role->description }}</td>
                                <td>{{ $role->takes }}</td>
                                <td>
                                    {{ $role->user?->name ?? $role->speaker_name ?? '-' }}
                                    @if(auth()->user()?->hasVorstandRole() || auth()->user()?->isMemberOfTeam('AG Fanhörbücher'))
                                        @php($prev = $this->previousSpeakers[$role->name] ?? null)
                                        @if($prev)
                                            <div class="text-xs text-base-content">Bisheriger Sprecher: {{ $prev }}</div>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
            @if(auth()->user()?->hasVorstandRole() || auth()->user()?->isMemberOfTeam('AG Fanhörbücher'))
                <div class="md:col-span-2"><span class="font-medium">Verantwortlich:</span> {{ $this->episode->responsible?->name ?? '-' }}</div>
                <div class="md:col-span-2">
                    <span class="font-medium">Anmerkungen:</span>
                    <p class="mt-1">{{ $this->episode->notes }}</p>
                </div>
            @endif
        </div>

        @if($this->canManage)
        <div class="mt-6 flex justify-end space-x-3">
            <x-button label="Bearbeiten" link="{{ route('hoerbuecher.edit', $this->episode) }}" wire:navigate icon="o-pencil" class="btn-info btn-sm" />
            <x-button label="Löschen" wire:click="$set('confirmingDelete', true)" icon="o-trash" class="btn-error btn-sm" />
        </div>

        <x-modal wire:model="confirmingDelete" title="Hörbuchfolge löschen">
            <p>Möchtest du die Hörbuchfolge <strong>{{ $this->episode->title }}</strong> wirklich löschen?</p>
            <x-slot:actions>
                <x-button label="Abbrechen" @click="$wire.confirmingDelete = false" class="btn-ghost" />
                <x-button label="Löschen" wire:click="deleteEpisode" class="btn-error" wire:loading.attr="disabled" />
            </x-slot:actions>
        </x-modal>
        @endif

        <div class="mt-6">
            <x-button label="« Zurück zur Übersicht" link="{{ route('hoerbuecher.index') }}" wire:navigate icon="o-arrow-left" class="btn-ghost btn-sm" />
        </div>
    </x-card>
</x-member-page>
