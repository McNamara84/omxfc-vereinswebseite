<x-app-layout>
    <x-member-page>
        <h1 class="text-2xl font-semibold text-[#8B0116] dark:text-[#FCA5A5] mb-6">Seitenaufrufe</h1>
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6 mb-8">
            <canvas id="visitsChart"></canvas>
        </div>
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
            <h3 class="font-semibold text-lg mb-4">Seitenaufrufe nach Nutzer</h3>
            <select id="userSelect" class="mb-4 border-gray-300 dark:bg-gray-700 dark:border-gray-600 rounded-md"></select>
            <canvas id="userVisitsChart"></canvas>
        </div>
    </x-member-page>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const visitData = @json($visitData);
        const labels = visitData.map(v => v.path);
        const counts = visitData.map(v => v.total);

        const ctx = document.getElementById('visitsChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Seitenaufrufe',
                    data: counts,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        const userVisitData = @json($userVisitData);
        const paths = [...new Set(userVisitData.map(v => v.path))];
        const users = [...new Set(userVisitData.map(v => v.user.name))];

        const userSelect = document.getElementById('userSelect');
        users.forEach(user => {
            const option = document.createElement('option');
            option.value = user;
            option.textContent = user;
            userSelect.appendChild(option);
        });

        const ctx2 = document.getElementById('userVisitsChart').getContext('2d');
        const userChart = new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: paths,
                datasets: [],
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        function updateUserChart(selectedUser) {
            const data = paths.map(path => {
                const record = userVisitData.find(v => v.path === path && v.user.name === selectedUser);
                return record ? record.total : 0;
            });
            userChart.data.datasets = [{
                label: selectedUser,
                data,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }];
            userChart.update();
        }

        updateUserChart(users[0]);

        userSelect.addEventListener('change', (e) => updateUserChart(e.target.value));
    </script>
</x-app-layout>
