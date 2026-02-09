{{--
    Reparatur-Script für maryUI ThemeToggle.

    Das ThemeToggle-Komponent rendert ein Inline-<script>, das
    document.documentElement.setAttribute("class", localStorage.getItem("mary-class")?.replaceAll('"', ''))
    aufruft. Wenn mary-class/mary-theme nicht in localStorage sind (erster Besuch, Tests),
    wird der Wert zu "undefined" (als String), was alle Theme-Klassen zerstört.

    Dieses Script erkennt die Beschädigung und stellt den von bootstrap-inline.js
    gesetzten korrekten Zustand wieder her.
--}}
<script>
(function() {
    var d = document.documentElement;
    var t = d.getAttribute('data-theme');
    var c = d.getAttribute('class');
    if (t === 'undefined' || c === 'undefined' || t === 'null' || c === 'null') {
        if (c === 'undefined' || c === 'null') d.removeAttribute('class');
        if (typeof window.__omxfcApplyStoredTheme === 'function') {
            window.__omxfcApplyStoredTheme();
        }
    }
})();
</script>
