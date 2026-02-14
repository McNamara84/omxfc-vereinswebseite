<x-card>
    <x-header :title="$heading" separator useH1 data-testid="page-title" />
    <form action="{{ $formAction }}" method="POST" id="request-form">
        @csrf
        @if($formMethod !== 'POST')
            @method($formMethod)
        @endif

        @php
            $selectedSeries = old('series', optional($requestModel)->series ?? ($types[0]->value ?? ''));
            $selectedBookNumber = old('book_number', optional($requestModel)->book_number ?? null);
            $selectedCondition = old('condition', optional($requestModel)->condition ?? 'Z0');

            $seriesOptions = collect($types)->map(fn($t) => ['id' => $t->value, 'name' => $t->value])->toArray();
            $bookOptions = $books->map(fn($b) => ['id' => $b->roman_number, 'name' => $b->roman_number . ' - ' . $b->title])->toArray();
            $conditionOptions = [
                ['id' => 'Z0', 'name' => 'Z0 - Druckfrisch (Top Zustand)'],
                ['id' => 'Z0-1', 'name' => 'Z0-1 - Druckfrisch, minimale Mängel'],
                ['id' => 'Z1', 'name' => 'Z1 - Sehr gut, Kleinstfehler'],
                ['id' => 'Z1-2', 'name' => 'Z1-2 - Sehr gut, leichte Gebrauchsspuren'],
                ['id' => 'Z2', 'name' => 'Z2 - Gut, kleine Mängel'],
                ['id' => 'Z2-3', 'name' => 'Z2-3 - Gut, stärker gebraucht'],
                ['id' => 'Z3', 'name' => 'Z3 - Deutlich gebraucht'],
                ['id' => 'Z3-4', 'name' => 'Z3-4 - Sehr stark gebraucht'],
                ['id' => 'Z4', 'name' => 'Z4 - Sehr schlecht erhalten'],
            ];

            $booksBySeries = $books->groupBy(fn($b) => $b->type->value)
                ->map(fn($group) => $group->pluck('roman_number')->map(fn($n) => (string) $n)->values());
        @endphp

        <div class="grid gap-6 md:grid-cols-2">
            <div class="md:col-span-1 space-y-4">
                <x-select
                    id="series-select"
                    name="series"
                    label="Serie"
                    :options="$seriesOptions"
                    :value="$selectedSeries"
                />

                <x-select
                    id="book-select"
                    name="book_number"
                    label="Roman"
                    :options="$bookOptions"
                    :value="$selectedBookNumber"
                />

                <x-select
                    id="condition-select"
                    name="condition"
                    label="Zustand bis einschließlich"
                    :options="$conditionOptions"
                    :value="$selectedCondition"
                />
            </div>

            <div class="md:col-span-1 flex items-center">
                <p class="text-sm text-base-content leading-relaxed">Beschreibe so genau wie möglich, welchen Roman du suchst und in welchem Zustand er mindestens sein soll. Mit präzisen Angaben erhöhst du die Chancen auf einen passenden Tausch.</p>
            </div>
        </div>

        <div class="mt-8 flex flex-wrap gap-3">
            <x-button :label="$submitLabel" type="submit" class="btn-primary" icon="o-check" />
            <x-button label="Abbrechen" link="{{ route('romantausch.index') }}" class="btn-ghost" />
        </div>
    </form>
</x-card>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const seriesSelect = document.getElementById('series-select');
            const bookSelect = document.getElementById('book-select');
            const booksBySeries = @json($booksBySeries);

            function filterBooks() {
                const series = seriesSelect.value;
                const allowedNumbers = new Set(booksBySeries[series] || []);
                let firstVisibleIndex = -1;
                let hasVisibleSelection = false;
                Array.from(bookSelect.options).forEach((option, idx) => {
                    if (!option.value) return;
                    const match = allowedNumbers.has(String(option.value));
                    option.hidden = !match;
                    option.disabled = !match;
                    if (match) {
                        if (firstVisibleIndex === -1) {
                            firstVisibleIndex = idx;
                        }
                        if (option.selected) {
                            hasVisibleSelection = true;
                        }
                    }
                });
                if (!hasVisibleSelection && firstVisibleIndex !== -1) {
                    bookSelect.selectedIndex = firstVisibleIndex;
                }
            }

            filterBooks();
            seriesSelect.addEventListener('change', filterBooks);
        });
    </script>
@endpush
