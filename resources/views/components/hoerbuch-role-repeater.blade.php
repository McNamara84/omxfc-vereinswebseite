@props(['initialRoles' => [], 'users' => collect(), 'previousSpeakerUrl' => '', 'previousSpeakers' => []])

@php
    $members = $users->map(fn($u) => ['id' => $u->id, 'name' => $u->name])->values()->toArray();
    $rolesData = collect($initialRoles)->map(fn($r, $i) => [
        '_key' => $i,
        'name' => $r['name'] ?? '',
        'description' => $r['description'] ?? '',
        'takes' => $r['takes'] ?? 0,
        'contact_email' => $r['contact_email'] ?? '',
        'speaker_pseudonym' => $r['speaker_pseudonym'] ?? '',
        'member_name' => $r['speaker_name'] ?? ($r['member_name'] ?? ''),
        'member_id' => (string) ($r['user_id'] ?? ($r['member_id'] ?? '')),
        'uploaded' => (bool) ($r['uploaded'] ?? false),
        'previousSpeaker' => isset($r['name']) && isset($previousSpeakers[$r['name']])
            ? 'Bisheriger Sprecher: ' . $previousSpeakers[$r['name']]
            : ($r['previousSpeaker'] ?? ''),
    ])->values()->toArray();
@endphp

<div
    x-data="hoerbuchRoleRepeater({
        initialRoles: {{ Js::from($rolesData) }},
        members: {{ Js::from($members) }},
        previousSpeakerUrl: {{ Js::from($previousSpeakerUrl) }},
    })"
>
    {{-- Spalten-Header --}}
    <div
        x-show="roles.length > 0"
        x-cloak
        class="grid grid-cols-1 md:grid-cols-[1.5fr_2fr_auto_2fr_2fr_2fr_auto_auto] gap-2 md:items-center text-xs font-semibold uppercase tracking-wide text-base-content role-row-header"
    >
        <span>Rolle</span>
        <span>Beschreibung</span>
        <span class="md:text-center">Takes</span>
        <span>Kontakt (optional)</span>
        <span>Pseudonym (optional)</span>
        <span>Sprecher</span>
        <span id="roles-uploaded-header" class="md:text-center">Aufnahme hochgeladen</span>
        <span class="sr-only md:not-sr-only md:text-right">Aktionen</span>
    </div>

    {{-- Rollen-Zeilen --}}
    <template x-for="(role, i) in roles" :key="role._key">
        <div class="grid grid-cols-1 md:grid-cols-[1.5fr_2fr_auto_2fr_2fr_2fr_auto_auto] gap-2 mb-2 items-start md:items-center role-row">
            <input type="text" :name="`roles[${i}][name]`" x-model="role.name"
                @blur="fetchPreviousSpeaker(role)"
                placeholder="Rolle" aria-label="Rollenname"
                class="input input-bordered input-sm w-full" />

            <input type="text" :name="`roles[${i}][description]`" x-model="role.description"
                placeholder="Beschreibung" aria-label="Rollenbeschreibung"
                class="input input-bordered input-sm w-full" />

            <input type="number" :name="`roles[${i}][takes]`" x-model.number="role.takes"
                min="0" max="999" inputmode="numeric" placeholder="Takes" aria-label="Anzahl Takes"
                class="input input-bordered input-sm w-full md:max-w-[6rem]" />

            <input type="email" :name="`roles[${i}][contact_email]`" x-model="role.contact_email"
                placeholder="Kontakt (optional)" aria-label="Kontakt E-Mail"
                class="input input-bordered input-sm w-full" />

            <input type="text" :name="`roles[${i}][speaker_pseudonym]`" x-model="role.speaker_pseudonym"
                placeholder="Pseudonym (optional)" aria-label="Sprecherpseudonym"
                class="input input-bordered input-sm w-full" />

            <div class="flex flex-col gap-2">
                <div>
                    <input type="text" :name="`roles[${i}][member_name]`" x-model="role.member_name"
                        @input="lookupMemberId(role)" list="members"
                        placeholder="Sprecher" aria-label="Name des Sprechers"
                        class="input input-bordered input-sm w-full" />
                    <input type="hidden" :name="`roles[${i}][member_id]`" :value="role.member_id" />
                </div>
                <div class="text-xs text-base-content previous-speaker" aria-live="polite" x-text="role.previousSpeaker"></div>
            </div>

            <div class="flex items-center md:justify-center">
                <input type="hidden" :name="`roles[${i}][uploaded]`" value="0" :disabled="role.uploaded" />
                <input type="checkbox" :name="`roles[${i}][uploaded]`" value="1" x-model="role.uploaded"
                    aria-labelledby="roles-uploaded-header"
                    class="checkbox checkbox-primary checkbox-sm" />
            </div>

            <button type="button" class="text-error md:text-right" aria-label="Rolle entfernen"
                @click="removeRole(i)">&times;</button>
        </div>
    </template>

    <x-button type="button" @click="addRole()" label="Rolle hinzufügen" icon="o-plus" class="btn-ghost btn-sm mt-2" />

    <datalist id="members">
        @foreach($users as $member)
            <option data-id="{{ $member->id }}" value="{{ $member->name }}"></option>
        @endforeach
    </datalist>

    @error('roles')
        <p class="mt-1 text-sm text-error">{{ $message }}</p>
    @enderror
</div>
