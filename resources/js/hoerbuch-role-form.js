function initHoerbuchRoleForm() {
    const container = document.getElementById('roles_list');

    if (!container) return;

    // Guard: nicht doppelt initialisieren
    if (container.dataset.roleFormInitialized) return;
    container.dataset.roleFormInitialized = 'true';
    const datalistSelector = container.dataset.membersTarget;
    const datalist = datalistSelector ? document.querySelector(datalistSelector) : null;
    const members = datalist ? Array.from(datalist.options).map(option => ({
        id: option.dataset.id,
        name: option.value,
    })) : [];
    const previousSpeakerUrl = container.dataset.previousSpeakerUrl;
    let roleIndex = Number.parseInt(container.dataset.roleIndex || container.querySelectorAll('.role-row').length || '0', 10);
    if (Number.isNaN(roleIndex)) {
        roleIndex = 0;
    }

    function debounce(fn, delay = 300) {
        let timeout;
        return (...args) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn(...args), delay);
        };
    }

    function bindRoleRow(row) {
        const memberInput = row.querySelector('input[name$="[member_name]"]');
        const memberIdInput = row.querySelector('input[name$="[member_id]"]');
        const roleNameInput = row.querySelector('input[name$="[name]"]');
        const hint = row.querySelector('.previous-speaker');
        const uploadCheckbox = row.querySelector('input[type="checkbox"][name$="[uploaded]"]');
        const uploadHidden = row.querySelector('input[type="hidden"][name$="[uploaded]"]');
        let controller;

        if (uploadCheckbox && uploadHidden) {
            uploadHidden.disabled = uploadCheckbox.checked;
            uploadCheckbox.addEventListener('change', () => {
                uploadHidden.disabled = uploadCheckbox.checked;
            });
        }

        if (memberInput && memberIdInput) {
            memberInput.addEventListener('input', e => {
                const option = members.find(m => m.name === e.target.value);
                memberIdInput.value = option ? option.id : '';
            });
        }

        function updateHint() {
            const name = roleNameInput.value.trim();
            controller?.abort();
            if (!name || !previousSpeakerUrl) {
                hint.textContent = '';
                return;
            }
            controller = new AbortController();
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            const url = new URL(previousSpeakerUrl, window.location.origin);
            url.search = new URLSearchParams({ name }).toString();
            fetch(url, {
                signal: controller.signal,
                headers: token ? { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' } : { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(r => {
                    if (r.status === 401) throw new Error('unauthorized');
                    if (!r.ok) throw new Error('request-failed');
                    return r.json();
                })
                .then(data => {
                    hint.textContent = data.speaker ? `Bisheriger Sprecher: ${data.speaker}` : '';
                })
                .catch(err => {
                    if (err.name === 'AbortError') return;
                    hint.textContent = err.message === 'unauthorized'
                        ? 'Nicht berechtigt'
                        : 'Fehler beim Laden des bisherigen Sprechers';
                });
        }

        const debouncedHint = debounce(updateHint);
        roleNameInput.addEventListener('input', debouncedHint);
        roleNameInput.addEventListener('blur', updateHint);

        const removeButton = row.querySelector('[data-role-remove]');
        if (removeButton) {
            removeButton.addEventListener('click', () => row.remove());
        } else {
            console.warn('hoerbuch-role-form: missing [data-role-remove] button', row);
        }
        updateHint();
    }

    function addRole() {
        const wrapper = document.createElement('div');
        wrapper.className = 'grid grid-cols-1 md:grid-cols-[1.5fr_2fr_auto_2fr_2fr_2fr_auto_auto] gap-2 mb-2 items-start md:items-center role-row';
        wrapper.innerHTML = `
            <input type="text" name="roles[${roleIndex}][name]" placeholder="Rolle" aria-label="Rollenname" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50" />
            <input type="text" name="roles[${roleIndex}][description]" placeholder="Beschreibung" aria-label="Rollenbeschreibung" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50" />
            <input type="number" name="roles[${roleIndex}][takes]" min="0" max="999" inputmode="numeric" placeholder="Takes" aria-label="Anzahl Takes" class="w-full md:max-w-[6rem] rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50" />
            <input type="email" name="roles[${roleIndex}][contact_email]" placeholder="Kontakt (optional)" aria-label="Kontakt E-Mail" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50" />
            <input type="text" name="roles[${roleIndex}][speaker_pseudonym]" placeholder="Pseudonym (optional)" aria-label="Sprecherpseudonym" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50" />
            <div class="flex flex-col gap-2">
                <div>
                    <input type="text" name="roles[${roleIndex}][member_name]" list="members" placeholder="Sprecher" aria-label="Name des Sprechers" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50" />
                    <input type="hidden" name="roles[${roleIndex}][member_id]" />
                </div>
                <div class="text-xs text-gray-500 previous-speaker" aria-live="polite"></div>
            </div>
            <div class="flex items-center md:justify-center">
                <input type="hidden" name="roles[${roleIndex}][uploaded]" value="0" />
                <input type="checkbox" name="roles[${roleIndex}][uploaded]" value="1" aria-labelledby="roles-uploaded-header" class="rounded border-gray-300 dark:border-gray-600 text-[#8B0116] focus:ring-[#8B0116] dark:focus:ring-[#FF6B81]" />
            </div>
            <button type="button" class="text-red-600 md:text-right" aria-label="Rolle entfernen" data-role-remove>&times;</button>
        `;
        bindRoleRow(wrapper);
        container.appendChild(wrapper);
        roleIndex++;
    }

    document.getElementById('add_role')?.addEventListener('click', addRole);
    container.querySelectorAll('.role-row').forEach(bindRoleRow);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initHoerbuchRoleForm);
} else {
    initHoerbuchRoleForm();
}

document.addEventListener('livewire:navigated', initHoerbuchRoleForm);
