@props(['ids' => []])
@if($errors->any())
    <div role="alert" tabindex="-1" data-ux-error-summary class="mb-4 rounded-xl border border-error bg-error/10 p-4 text-base-content focus-visible:outline-2 focus-visible:outline-error">
        <p class="font-semibold">Bitte korrigiere die markierten Felder.</p>
        <ul class="mt-2 list-inside list-disc space-y-1 text-sm">
            @foreach($errors->getMessages() as $field => $messages)
                <li>
                    @if(isset($ids[$field]))
                        <a href="#{{ $ids[$field] }}" class="underline underline-offset-2" x-data x-on:click.prevent="document.getElementById(@js($ids[$field]))?.focus()">{{ $messages[0] }}</a>
                    @else
                        {{ $messages[0] }}
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
