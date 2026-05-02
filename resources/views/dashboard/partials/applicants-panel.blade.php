@if($anwaerter->isNotEmpty())
    <x-ui.panel title="Mitgliedsanträge" description="Neue Vereinsanträge können hier direkt geprüft, genehmigt oder abgelehnt werden." data-testid="dashboard-applicants-panel">
        <div x-data="{ rejectUrl: '' }">
            <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>E-Mail</th>
                            <th>Beitrag</th>
                            <th class="text-center">Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($anwaerter as $person)
                            <tr data-testid="dashboard-applicant-row">
                                <td>
                                    <a href="{{ route('profile.view', $person->id) }}" wire:navigate class="text-primary hover:underline">{{ $person->name }}</a>
                                </td>
                                <td>{{ $person->email }}</td>
                                <td>{{ $person->mitgliedsbeitrag }}</td>
                                <td>
                                    <div class="flex justify-center gap-2">
                                        <form action="{{ route('anwaerter.approve', $person->id) }}" method="POST">
                                            @csrf
                                            <x-button type="submit" label="Genehmigen" class="btn-success btn-sm" icon="o-check" />
                                        </form>
                                        <x-button
                                            label="Ablehnen"
                                            class="btn-error btn-sm"
                                            icon="o-x-mark"
                                            @click="rejectUrl = '{{ route('anwaerter.reject', $person->id) }}'; document.getElementById('reject-anwaerter-modal').showModal()"
                                        />
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <x-mary-modal id="reject-anwaerter-modal" title="Antrag ablehnen" separator without-trap-focus>
                <p class="text-base-content">
                    Möchtest du diesen Mitgliedsantrag wirklich ablehnen? Der Nutzer wird dadurch gelöscht.
                </p>

                <x-slot:actions>
                    <x-button label="Abbrechen" @click="document.getElementById('reject-anwaerter-modal').close()" />
                    <form :action="rejectUrl" method="POST" class="inline">
                        @csrf
                        <x-button type="submit" label="Ablehnen" class="btn-error" icon="o-x-mark" />
                    </form>
                </x-slot:actions>
            </x-mary-modal>
        </div>
    </x-ui.panel>
@endif