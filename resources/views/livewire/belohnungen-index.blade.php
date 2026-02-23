<div class="py-12">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <x-header title="Belohnungen" separator>
            <x-slot:subtitle>
                Verdient: <x-badge :value="$this->earnedBaxx . ' Baxx'" class="badge-primary" />
                Ausgegeben: <x-badge :value="$this->spentBaxx . ' Baxx'" class="badge-ghost" />
                Verfügbar: <x-badge :value="$this->availableBaxx . ' Baxx'" class="badge-success" />
            </x-slot:subtitle>
        </x-header>

        {{-- Hilfetext --}}
        <x-alert icon="o-information-circle" class="mb-6 alert-info" dismissible>
            <p>
                <strong>So funktioniert das Belohnungssystem:</strong>
                Mit deinen verdienten Baxx kannst du hier Features freischalten. Wähle aus, welche Features du
                nutzen möchtest, und kaufe sie mit deinen Baxx. Einmal freigeschaltete Features bleiben dauerhaft aktiv.
                Baxx verdienst du z.&nbsp;B. durch das Erledigen von Aufgaben, Rezensionen oder Romantausch-Angebote.
            </p>
        </x-alert>

        {{-- Filter --}}
        <div class="flex flex-wrap gap-4 mb-6">
            <x-select
                label="Status"
                wire:model.live="filter"
                :options="[
                    ['id' => 'alle', 'name' => 'Alle'],
                    ['id' => 'freigeschaltet', 'name' => 'Freigeschaltet'],
                    ['id' => 'nicht_freigeschaltet', 'name' => 'Nicht freigeschaltet'],
                ]"
                option-value="id"
                option-label="name"
                class="w-48"
            />

            <x-select
                label="Kategorie"
                wire:model.live="categoryFilter"
                :options="array_merge(
                    [['id' => 'alle', 'name' => 'Alle Kategorien']],
                    array_map(fn($c) => ['id' => $c, 'name' => $c], $this->categories)
                )"
                option-value="id"
                option-label="name"
                class="w-56"
            />
        </div>

        {{-- Rewards nach Kategorie gruppiert --}}
        @forelse($this->rewards as $category => $categoryRewards)
            <h2 class="text-xl font-bold text-primary mt-8 mb-4">{{ $category }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                @foreach($categoryRewards as $reward)
                    <x-card shadow class="{{ $reward['purchased'] ? 'border-l-4 border-success' : '' }}">
                        <div class="flex flex-col gap-3">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <h3 class="text-lg font-semibold text-primary">{{ $reward['title'] }}</h3>
                                    <p class="text-base-content text-sm mt-1">{{ $reward['description'] }}</p>
                                </div>
                                <x-badge
                                    :value="$reward['cost_baxx'] . ' Baxx'"
                                    class="shrink-0 whitespace-nowrap {{ $reward['purchased'] ? 'badge-success' : ($reward['can_afford'] ? 'badge-primary' : 'badge-ghost') }}"
                                />
                            </div>

                            <div class="flex justify-end">
                                @if($reward['purchased'])
                                    <x-badge value="✓ Freigeschaltet" class="badge-success badge-lg" />
                                @elseif($reward['can_afford'])
                                    <x-button
                                        label="Freischalten"
                                        icon="o-lock-open"
                                        class="btn-primary btn-sm"
                                        wire:click="purchase({{ $reward['id'] }})"
                                        wire:confirm="Möchtest du '{{ $reward['title'] }}' für {{ $reward['cost_baxx'] }} Baxx freischalten? Dein verfügbares Guthaben: {{ $this->availableBaxx }} Baxx. Nach dem Kauf: {{ $this->availableBaxx - $reward['cost_baxx'] }} Baxx."
                                    />
                                @else
                                    <x-badge
                                        value="🔒 {{ $reward['missing_baxx'] }} Baxx fehlen"
                                        class="badge-ghost badge-lg"
                                    />
                                @endif
                            </div>
                        </div>
                    </x-card>
                @endforeach
            </div>
        @empty
            <x-alert icon="o-face-frown" class="alert-warning">
                Keine Belohnungen gefunden, die deinen Filtern entsprechen.
            </x-alert>
        @endforelse
    </div>
</div>
