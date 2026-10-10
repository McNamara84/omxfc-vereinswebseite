<span
    x-cloak
    class="inline-block"
    x-data="{
        open: false,
        timer: null,
        show() { clearTimeout(this.timer); this.open = true },
        hide() {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => {
                if (!this.$el.matches(':hover') &amp;&amp; !this.$refs.myTrigger.matches(':focus')) this.open = false
            }, 300)
        },
        close() { clearTimeout(this.timer); this.open = false },
        destroy() { clearTimeout(this.timer) }
    }"
    @keydown.escape.prevent.stop="close()"
>
    <button
        type="button"
        x-ref="myTrigger"
        aria-label="{{ $trigger->attributes->get('aria-label', __('Hinweis anzeigen')) }}"
        aria-describedby="{{ $uuid }}-content"
        aria-controls="{{ $uuid }}-content"
        aria-expanded="false"
        x-bind:aria-expanded="open.toString()"
        @mouseenter="show()"
        @mouseleave="hide()"
        @focus="show()"
        @blur="hide()"
        @click="show()"
        {{ $trigger->attributes->except(['aria-label'])->class(['inline-flex cursor-pointer rounded focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary']) }}
    >
        {{ $trigger }}
    </button>
    <span
        id="{{ $uuid }}-content"
        role="tooltip"
        x-bind:style="{ display: open ? 'inline-block' : 'none' }"
        x-anchor.{{ $position }}.offset.{{ $offset }}="$refs.myTrigger"
        @mouseenter="show()"
        @mouseleave="hide()"
        {{ $content->attributes->class(['z-50 inline-block max-w-xs whitespace-normal rounded-md border border-base-content/10 bg-base-100 p-3 text-sm shadow-xl']) }}
    >
        {{ $content }}
    </span>
</span>
