import Alpine from 'alpinejs';

Alpine.data('hoerbuchRoleRepeater', ({ initialRoles = [], members = [], previousSpeakerUrl = '' }) => ({
    roles: initialRoles,
    members,
    previousSpeakerUrl,
    nextKey: initialRoles.length,

    addRole() {
        this.roles.push({
            _key: this.nextKey++,
            name: '',
            description: '',
            takes: 0,
            contact_email: '',
            speaker_pseudonym: '',
            member_name: '',
            member_id: '',
            uploaded: false,
            previousSpeaker: '',
        });
    },

    removeRole(index) {
        this.roles.splice(index, 1);
    },

    lookupMemberId(role) {
        const match = this.members.find(m => m.name === role.member_name);
        role.member_id = match ? String(match.id) : '';
    },

    async fetchPreviousSpeaker(role) {
        const name = role.name?.trim();
        if (!name || !this.previousSpeakerUrl) {
            role.previousSpeaker = '';
            return;
        }
        try {
            const url = new URL(this.previousSpeakerUrl, window.location.origin);
            url.searchParams.set('name', name);
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            const headers = { 'X-Requested-With': 'XMLHttpRequest' };
            if (token) headers['X-CSRF-TOKEN'] = token;
            const res = await fetch(url, { headers });
            if (res.status === 401) {
                role.previousSpeaker = 'Nicht berechtigt';
                return;
            }
            if (!res.ok) throw new Error();
            const data = await res.json();
            role.previousSpeaker = data.speaker ? `Bisheriger Sprecher: ${data.speaker}` : '';
        } catch {
            role.previousSpeaker = 'Fehler beim Laden des bisherigen Sprechers';
        }
    },
}));
