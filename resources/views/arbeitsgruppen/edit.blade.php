<x-app-layout>
    <x-member-page class="max-w-3xl">
        <x-header title="AG bearbeiten" separator>
            <x-slot:actions>
                <x-button label="Zurück" icon="o-arrow-left" link="{{ route('arbeitsgruppen.index') }}" class="btn-ghost" />
            </x-slot:actions>
        </x-header>

        <x-card>
            <form action="{{ route('arbeitsgruppen.update', $team) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @php
                    $isAdmin = Auth::user()->hasRole(\App\Enums\Role::Admin);
                @endphp

                <div class="space-y-4">
                    <x-input
                        name="{{ $isAdmin ? 'name' : '' }}"
                        label="Name der AG"
                        value="{{ old('name', $team->name) }}"
                        required
                        :disabled="!$isAdmin"
                    />
                    @unless($isAdmin)
                        <input type="hidden" name="name" value="{{ old('name', $team->name) }}">
                    @endunless

                    @php
                        $userOptions = $users->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->toArray();
                    @endphp
                    @if($isAdmin)
                        <x-select
                            name="leader_id"
                            label="AG-Leiter"
                            :options="$userOptions"
                            :value="old('leader_id', $team->user_id)"
                            required
                        />
                    @else
                        <div>
                            <label class="fieldset-legend">AG-Leiter</label>
                            <select disabled class="select select-bordered w-full opacity-60">
                                @foreach($users as $member)
                                    <option value="{{ $member->id }}" {{ old('leader_id', $team->user_id) == $member->id ? 'selected' : '' }}>{{ $member->name }}</option>
                                @endforeach
                            </select>
                            <input type="hidden" name="leader_id" value="{{ old('leader_id', $team->user_id) }}">
                        </div>
                    @endif

                    <x-textarea
                        name="description"
                        label="Beschreibung"
                        rows="3"
                    >{{ old('description', $team->description) }}</x-textarea>

                    <x-input
                        name="email"
                        label="E-Mail-Adresse"
                        type="email"
                        value="{{ old('email', $team->email) }}"
                    />

                    <x-input
                        name="meeting_schedule"
                        label="Wiederkehrender Termin"
                        value="{{ old('meeting_schedule', $team->meeting_schedule) }}"
                    />

                    <div>
                        <label for="logo" class="fieldset-legend">Logo</label>
                        <input type="file" name="logo" id="logo" accept="image/*" class="file-input file-input-bordered w-full">
                        @error('logo')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="flex justify-end gap-3 mt-6">
                    <x-button label="Abbrechen" link="{{ route('arbeitsgruppen.index') }}" class="btn-ghost" />
                    <x-button label="Speichern" type="submit" class="btn-primary" icon="o-check" />
                </div>
            </form>

            {{-- Mitglieder-Tabelle --}}
            <div class="mt-8">
                <h3 class="text-lg font-semibold text-base-content mb-2">Mitglieder</h3>
                <x-card>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Rolle</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($team->users as $member)
                                <tr>
                                    <td>{{ $member->name }}</td>
                                    <td>{{ $member->id === $team->user_id ? 'AG-Leiter' : 'Mitwirkender' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>

                @can('addTeamMember', $team)
                    <form action="{{ route('arbeitsgruppen.add-member', $team) }}" method="POST" class="flex flex-col sm:flex-row sm:items-center gap-2 mt-4">
                        @csrf
                        @php
                            $availableMemberOptions = $availableMembers->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->toArray();
                        @endphp
                        <x-select
                            name="user_id"
                            :options="$availableMemberOptions"
                            placeholder="Mitglied auswählen"
                            required
                            class="flex-1"
                        />
                        <x-button label="Hinzufügen" type="submit" class="btn-primary" icon="o-plus" />
                    </form>
                    @error('user_id', 'addTeamMember')
                        <p class="mt-1 text-sm text-error">{{ $message }}</p>
                    @enderror
                @endcan
            </div>
        </x-card>
    </x-member-page>
</x-app-layout>
