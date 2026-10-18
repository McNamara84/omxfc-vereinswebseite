@props(['items' => []])
@if(count($items) > 1)
    <nav aria-label="Brotkrumen" {{ $attributes->class(['omxfc-breadcrumbs mx-auto w-full max-w-7xl px-4 py-3 text-sm sm:px-6 lg:px-8']) }}>
        <ol class="flex flex-wrap items-center gap-x-2 gap-y-1">
            @foreach($items as $item)
                <li @class(['flex min-w-0 items-center gap-2', 'hidden sm:flex' => ! $loop->first && ! $loop->last && ! isset($item['link'])])>
                    @unless($loop->first)<span aria-hidden="true" class="text-base-content/75">/</span>@endunless
                    @if(isset($item['link']) && ! $loop->last)
                        <a href="{{ $item['link'] }}" wire:navigate class="inline-flex min-h-8 items-center rounded-sm text-base-content/80 underline-offset-4 hover:underline focus-visible:outline-2 focus-visible:outline-primary">{{ $item['label'] }}</a>
                    @else
                        <span @if($loop->last) aria-current="page" @endif class="break-words {{ $loop->last ? 'font-semibold' : 'text-base-content/80' }}">{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
