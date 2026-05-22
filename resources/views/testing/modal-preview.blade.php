@php
    $maryModals = [
        ['id' => 'preview-todo-delete', 'alias' => 'modal', 'label' => 'Challenge löschen', 'source' => 'resources/views/livewire/todo-show.blade.php', 'family' => 'Livewire x-modal'],
        ['id' => 'preview-three-d-model-delete', 'alias' => 'modal', 'label' => '3D-Modell löschen', 'source' => 'resources/views/livewire/three-d-model-show.blade.php', 'family' => 'Livewire x-modal'],
        ['id' => 'preview-hoerbuch-delete', 'alias' => 'modal', 'label' => 'Hörbuchfolge löschen', 'source' => 'resources/views/livewire/hoerbuch-show.blade.php', 'family' => 'Livewire x-modal'],
        ['id' => 'preview-api-token-display', 'alias' => 'modal', 'label' => 'API Token anzeigen', 'source' => 'resources/views/api/api-token-manager.blade.php', 'family' => 'Jetstream x-modal'],
        ['id' => 'preview-api-token-permissions', 'alias' => 'modal', 'label' => 'API Token Rechte', 'source' => 'resources/views/api/api-token-manager.blade.php', 'family' => 'Jetstream x-modal'],
        ['id' => 'preview-api-token-delete', 'alias' => 'modal', 'label' => 'API Token löschen', 'source' => 'resources/views/api/api-token-manager.blade.php', 'family' => 'Jetstream x-modal'],
        ['id' => 'preview-review-delete', 'alias' => 'modal', 'label' => 'Rezension löschen', 'source' => 'resources/views/livewire/rezension-show.blade.php', 'family' => 'Livewire x-modal'],
        ['id' => 'preview-confirm-password', 'alias' => 'modal', 'label' => 'Passwort bestätigen', 'source' => 'resources/views/components/confirms-password.blade.php', 'family' => 'Jetstream x-modal'],
        ['id' => 'preview-reward-modal', 'alias' => 'modal', 'label' => 'Belohnung bearbeiten', 'source' => 'resources/views/livewire/belohnungen-admin.blade.php', 'family' => 'Livewire x-modal'],
        ['id' => 'preview-rule-modal', 'alias' => 'modal', 'label' => 'Vergaberegel bearbeiten', 'source' => 'resources/views/livewire/belohnungen-admin.blade.php', 'family' => 'Livewire x-modal'],
        ['id' => 'preview-review-special-offer', 'alias' => 'modal', 'label' => 'Review-Sonderaktion bearbeiten', 'source' => 'resources/views/livewire/belohnungen-admin.blade.php', 'family' => 'Livewire x-modal'],
        ['id' => 'preview-maddraxiversum-special-offer', 'alias' => 'modal', 'label' => 'Maddraxiversum-Sonderaktion bearbeiten', 'source' => 'resources/views/livewire/belohnungen-admin.blade.php', 'family' => 'Livewire x-modal'],
        ['id' => 'preview-romantausch-special-offer', 'alias' => 'modal', 'label' => 'Romantausch-Sonderaktion bearbeiten', 'source' => 'resources/views/livewire/belohnungen-admin.blade.php', 'family' => 'Livewire x-modal'],
        ['id' => 'preview-download-modal', 'alias' => 'modal', 'label' => 'Download bearbeiten', 'source' => 'resources/views/livewire/belohnungen-admin.blade.php', 'family' => 'Livewire x-modal'],
        ['id' => 'preview-kompendium-edit', 'alias' => 'modal', 'label' => 'Roman bearbeiten', 'source' => 'resources/views/livewire/kompendium-admin-dashboard.blade.php', 'family' => 'Livewire x-modal'],
        ['id' => 'preview-mission-modal', 'alias' => 'modal', 'label' => 'Mission-Info', 'source' => 'resources/views/maddraxiversum/index.blade.php', 'family' => 'Seitenweites x-modal'],
        ['id' => 'preview-logout-sessions', 'alias' => 'mary-modal', 'label' => 'Alle anderen Browser-Sitzungen beenden', 'source' => 'resources/views/profile/logout-other-browser-sessions-form.blade.php', 'family' => 'Jetstream x-mary-modal'],
        ['id' => 'preview-delete-user', 'alias' => 'mary-modal', 'label' => 'Mitgliedschaft kündigen', 'source' => 'resources/views/profile/delete-user-form.blade.php', 'family' => 'Jetstream x-mary-modal'],
        ['id' => 'preview-delete-team', 'alias' => 'mary-modal', 'label' => 'Arbeitsgruppe löschen', 'source' => 'resources/views/teams/delete-team-form.blade.php', 'family' => 'Jetstream x-mary-modal'],
        ['id' => 'preview-team-role', 'alias' => 'mary-modal', 'label' => 'Rolle verwalten', 'source' => 'resources/views/teams/team-member-manager.blade.php', 'family' => 'Jetstream x-mary-modal'],
        ['id' => 'preview-leave-team', 'alias' => 'mary-modal', 'label' => 'Arbeitsgruppe verlassen', 'source' => 'resources/views/teams/team-member-manager.blade.php', 'family' => 'Jetstream x-mary-modal'],
        ['id' => 'preview-remove-member', 'alias' => 'mary-modal', 'label' => 'Mitglied entfernen', 'source' => 'resources/views/teams/team-member-manager.blade.php', 'family' => 'Jetstream x-mary-modal'],
        ['id' => 'preview-profile-photo', 'alias' => 'mary-modal', 'label' => 'Profilfoto', 'source' => 'resources/views/profile/view.blade.php', 'family' => 'showModal x-mary-modal'],
        ['id' => 'preview-badge-modal', 'alias' => 'mary-modal', 'label' => 'Badge-Ansicht', 'source' => 'resources/views/profile/view.blade.php', 'family' => 'showModal x-mary-modal'],
        ['id' => 'preview-reject-applicant', 'alias' => 'mary-modal', 'label' => 'Antrag ablehnen', 'source' => 'resources/views/dashboard/partials/applicants-panel.blade.php', 'family' => 'showModal x-mary-modal'],
        ['id' => 'preview-fanfiction-delete', 'alias' => 'mary-modal', 'label' => 'Fanfiction löschen', 'source' => 'resources/views/admin/fanfiction/index.blade.php', 'family' => 'showModal x-mary-modal'],
    ];

    $customOverlays = [
        ['id' => 'preview-confirm-delete', 'label' => 'Bestätigung aus confirm-delete', 'source' => 'resources/views/components/confirm-delete.blade.php', 'family' => 'Alpine Overlay', 'backdropClass' => 'bg-black/50'],
        ['id' => 'preview-kassenbuch-payment', 'label' => 'Kassenbuch Zahlungsdaten', 'source' => 'resources/views/livewire/kassenbuch-index.blade.php', 'family' => 'Livewire Overlay', 'backdropClass' => 'bg-black/50'],
        ['id' => 'preview-kassenbuch-create', 'label' => 'Kassenbucheintrag hinzufügen', 'source' => 'resources/views/livewire/kassenbuch-index.blade.php', 'family' => 'Livewire Overlay', 'backdropClass' => 'bg-black/50'],
        ['id' => 'preview-kassenbuch-request-edit', 'label' => 'Bearbeitung anfragen', 'source' => 'resources/views/livewire/kassenbuch-index.blade.php', 'family' => 'Livewire Overlay', 'backdropClass' => 'bg-black/50'],
        ['id' => 'preview-kassenbuch-edit', 'label' => 'Kassenbucheintrag bearbeiten', 'source' => 'resources/views/livewire/kassenbuch-index.blade.php', 'family' => 'Livewire Overlay', 'backdropClass' => 'bg-black/50'],
        ['id' => 'preview-kassenbuch-reject-request', 'label' => 'Bearbeitungsanfrage ablehnen', 'source' => 'resources/views/livewire/kassenbuch-index.blade.php', 'family' => 'Livewire Overlay', 'backdropClass' => 'bg-black/50'],
        ['id' => 'preview-romantausch-photo', 'label' => 'Romantausch Fotoansicht', 'source' => 'resources/views/livewire/romantausch-index.blade.php', 'family' => 'Galerie Overlay', 'backdropClass' => 'bg-black/70 backdrop-blur-sm'],
        ['id' => 'preview-newsletter-confirm', 'label' => 'Newsletter Sende-Bestätigung', 'source' => 'resources/views/newsletter/versenden.blade.php', 'family' => 'Alpine Overlay', 'backdropClass' => 'bg-base-300/75'],
        ['id' => 'preview-chronik-lightbox', 'label' => 'Chronik Lightbox', 'source' => 'resources/views/pages/chronik.blade.php', 'family' => 'Lightbox Overlay', 'backdropClass' => 'bg-black/80 backdrop-blur-sm'],
    ];

    $allModals = array_merge($maryModals, $customOverlays);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="caramellatte">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Modal-Vorschau</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-base-200 text-base-content antialiased">
    <main data-preview-page class="mx-auto flex min-h-screen w-full max-w-7xl flex-col gap-8 px-4 py-6 sm:px-6 lg:px-8 lg:py-10">
        <header class="rounded-4xl border border-base-300 bg-base-100 px-6 py-8 shadow-sm sm:px-8">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl space-y-3">
                    <span class="badge badge-outline badge-lg">Nur lokal und im Test</span>
                    <h1 class="font-display text-4xl font-semibold tracking-tight">Modal-Vorschau</h1>
                    <p class="max-w-2xl text-base leading-7 text-base-content/70">
                        Diese Seite bündelt alle Modal-Familien, die aktuell im Projekt verwendet werden. Sie dient als visuelle Regression für die globale Backdrop-Korrektur und als Screenshot-Ziel für den Docker-Lauf.
                    </p>
                </div>
                <div class="rounded-3xl border border-base-300 bg-base-200 px-5 py-4 text-sm text-base-content/70">
                    <p class="font-semibold text-base-content">Abgedeckte Vorschauen</p>
                    <p>{{ count($maryModals) }} MaryUI/daisyUI-Dialoge und {{ count($customOverlays) }} eigene Overlays</p>
                </div>
            </div>
        </header>

        <section class="grid gap-8 xl:grid-cols-[minmax(0,1.2fr)_minmax(22rem,0.8fr)]">
            <div class="space-y-8">
                <section class="space-y-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-2xl font-semibold">MaryUI und daisyUI</h2>
                            <p class="text-sm text-base-content/65">x-modal und x-mary-modal, jeweils an den realen Einsatzstellen gespiegelt.</p>
                        </div>
                        <span class="badge badge-neutral badge-outline">{{ count($maryModals) }} Stück</span>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($maryModals as $modal)
                            <button
                                id="open-{{ $modal['id'] }}"
                                type="button"
                                data-modal-trigger
                                data-modal-target="{{ $modal['id'] }}"
                                onclick="window.__omxfcOpenPreviewModal('{{ $modal['id'] }}')"
                                class="group rounded-[1.75rem] border border-base-300 bg-base-100 p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md"
                            >
                                <div class="flex items-center justify-between gap-3 text-xs uppercase tracking-[0.18em] text-base-content/45">
                                    <span>{{ $modal['family'] }}</span>
                                    <span>{{ $modal['alias'] }}</span>
                                </div>
                                <p class="mt-4 text-lg font-semibold text-base-content">{{ $modal['label'] }}</p>
                                <p class="mt-2 text-sm leading-6 text-base-content/65">{{ $modal['source'] }}</p>
                            </button>
                        @endforeach
                    </div>
                </section>

                <section class="space-y-4">
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h2 class="text-2xl font-semibold">Eigene Overlays</h2>
                            <p class="text-sm text-base-content/65">Kassenbuch, Chronik, Romantausch und weitere projektinterne Dialoge ohne Mary-Komponente.</p>
                        </div>
                        <span class="badge badge-neutral badge-outline">{{ count($customOverlays) }} Stück</span>
                    </div>
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @foreach ($customOverlays as $modal)
                            <button
                                id="open-{{ $modal['id'] }}"
                                type="button"
                                data-modal-trigger
                                data-modal-target="{{ $modal['id'] }}"
                                onclick="window.__omxfcOpenPreviewModal('{{ $modal['id'] }}')"
                                class="group rounded-[1.75rem] border border-base-300 bg-base-100 p-5 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md"
                            >
                                <div class="flex items-center justify-between gap-3 text-xs uppercase tracking-[0.18em] text-base-content/45">
                                    <span>{{ $modal['family'] }}</span>
                                    <span>overlay</span>
                                </div>
                                <p class="mt-4 text-lg font-semibold text-base-content">{{ $modal['label'] }}</p>
                                <p class="mt-2 text-sm leading-6 text-base-content/65">{{ $modal['source'] }}</p>
                            </button>
                        @endforeach
                    </div>
                </section>
            </div>

            <aside class="rounded-4xl border border-base-300 bg-base-100 p-6 shadow-sm lg:p-7">
                <h2 class="text-xl font-semibold">Prüfziel</h2>
                <div class="mt-4 space-y-4 text-sm leading-7 text-base-content/70">
                    <p>Alle Vorschauen verwenden dieselbe Backdrop-Prüfung wie die Browser-Tests. Sobald ein Modal geöffnet wird, darf der Hintergrund nicht transparent bleiben.</p>
                    <p>Die Screenshots aus dem Docker-Lauf werden aus genau dieser Seite erzeugt. Neue Modals sollten deshalb hier ergänzt werden, damit der visuelle Regressionstest vollständig bleibt.</p>
                </div>

                <div class="mt-6 rounded-3xl border border-dashed border-base-300 bg-base-200 p-5 text-sm text-base-content/70">
                    <p class="font-semibold text-base-content">Aktuelle Abdeckung</p>
                    <ul class="mt-3 space-y-2">
                        @foreach ($allModals as $modal)
                            <li class="flex items-start justify-between gap-4 border-b border-base-300/60 pb-2 last:border-b-0 last:pb-0">
                                <span class="font-medium text-base-content">{{ $modal['label'] }}</span>
                                <span class="text-right text-xs uppercase tracking-[0.18em] text-base-content/45">{{ $modal['family'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </aside>
        </section>
    </main>

    @foreach ($maryModals as $modal)
        <dialog id="{{ $modal['id'] }}" class="modal" data-preview-modal data-modal-family="{{ $modal['family'] }}">
            <div class="modal-box">
                <form method="dialog" tabindex="-1">
                    <button type="submit" class="btn btn-circle btn-ghost btn-sm absolute inset-e-2 top-2 z-999" tabindex="-1">✕</button>
                </form>

                <div class="mb-5 space-y-3">
                    <span class="badge badge-outline">{{ $modal['alias'] }}</span>
                    <h2 class="text-xl font-semibold">{{ $modal['label'] }}</h2>
                    <p class="text-sm leading-6 text-base-content/75">Diese Vorschau spiegelt den Dialog aus {{ $modal['source'] }}. Entscheidend ist der abgedunkelte Hintergrund hinter dem Dialog.</p>
                </div>

                <div class="space-y-4">
                    <div class="rounded-3xl bg-base-200 p-4 text-sm text-base-content/70">
                        <p class="font-semibold text-base-content">Warum diese Variante wichtig ist</p>
                        <p>Die globale Korrektur muss Livewire-gesteuerte und direkt per ID geöffnete Mary-DOM-Strukturen gleichermaßen decken.</p>
                    </div>
                </div>

                <div class="modal-action">
                    <form method="dialog">
                        <button type="submit" class="btn btn-primary">Schließen</button>
                    </form>
                </div>
            </div>

            <form class="modal-backdrop" method="dialog">
                <button type="submit">close</button>
            </form>
        </dialog>
    @endforeach

    @foreach ($customOverlays as $modal)
        <div
            id="{{ $modal['id'] }}"
            data-preview-overlay
            data-modal-family="{{ $modal['family'] }}"
            data-open="false"
            class="{{ $modal['backdropClass'] }} fixed inset-0 z-50 hidden items-center justify-center px-4 py-8"
        >
            <div class="relative w-full max-w-2xl rounded-4xl border border-white/15 bg-base-100 p-6 shadow-2xl sm:p-8">
                <button type="button" class="btn btn-circle btn-ghost absolute right-4 top-4" aria-label="Schließen" onclick="window.__omxfcClosePreviewModals()">✕</button>
                <div class="space-y-4">
                    <span class="badge badge-outline">{{ $modal['family'] }}</span>
                    <h2 class="text-2xl font-semibold">{{ $modal['label'] }}</h2>
                    <p class="text-sm leading-6 text-base-content/75">Diese Vorschau deckt die Overlay-Struktur aus {{ $modal['source'] }} ab. Für die Regression zählt hier vor allem, dass der Overlay-Hintergrund sichtbar abdunkelt.</p>
                    <div class="rounded-3xl bg-base-200 p-4 text-sm text-base-content/70">
                        <p class="font-semibold text-base-content">Sichtprüfung</p>
                        <p>Beim Öffnen muss die Seite unter dem Overlay deutlich abgedunkelt bleiben. Genau diese Zustände werden in Chromium als Screenshot abgelegt.</p>
                    </div>
                </div>
                <div class="mt-6 flex justify-end">
                    <button type="button" class="btn btn-primary" onclick="window.__omxfcClosePreviewModals()">Schließen</button>
                </div>
            </div>
        </div>
    @endforeach

    <script>
        (() => {
            const colorParser = document.createElement('canvas').getContext('2d');

            function normalizeColor(color) {
                if (!colorParser || !color) {
                    return 'transparent';
                }

                colorParser.fillStyle = '#000';
                colorParser.fillStyle = color;

                return colorParser.fillStyle;
            }

            function alphaFromColor(color) {
                const normalized = normalizeColor(color);

                if (!normalized || normalized === 'transparent') {
                    return 0;
                }

                const rgbaMatch = normalized.match(/^rgba\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*,\s*([0-9.]+)\s*\)$/i);

                if (rgbaMatch) {
                    return Number(rgbaMatch[1]);
                }

                return 1;
            }

            window.__omxfcClosePreviewModals = () => {
                document.querySelectorAll('dialog[data-preview-modal][open]').forEach((dialog) => {
                    dialog.close();
                });

                document.querySelectorAll('[data-preview-overlay]').forEach((overlay) => {
                    overlay.classList.add('hidden');
                    overlay.dataset.open = 'false';
                });
            };

            window.__omxfcOpenPreviewModal = (id) => {
                window.__omxfcClosePreviewModals();

                const target = document.getElementById(id);

                if (!target) {
                    return;
                }

                if (target instanceof HTMLDialogElement) {
                    target.showModal();

                    return;
                }

                target.classList.remove('hidden');
                target.dataset.open = 'true';
            };

            window.__omxfcPreviewBackdropAlpha = (id) => {
                const target = document.getElementById(id);

                if (!target) {
                    return 0;
                }

                const alphas = [alphaFromColor(window.getComputedStyle(target).backgroundColor)];

                if (target instanceof HTMLDialogElement) {
                    alphas.push(alphaFromColor(window.getComputedStyle(target, '::backdrop').backgroundColor));

                    const maryBackdrop = target.querySelector('.modal-backdrop');

                    if (maryBackdrop) {
                        alphas.push(alphaFromColor(window.getComputedStyle(maryBackdrop).backgroundColor));
                    }
                }

                return Math.max(...alphas);
            };
        })();
    </script>
</body>
</html>