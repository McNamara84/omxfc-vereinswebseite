{{-- Flash-zu-Toast-Bridge: Wandelt Controller-Flash-Messages in maryUI-Toasts um.
     Controller nutzen ->with('status'/'success'/'error'/'warning'/'info', '...'), diese Partial
     konvertiert sie beim Seitenaufruf automatisch in window.toast() Aufrufe. --}}
@if(session('status') || session('success') || session('error') || session('warning') || session('info'))
<script>
    function __omxfcFlashToast() {
        if (typeof window.toast !== 'function') return;
        @if(session('status') || session('success'))
        window.toast({toast: {title: @json(session('status') ?: session('success')), css: 'alert-success', timeout: 3000, position: 'toast-top toast-end', noProgress: false}});
        @endif
        @if(session('error'))
        window.toast({toast: {title: @json(session('error')), css: 'alert-error', timeout: 5000, position: 'toast-top toast-end', noProgress: false}});
        @endif
        @if(session('warning'))
        window.toast({toast: {title: @json(session('warning')), css: 'alert-warning', timeout: 4000, position: 'toast-top toast-end', noProgress: false}});
        @endif
        @if(session('info'))
        window.toast({toast: {title: @json(session('info')), css: 'alert-info', timeout: 3000, position: 'toast-top toast-end', noProgress: false}});
        @endif
    }
    document.addEventListener('DOMContentLoaded', __omxfcFlashToast, { once: true });
    document.addEventListener('livewire:navigated', __omxfcFlashToast, { once: true });
</script>
@endif
