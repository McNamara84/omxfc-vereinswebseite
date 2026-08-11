@props([
    'rating',
    'pageTitle' => null,
    'instance' => 'rating',
])

@php
    $value = max(1, min(5, (int) $rating));
    $safeInstance = preg_replace('/[^A-Za-z0-9_-]/', '-', (string) $instance) ?: 'rating';
    $url = is_string($pageTitle) && trim($pageTitle) !== ''
        ? rtrim((string) config('maddraxikon.base_url'), '/')
            .'/index.php?title='.rawurlencode(str_replace(' ', '_', trim($pageTitle)))
        : null;
@endphp

<div
    {{ $attributes->class(['maddraxikon-comet-rating mt-1.5 mb-1']) }}
    data-testid="maddraxikon-comet-rating"
    data-rating="{{ $value }}"
>
    <span class="maddraxikon-comet-rating__label">Bewertung im Maddraxikon</span>

    @if($url)
        <a
            href="{{ $url }}"
            target="_blank"
            rel="noopener noreferrer"
            class="maddraxikon-comet-rating__link"
            aria-label="{{ $value }} von 5 Kometen – Romanseite im Maddraxikon öffnen"
        >
    @endif

    <span class="maddraxikon-comet-rating__comets" aria-hidden="true">
        @foreach(range(1, 5) as $position)
            @php
                $filled = $position <= $value;
                $gradientId = 'comet-gradient-'.$safeInstance.'-'.$position;
            @endphp
            <svg
                viewBox="0 0 32 32"
                class="maddraxikon-comet {{ $filled ? 'maddraxikon-comet--filled' : 'maddraxikon-comet--empty' }}"
                data-comet-position="{{ $position }}"
                data-comet-filled="{{ $filled ? 'true' : 'false' }}"
                focusable="false"
            >
                @if($filled)
                    <defs>
                        <linearGradient id="{{ $gradientId }}" x1="2" y1="28" x2="28" y2="4" gradientUnits="userSpaceOnUse">
                            <stop offset="0" stop-color="#15803d" />
                            <stop offset="0.52" stop-color="#22c55e" />
                            <stop offset="1" stop-color="#bbf7d0" />
                        </linearGradient>
                    </defs>
                @endif

                <path
                    d="M3.5 26.5C9.8 24.3 11.8 19.2 15.5 15.2"
                    fill="none"
                    stroke="{{ $filled ? 'url(#'.$gradientId.')' : 'currentColor' }}"
                    stroke-width="3.2"
                    stroke-linecap="round"
                />
                <path
                    d="M2.8 20.3C8.7 19.4 11.4 15.8 15.9 12.7"
                    fill="none"
                    stroke="{{ $filled ? 'url(#'.$gradientId.')' : 'currentColor' }}"
                    stroke-width="1.8"
                    stroke-linecap="round"
                />
                <circle
                    cx="21.5"
                    cy="10.5"
                    r="6.1"
                    fill="{{ $filled ? 'url(#'.$gradientId.')' : 'none' }}"
                    stroke="{{ $filled ? '#dcfce7' : 'currentColor' }}"
                    stroke-width="{{ $filled ? '0.8' : '1.7' }}"
                />
                @if($filled)
                    <ellipse cx="19.4" cy="8.4" rx="1.7" ry="1.1" fill="#f0fdf4" opacity="0.8" />
                @endif
            </svg>
        @endforeach
    </span>

    <span class="maddraxikon-comet-rating__value" aria-hidden="true">{{ $value }}/5</span>
    <span class="sr-only">{{ $value }} von 5 Kometen</span>

    @if($url)
        </a>
    @endif
</div>
