const data = window.roleFormData;
if (data) {
    const members = data.members || [];
    const previousSpeakerUrl = data.previousSpeakerUrl;
    let roleIndex = data.roleIndex || 0;

    function debounce(fn, delay = 300) {
        let timeout;
        return (...args) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => fn(...args), delay);
        };
    }

    function bindRoleRow(row) {
        const memberInput = row.querySelector('input[list]');
        const hidden = row.querySelector('input[type="hidden"]');
        const roleNameInput = row.querySelector('input[name$="[name]"]');
        const hint = row.querySelector('.previous-speaker');
        let controller;

        memberInput.addEventListener('input', e => {
            const option = members.find(m => m.name === e.target.value);
            hidden.value = option ? option.id : '';
        });

        function updateHint() {
            const name = roleNameInput.value.trim();
            controller?.abort();
            if (!name) {
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

        row.querySelector('button').addEventListener('click', () => row.remove());
        updateHint();
    }

    function addRole() {
        const container = document.getElementById('roles_list');
        const wrapper = document.createElement('div');
        wrapper.className = 'grid grid-cols-5 gap-2 mb-2 items-start role-row';
        wrapper.innerHTML = `
            <input type="text" name="roles[${roleIndex}][name]" placeholder="Rolle" class="col-span-1 w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50" />
            <input type="text" name="roles[${roleIndex}][description]" placeholder="Beschreibung" class="col-span-1 w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50" />
            <input type="number" name="roles[${roleIndex}][takes]" min="0" placeholder="Takes" class="col-span-1 w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50" />
            <div class="col-span-1">
                <input type="text" name="roles[${roleIndex}][member_name]" list="members" placeholder="Sprecher" class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:border-[#8B0116] dark:focus:border-[#FF6B81] focus:ring focus:ring-[#8B0116] dark:focus:ring-[#FF6B81] focus:ring-opacity-50" />
                <input type="hidden" name="roles[${roleIndex}][member_id]" />
                <div class="text-xs text-gray-500 mt-1 previous-speaker"></div>
            </div>
            <button type="button" class="col-span-1 text-red-600" aria-label="Remove">&times;</button>
        `;
        bindRoleRow(wrapper);
        container.appendChild(wrapper);
        roleIndex++;
    }

    document.getElementById('add_role')?.addEventListener('click', addRole);
    document.querySelectorAll('#roles_list .role-row').forEach(bindRoleRow);
}
