# Bestehende Anwendungsfehler aus der Testabnahme

## Behoben am 17. Oktober 2026: WebKit-Overlay des mobilen Navigationsmenüs

Die CSS-Regel in `resources/css/app.css` setzt die Sichtbarkeit des Overlays
unterhalb von `64rem` ausdrücklich anhand des zugehörigen angekreuzten
Drawer-Checkboxes. Damit trifft der reale Overlay-Klick auch unter WebKit das
Label. Escape, Overlay-Klick, Fokusrückgabe und Schließen nach Navigation
bestehen mit Playwright 1.64.1 in Chromium, Firefox und WebKit. Der Test prüft
zusätzlich die tatsächliche Sichtbarkeit des Overlays; keine bestehende
Assertion wurde entfernt. Die folgenden Absätze dokumentieren den früheren Befund.

Der unveränderte Test `mobile member drawer opens, closes with escape and closes
after navigation` in `tests/e2e/member-navigation.spec.js` schlägt unter dem
nativen Linux-WebKit fehl. Nach dem Overlay-Klick bleibt `#member-drawer`
angekreuzt. Die Prüfung mit Escape besteht zuvor. Chromium und Firefox bestehen
den gesamten Test.

Der Fehler wurde auch mit dem Test und dem Browser-Seeding des Ausgangscommits
`7b509af193e78a3cfacd098d3d0c0db76c3474e5` reproduziert. Ein weiterer Lauf ohne
Trace-Aufzeichnung reproduziert ihn ebenfalls. Bei offenem Menü besitzt
`label.drawer-overlay[for="member-drawer"]` unter WebKit weiterhin
`visibility: hidden`. Das Hit-Testing trifft an zwölf geprüften Positionen im
Overlay-Bereich den umgebenden `div.drawer-side`, statt das Label zu treffen.
Eine andere Klickposition im Test behebt dieses Sichtbarkeitsproblem daher nicht.

Reproduktion nach dem dokumentierten Bau der isolierten Testumgebung:

```sh
docker compose -f docker-compose.tests.yml run --rm --no-deps assets
docker compose -f docker-compose.tests.yml run --rm --no-deps browser node node_modules/playwright/cli.js test tests/e2e/member-navigation.spec.js --project=webkit --grep="mobile member drawer" --workers=1 --trace=off
```

Die damalige Aufgabe: Die Sichtbarkeits- und Hit-Testing-Regeln des MaryUI/daisyUI-
Drawers unter WebKit untersuchen. Betroffen sind
`resources/views/layouts/member.blade.php`, die von MaryUI erzeugte
`.drawer-overlay` und die zugehörigen CSS-Regeln. Die Behebung anschließend
gegen alle drei Browser prüfen, einschließlich Escape, Overlay-Klick,
Fokusrückgabe und Schließen nach Navigation.

Der Anwendungscode und die Assertions dieses Tests bleiben im Testrefactoring
erhalten. Der Fall wird weder übersprungen noch als erwarteter Fehler markiert.
Der ursprüngliche lokale Browserbefehl meldete diesen Fehler mit einem
Fehlercode. WebKit war bereits vor dem Refactoring aus der Linux-CI ausgeschlossen;
Chromium und Firefox bleiben dort vollständig verpflichtend.
