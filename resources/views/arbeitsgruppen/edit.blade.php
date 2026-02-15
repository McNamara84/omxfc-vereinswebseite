<x-app-layout>
    <x-member-page class="max-w-3xl">
        <x-header title="AG bearbeiten" separator>
            <x-slot:actions>
                <x-button label="Zurück" icon="o-arrow-left" link="{{ route('arbeitsgruppen.index') }}" class="btn-ghost" />
            </x-slot:actions>
        </x-header>

        <x-card>
            <x-form method="POST" action="{{ route('arbeitsgruppen.update', $team) }}" no-separator enctype="multipart/form-data">
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
                                    <option value="{{ $member->id }}" @selected(old('leader_id', $team->user_id) == $member->id)>{{ $member->name }}</option>
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

                    <fieldset class="fieldset py-0">
                        <legend class="fieldset-legend mb-0.5">Logo</legend>
                        <input type="file" name="logo" accept="image/*" class="file-input w-full" />
                        @error('logo')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </fieldset>
                </div>

                <x-slot:actions>
                    <x-button label="Abbrechen" link="{{ route('arbeitsgruppen.index') }}" class="btn-ghost" />
                    <x-button label="Speichern" type="submit" class="btn-primary" icon="o-check" />
                </x-slot:actions>
            </x-form>

            {{-- Mitglieder-Tabelle --}}
            <div class="mt-8">
                <x-header title="Mitglieder" size="text-lg" separator />

                <div class="overflow-x-auto">
                <table class="table table-zebra">
                    <thead class="text-base-content">
                        <tr>
                            <th>Name</th>
                            <th>Rolle</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($team->users as $member)
                        <tr class="hover:bg-base-200">
                            <td>{{ $member->name }}</td>
                            <td>{{ $member->id === $team->user_id ? 'AG-Leiter' : 'Mitwirkender' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="2" class="text-center py-8 text-base-content/50">
                                <x-icon name="o-users" class="w-12 h-12 opacity-30 mx-auto" />
                                <p class="mt-2">Keine Mitglieder in dieser AG.</p>
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
                </div>

                @can('addTeamMember', $team)
                    <x-form method="POST" action="{{ route('arbeitsgruppen.add-member', $team) }}" no-separator class="mt-4">
                        @csrf
                        <div class="flex flex-col sm:flex-row sm:items-end gap-2">
                            @php
                                $availableMemberOptions = $availableMembers->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->toArray();
                            @endphp
                            <x-select
                                name="user_id"
                                label="Mitglied hinzufügen"
                                :options="$availableMemberOptions"
                                placeholder="Mitglied auswählen"
                                required
                                class="flex-1"
                            />
                            <x-button label="Hinzufügen" type="submit" class="btn-primary" icon="o-plus" />
                        </div>
                        @error('user_id', 'addTeamMember')
                            <p class="mt-1 text-sm text-error">{{ $message }}</p>
                        @enderror
                    </x-form>
                @endcan
            </div>
        </x-card>
    </x-member-page>
</x-app-layout>
