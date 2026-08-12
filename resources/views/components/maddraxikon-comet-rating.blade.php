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
                $nucleusGradientId = 'comet-nucleus-'.$safeInstance.'-'.$position;
                $tailGradientId = 'comet-tail-'.$safeInstance.'-'.$position;
                $nucleusGlowId = 'comet-glow-'.$safeInstance.'-'.$position;
            @endphp
            <svg
                viewBox="0 0 40 32"
                class="maddraxikon-comet {{ $filled ? 'maddraxikon-comet--filled' : 'maddraxikon-comet--empty' }}"
                data-comet-position="{{ $position }}"
                data-comet-filled="{{ $filled ? 'true' : 'false' }}"
                focusable="false"
            >
                @if($filled)
                    <defs>
                        <linearGradient id="{{ $tailGradientId }}" x1="2.5" y1="28.5" x2="26" y2="12" gradientUnits="userSpaceOnUse">
                            <stop offset="0" stop-color="#dc2626" stop-opacity="0.82" />
                            <stop offset="0.3" stop-color="#f97316" />
                            <stop offset="0.68" stop-color="#fbbf24" />
                            <stop offset="1" stop-color="#fff7ed" />
                        </linearGradient>
                        <radialGradient id="{{ $nucleusGradientId }}" cx="0" cy="0" r="1" gradientUnits="userSpaceOnUse" gradientTransform="translate(27.4 7.4) rotate(45) scale(9.2)">
                            <stop offset="0" stop-color="#f0fdf4" />
                            <stop offset="0.3" stop-color="#86efac" />
                            <stop offset="0.68" stop-color="#22c55e" />
                            <stop offset="1" stop-color="#15803d" />
                        </radialGradient>
                        <filter id="{{ $nucleusGlowId }}" x="-70%" y="-70%" width="240%" height="240%" color-interpolation-filters="sRGB">
                            <feGaussianBlur stdDeviation="1.65" />
                        </filter>
                    </defs>
                @endif

                @if($filled)
                    <g class="maddraxikon-comet__tail">
                        <path
                            d="M2.5 28.5C7.2 21.4 13.4 16 24.1 11.5C21.8 15.1 22.5 17.9 25.9 19.2C21.2 20.4 18.1 22.1 15.2 24.3C15.4 21.8 14.4 20.1 12.9 19.9C10.4 23.8 6.6 27 2.5 28.5Z"
                            fill="url(#{{ $tailGradientId }})"
                        />
                        <path
                            d="M8.2 25.7C12.1 20.9 17 16.8 24.3 13.5C22.9 15.7 23.5 17.3 25.2 18.2C20.8 19.4 16 22.7 12.6 25.1C13 23.2 12.5 22 11.5 21.5C10.4 23.1 9.3 24.5 8.2 25.7Z"
                            fill="#fde68a"
                            opacity="0.95"
                        />
                        <path
                            d="M14.6 21.6C17.1 18.5 20.5 16.2 24.4 14.5C23.7 16 24.1 17 25 17.7C21.5 18.4 18.1 20.3 15.4 22.2Z"
                            fill="#fff7ed"
                            opacity="0.92"
                        />
                        <path
                            d="M5.4 25.9C8.2 23.6 10.5 21.2 12.8 17.9"
                            fill="none"
                            stroke="#fb923c"
                            stroke-width="1.05"
                            stroke-linecap="round"
                            opacity="0.85"
                        />
                    </g>
                    <circle
                        class="maddraxikon-comet__nucleus-glow"
                        cx="29.4"
                        cy="9.7"
                        r="7.2"
                        fill="#4ade80"
                        opacity="0.58"
                        filter="url(#{{ $nucleusGlowId }})"
                    />
                    <circle
                        class="maddraxikon-comet__nucleus"
                        cx="29.4"
                        cy="9.7"
                        r="6.35"
                        fill="url(#{{ $nucleusGradientId }})"
                        stroke="#dcfce7"
                        stroke-width="0.85"
                    />
                    <ellipse cx="27.1" cy="7.35" rx="1.85" ry="1.15" fill="#f0fdf4" opacity="0.9" />
                @else
                    <path
                        d="M2.5 28.5C7.2 21.4 13.4 16 24.1 11.5C21.8 15.1 22.5 17.9 25.9 19.2C21.2 20.4 18.1 22.1 15.2 24.3C15.4 21.8 14.4 20.1 12.9 19.9C10.4 23.8 6.6 27 2.5 28.5Z"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.45"
                        stroke-linejoin="round"
                    />
                    <path
                        d="M8.2 25.7C12.1 20.9 17 16.8 24.3 13.5"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="0.9"
                        stroke-linecap="round"
                    />
                    <circle
                        cx="29.4"
                        cy="9.7"
                        r="6.35"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.7"
                    />
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
