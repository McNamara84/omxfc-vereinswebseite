<x-app-layout>
    <x-member-page class="max-w-3xl">
        <x-card shadow>
            <x-header title="{{ $episode->title }}" separator />

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div><span class="font-medium">Folge:</span> {{ $episode->episode_number }}</div>
                <div><span class="font-medium">Autor:</span> {{ $episode->author }}</div>
                <div><span class="font-medium">Ziel-EVT:</span> {{ $episode->planned_release_date }}</div>
                <div><span class="font-medium">Status:</span> {{ $episode->status->value }}</div>
                <div class="md:col-span-2">
                    <span class="font-medium">Fortschritt:</span>
                    <div class="w-full bg-base-200 rounded-full h-4 mt-1">
                        <div class="h-4 rounded-full text-xs font-medium text-center leading-none text-white" style="width: {{ $episode->progress }}%; background-color: hsl({{ $episode->progressHue() }}, 100%, 40%);">
                            {{ $episode->progress }}%
                        </div>
                    </div>
                </div>
                <div class="md:col-span-2">
                    <span class="font-medium">Rollen besetzt:</span>
                    <div class="w-full bg-base-200 rounded-full h-4 mt-1">
                        <div class="h-4 rounded-full text-xs font-medium text-center leading-none text-white" style="width: {{ $episode->rolesFilledPercent() }}%; background-color: hsl({{ $episode->rolesHue() }}, 100%, 40%);">
                            {{ $episode->roles_filled }}/{{ $episode->roles_total }}
                        </div>
                    </div>
                </div>
                @if($episode->roles->isNotEmpty())
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
                                @foreach($episode->roles as $role)
                                @php
                                    $rowClasses = $role->uploaded ? 'bg-success/10 text-success-content' : '';
                                @endphp
                                <tr class="{{ $rowClasses }}">
                                    <td class="align-top">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span>{{ $role->name }}</span>
                                            @if($role->uploaded)
                                                <x-badge value="Upload vorhanden" class="badge-success badge-sm" />
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ $role->description }}</td>
                                    <td>{{ $role->takes }}</td>
                                    <td>
                                        {{ $role->user?->name ?? $role->speaker_name ?? '-' }}
                                        @php($prev = $previousSpeakers[$role->name] ?? null)
                                        @if($prev)
                                            <div class="text-xs text-base-content">Bisheriger Sprecher: {{ $prev }}</div>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
                <div class="md:col-span-2"><span class="font-medium">Verantwortlich:</span> {{ $episode->responsible?->name ?? '-' }}</div>
                <div class="md:col-span-2">
                    <span class="font-medium">Anmerkungen:</span>
                    <p class="mt-1">{{ $episode->notes }}</p>
                </div>
            </div>

            @if(auth()->user()->hasVorstandRole() || auth()->user()->isOwnerOfTeam('AG Fanhörbücher'))
            <div class="mt-6 flex justify-end space-x-3">
                <x-button label="Bearbeiten" link="{{ route('hoerbuecher.edit', $episode) }}" icon="o-pencil" class="btn-info btn-sm" />
                <x-confirm-delete :action="route('hoerbuecher.destroy', $episode)" />
            </div>
            @endif
            <div class="mt-6">
                <x-button label="« Zurück zur Übersicht" link="{{ route('hoerbuecher.index') }}" icon="o-arrow-left" class="btn-ghost btn-sm" />
            </div>
        </x-card>
    </x-member-page>
</x-app-layout>
