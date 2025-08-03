<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Seitenaufrufe
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                <canvas id="visitsChart"></canvas>
            </div>
        </div>
    </div>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                <h3 class="font-semibold text-lg mb-4">Seitenaufrufe nach Nutzer</h3>
                <canvas id="userVisitsChart"></canvas>
            </div>
        </div>
    </div>

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

        const datasets = users.map((user, index) => {
            const color = `hsl(${index * 360 / users.length}, 70%, 50%)`;
            const data = paths.map(path => {
                const record = userVisitData.find(v => v.path === path && v.user.name === user);
                return record ? record.total : 0;
            });
            return {
                label: user,
                data,
                backgroundColor: color,
            };
        });

        const ctx2 = document.getElementById('userVisitsChart').getContext('2d');
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: paths,
                datasets: datasets,
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    </script>
</x-app-layout>
