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
            $seriesError = $errors->first('series');
            $bookNumberError = $errors->first('book_number');
            $conditionError = $errors->first('condition');
        @endphp

        <div class="grid gap-6 md:grid-cols-2">
            <div class="md:col-span-1 space-y-4">
                <div>
                    <label for="series-select" class="label label-text">Serie</label>
                    <select
                        name="series"
                        id="series-select"
                        @class([
                            'select select-bordered w-full',
                            'select-error' => $seriesError,
                        ])
                        @if($seriesError)
                            aria-invalid="true"
                            aria-describedby="series-error"
                        @endif
                    >
                        @foreach($types as $type)
                            <option value="{{ $type->value }}" @selected($selectedSeries === $type->value)>{{ $type->value }}</option>
                        @endforeach
                    </select>
                    @error('series')
                        <p id="series-error" class="text-sm text-error mt-1" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="book-select" class="label label-text">Roman</label>
                    <select
                        name="book_number"
                        id="book-select"
                        @class([
                            'select select-bordered w-full',
                            'select-error' => $bookNumberError,
                        ])
                        @if($bookNumberError)
                            aria-invalid="true"
                            aria-describedby="book_number-error"
                        @endif
                    >
                        @foreach($books as $book)
                            <option
                                value="{{ $book->roman_number }}"
                                data-series="{{ $book->type->value }}"
                                @selected((string) $selectedBookNumber === (string) $book->roman_number)
                            >
                                {{ $book->roman_number }} - {{ $book->title }}
                            </option>
                        @endforeach
                    </select>
                    @error('book_number')
                        <p id="book_number-error" class="text-sm text-error mt-1" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="condition-select" class="label label-text">Zustand bis einschließlich</label>
                    <select
                        name="condition"
                        id="condition-select"
                        @class([
                            'select select-bordered w-full',
                            'select-error' => $conditionError,
                        ])
                        @if($conditionError)
                            aria-invalid="true"
                            aria-describedby="condition-error"
                        @endif
                    >
                        <option value="Z0" @selected($selectedCondition === 'Z0')>Z0 - Druckfrisch (Top Zustand)</option>
                        <option value="Z0-1" @selected($selectedCondition === 'Z0-1')>Z0-1 - Druckfrisch, minimale Mängel</option>
                        <option value="Z1" @selected($selectedCondition === 'Z1')>Z1 - Sehr gut, Kleinstfehler</option>
                        <option value="Z1-2" @selected($selectedCondition === 'Z1-2')>Z1-2 - Sehr gut, leichte Gebrauchsspuren</option>
                        <option value="Z2" @selected($selectedCondition === 'Z2')>Z2 - Gut, kleine Mängel</option>
                        <option value="Z2-3" @selected($selectedCondition === 'Z2-3')>Z2-3 - Gut, stärker gebraucht</option>
                        <option value="Z3" @selected($selectedCondition === 'Z3')>Z3 - Deutlich gebraucht</option>
                        <option value="Z3-4" @selected($selectedCondition === 'Z3-4')>Z3-4 - Sehr stark gebraucht</option>
                        <option value="Z4" @selected($selectedCondition === 'Z4')>Z4 - Sehr schlecht erhalten</option>
                    </select>
                    @error('condition')
                        <p id="condition-error" class="text-sm text-error mt-1" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="md:col-span-1 flex items-center">
                <p class="text-sm text-base-content/60 leading-relaxed">Beschreibe so genau wie möglich, welchen Roman du suchst und in welchem Zustand er mindestens sein soll. Mit präzisen Angaben erhöhst du die Chancen auf einen passenden Tausch.</p>
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

            function filterBooks() {
                const series = seriesSelect.value;
                let firstVisibleIndex = -1;
                let hasVisibleSelection = false;
                Array.from(bookSelect.options).forEach((option, idx) => {
                    const match = option.dataset.series === series;
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
