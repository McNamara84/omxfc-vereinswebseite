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
                        <table class="min-w-full text-sm">
                            <thead class="bg-base-200">
                                <tr>
                                    <th class="px-2 py-1 text-left">Rolle</th>
                                    <th class="px-2 py-1 text-left">Beschreibung</th>
                                    <th class="px-2 py-1 text-left">Takes</th>
                                    <th class="px-2 py-1 text-left">Sprecher</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($episode->roles as $role)
                                @php
                                    $rowClasses = 'border-t border-base-content/10 transition-colors';
                                    if ($role->uploaded) {
                                        $rowClasses .= ' bg-success/10 text-success-content';
                                    } else {
                                        $rowClasses .= ' hover:bg-base-200/50';
                                    }
                                @endphp
                                <tr class="{{ $rowClasses }}">
                                    <td class="px-2 py-1 align-top">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span>{{ $role->name }}</span>
                                            @if($role->uploaded)
                                                <span class="inline-flex items-center gap-1 rounded-full bg-success/10 px-2 py-0.5 text-xs font-semibold text-success" role="status" aria-label="Aufnahme für diese Rolle wurde hochgeladen">
                                                    <x-icon name="o-check" class="h-3.5 w-3.5" />
                                                    <span>Upload vorhanden</span>
                                                </span>
                                                <span class="sr-only">Für diese Rolle wurde bereits eine Aufnahme hochgeladen.</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-2 py-1">{{ $role->description }}</td>
                                    <td class="px-2 py-1">{{ $role->takes }}</td>
                                    <td class="px-2 py-1">
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
