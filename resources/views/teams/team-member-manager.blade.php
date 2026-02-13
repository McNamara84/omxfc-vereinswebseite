<div>
    {{-- Mitglied hinzufügen --}}
    @if (Gate::check('addTeamMember', $team))
        <x-hr class="my-8" />

        <x-card>
            <x-header title="Mitglied hinzufügen" subtitle="Füge ein neues Mitglied zu dieser Arbeitsgruppe hinzu." size="text-lg" class="!mb-4" />

            <p class="text-sm text-base-content mb-4">
                Gib die E-Mail-Adresse der Person ein, die du zu dieser Arbeitsgruppe hinzufügen möchtest.
            </p>

            <form wire:submit="addTeamMember">
                <x-input label="E-Mail-Adresse" type="email" wire:model="addTeamMemberForm.email" errorField="email" />

                {{-- Rollenauswahl --}}
                @if (count($this->roles) > 0)
                    <div class="mt-4">
                        <label class="label">
                            <span class="label-text font-medium">Rolle</span>
                        </label>
                        @error('role') <p class="text-error text-sm mt-1">{{ $message }}</p> @enderror

                        <div class="mt-1 border border-base-300 rounded-lg overflow-hidden">
                            @foreach ($this->roles as $index => $role)
                                <button type="button"
                                    class="relative px-4 py-3 inline-flex w-full text-start focus:z-10 focus:outline-none focus:ring-2 focus:ring-primary {{ $index > 0 ? 'border-t border-base-300' : '' }}"
                                    wire:click="$set('addTeamMemberForm.role', '{{ $role->key }}')"
                                >
                                    <div class="{{ isset($addTeamMemberForm['role']) && $addTeamMemberForm['role'] !== $role->key ? 'opacity-50' : '' }}">
                                        <div class="flex items-center">
                                            <span class="text-sm text-base-content {{ $addTeamMemberForm['role'] == $role->key ? 'font-semibold' : '' }}">
                                                {{ $role->name }}
                                            </span>

                                            @if ($addTeamMemberForm['role'] == $role->key)
                                                <x-icon name="o-check-circle" class="ms-2 w-5 h-5 text-success" />
                                            @endif
                                        </div>

                                        <div class="mt-1 text-xs text-base-content/70">
                                            {{ $role->description }}
                                        </div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="mt-6 flex items-center justify-end gap-3">
                    <x-action-message class="me-3" on="saved">
                        <span class="text-sm text-success flex items-center gap-1">
                            <x-icon name="o-check-circle" class="w-5 h-5" aria-hidden="true" />
                            Hinzugefügt.
                        </span>
                    </x-action-message>

                    <x-button type="submit" label="Hinzufügen" class="btn-primary" icon="o-plus" />
                </div>
            </form>
        </x-card>
    @endif

    {{-- Ausstehende Einladungen --}}
    @if ($team->teamInvitations->isNotEmpty() && Gate::check('addTeamMember', $team))
        <x-hr class="my-8" />

        <x-card>
            <x-header title="Ausstehende Einladungen" subtitle="Diese Personen wurden eingeladen und haben eine E-Mail erhalten. Sie können der Arbeitsgruppe beitreten, indem sie die Einladung annehmen." size="text-lg" class="!mb-4" />

            <div class="space-y-4">
                @foreach ($team->teamInvitations as $invitation)
                    <div class="flex items-center justify-between rounded-lg bg-base-200 px-4 py-3">
                        <span class="text-base-content">{{ $invitation->email }}</span>

                        @if (Gate::check('removeTeamMember', $team))
                            <x-button
                                label="Zurückziehen"
                                class="btn-ghost btn-sm text-error"
                                icon="o-x-mark"
                                wire:click="cancelTeamInvitation({{ $invitation->id }})"
                            />
                        @endif
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    {{-- Mitgliederliste --}}
    @if ($team->users->isNotEmpty())
        <x-hr class="my-8" />

        <x-card>
            <x-header title="Mitglieder" subtitle="Alle Personen, die dieser Arbeitsgruppe angehören." size="text-lg" class="!mb-4" />

            <div class="space-y-3">
                @foreach ($team->users->sortBy('name') as $user)
                    <div class="flex items-center justify-between rounded-lg bg-base-200 px-4 py-3">
                        <a href="{{ route('profile.view', $user->id) }}" class="flex items-center gap-3 hover:opacity-80 transition-opacity">
                            <x-avatar :image="$user->profile_photo_url" class="!w-8 !h-8" />
                            <span class="text-base-content font-medium">{{ $user->name }}</span>
                        </a>

                        <div class="flex items-center gap-2">
                            {{-- Rollenmanagement --}}
                            @if (Gate::check('updateTeamMember', $team) && Laravel\Jetstream\Jetstream::hasRoles())
                                <x-button
                                    :label="Laravel\Jetstream\Jetstream::findRole($user->membership->role)->name"
                                    class="btn-ghost btn-sm"
                                    wire:click="manageRole('{{ $user->id }}')"
                                />
                            @elseif (Laravel\Jetstream\Jetstream::hasRoles())
                                <x-badge :value="Laravel\Jetstream\Jetstream::findRole($user->membership->role)->name" class="badge-ghost" />
                            @endif

                            {{-- Arbeitsgruppe verlassen --}}
                            @if ($this->user->id === $user->id)
                                <x-button
                                    label="Verlassen"
                                    class="btn-ghost btn-sm text-error"
                                    icon="o-arrow-right-start-on-rectangle"
                                    wire:click="$toggle('confirmingLeavingTeam')"
                                />
                            {{-- Mitglied entfernen --}}
                            @elseif (Gate::check('removeTeamMember', $team))
                                <x-button
                                    label="Entfernen"
                                    class="btn-ghost btn-sm text-error"
                                    icon="o-user-minus"
                                    wire:click="confirmTeamMemberRemoval('{{ $user->id }}')"
                                />
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </x-card>
    @endif

    {{-- Rollenverwaltungs-Modal --}}
    <x-mary-modal wire:model="currentlyManagingRole" title="Rolle verwalten" separator>
        <div class="border border-base-300 rounded-lg overflow-hidden">
            @foreach ($this->roles as $index => $role)
                <button type="button"
                    class="relative px-4 py-3 inline-flex w-full text-start focus:z-10 focus:outline-none focus:ring-2 focus:ring-primary {{ $index > 0 ? 'border-t border-base-300' : '' }}"
                    wire:click="$set('currentRole', '{{ $role->key }}')"
                >
                    <div class="{{ $currentRole !== $role->key ? 'opacity-50' : '' }}">
                        <div class="flex items-center">
                            <span class="text-sm text-base-content {{ $currentRole == $role->key ? 'font-semibold' : '' }}">
                                {{ $role->name }}
                            </span>

                            @if ($currentRole == $role->key)
                                <x-icon name="o-check-circle" class="ms-2 w-5 h-5 text-success" />
                            @endif
                        </div>

                        <div class="mt-1 text-xs text-base-content/70">
                            {{ $role->description }}
                        </div>
                    </div>
                </button>
            @endforeach
        </div>

        <x-slot:actions>
            <x-button label="Abbrechen" wire:click="stopManagingRole" wire:loading.attr="disabled" />
            <x-button label="Speichern" class="btn-primary" wire:click="updateRole" wire:loading.attr="disabled" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Arbeitsgruppe-verlassen-Modal --}}
    <x-mary-modal wire:model="confirmingLeavingTeam" title="Arbeitsgruppe verlassen" separator>
        <p class="text-base-content">
            Bist du sicher, dass du diese Arbeitsgruppe verlassen möchtest?
        </p>

        <x-slot:actions>
            <x-button label="Abbrechen" wire:click="$toggle('confirmingLeavingTeam')" wire:loading.attr="disabled" />
            <x-button label="Verlassen" class="btn-error" wire:click="leaveTeam" wire:loading.attr="disabled" icon="o-arrow-right-start-on-rectangle" />
        </x-slot:actions>
    </x-mary-modal>

    {{-- Mitglied-entfernen-Modal --}}
    <x-mary-modal wire:model="confirmingTeamMemberRemoval" title="Mitglied entfernen" separator>
        <p class="text-base-content">
            Bist du sicher, dass du diese Person aus der Arbeitsgruppe entfernen möchtest?
        </p>

        <x-slot:actions>
            <x-button label="Abbrechen" wire:click="$toggle('confirmingTeamMemberRemoval')" wire:loading.attr="disabled" />
            <x-button label="Entfernen" class="btn-error" wire:click="removeTeamMember" wire:loading.attr="disabled" icon="o-user-minus" />
        </x-slot:actions>
    </x-mary-modal>
</div>