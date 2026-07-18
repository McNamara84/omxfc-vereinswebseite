# Maddraxikon-Baxx: Betrieb, Rollout und Datenschutz

Stand: 18. Juli 2026

Diese Anleitung beschreibt den Betrieb der implementierten Integration. Die
Installation des OAuth-Servers im Wiki steht separat in
[maddraxikon-oauth2-installation.md](maddraxikon-oauth2-installation.md).

## 1. Sicherheitsmodell

- Vereinswebsite und Maddraxikon behalten getrennte Datenbanken und Konten.
- Die Kontoinhaberschaft wird ausschließlich mit OAuth 2.0 Authorization Code
  und PKCE nachgewiesen.
- Die Anwendung fordert nur die Wiki-Identität an. OAuth-Access- und
  Refresh-Token werden nach dem Profilabruf nicht gespeichert.
- Importiert wird über die MediaWiki Action API, nicht durch HTML-Crawling.
- Nur aktive Benutzer des exakt benannten Teams `Mitglieder` sind berechtigt;
  die Rolle `Anwärter` ist ausgeschlossen.
- Alle Schalter sind standardmäßig deaktiviert. Die drei Stufen Verknüpfung,
  Import und Auszahlung werden getrennt freigeschaltet.

## 2. Erforderliche Produktionskonfiguration

Mindestens folgende Werte müssen in `.env.production` gesetzt werden:

```dotenv
MADDRAXIKON_BASE_URL=https://de.maddraxikon.com
MADDRAXIKON_WIKI_KEY=maddraxikon-de
MADDRAXIKON_OAUTH_CLIENT_ID=
MADDRAXIKON_OAUTH_CLIENT_SECRET=
MADDRAXIKON_IDENTITY_HMAC_PEPPERS=v1:raw:EIN_LANGES_ZUFAELLIGES_GEHEIMNIS

MADDRAXIKON_LINKING_ENABLED=false
MADDRAXIKON_SYNC_ENABLED=false
MADDRAXIKON_AWARDS_ENABLED=false

MADDRAXIKON_RECENT_CHANGES_RETENTION_DAYS=30
MADDRAXIKON_SYNC_MAX_WINDOW_MINUTES=360
MADDRAXIKON_RECOVERY_MAX_WINDOW_DAYS=90
MADDRAXIKON_EVALUATION_SOURCE_BATCH_SIZE=100
MADDRAXIKON_EVALUATION_API_BATCH_SIZE=50
MADDRAXIKON_MONITOR_QUEUE_OLDEST_MINUTES=30
MADDRAXIKON_CORRECTION_AUDIT_RETENTION_DAYS=3650
DB_QUEUE_RETRY_AFTER=420
```

Der erste Eintrag in `MADDRAXIKON_IDENTITY_HMAC_PEPPERS` ist der aktive Pepper.
Er muss unabhängig vom Laravel-`APP_KEY` erzeugt, gesichert und bei Deployments
unverändert bereitgestellt werden. Eine einmal eingesetzte Versionskennung ist
dauerhaft und darf niemals einem anderen Geheimnis zugeordnet werden. Bei einer
Rotation erhält das neue Geheimnis deshalb zwingend einen neuen Versionsnamen
und wird vorangestellt; alte Einträge bleiben unverändert für die Prüfung
vorhandener Tombstones erhalten. Doppelte Versionsnamen werden bereits beim
Einlesen der Konfiguration abgewiesen. Zusätzlich bindet jeder Tombstone seine
Schlüsselversion an einen domänenseparierten Fingerprint. Eine heimliche
Secret-Änderung unter demselben Namen stoppt neue Verknüpfungen fail-closed.
Das Format ist `version:raw:secret`, mehrere Einträge werden durch
Kommas getrennt.

Falls bereits Tombstones mit einem alten `APP_KEY` erzeugt wurden, muss dieser
Schlüssel nachrangig als
`legacy-app-key:raw:ALTER_APP_KEY_WERT` aufgenommen werden. Erst wenn keine so
markierten Altzeilen mehr existieren, darf dieser Eintrag entfallen.

Die OAuth-Callback-URL ist:

```text
https://maddrax-fanclub.de/oauth/maddraxikon/callback
```

Die URL muss in MediaWiki exakt so registriert sein. Wildcards und zusätzliche
Callback-URLs sind nicht vorgesehen. Ihr Origin muss exakt dem Origin von
`APP_URL` entsprechen. Der Callback antwortet mit `Referrer-Policy: no-referrer`
sowie `Cache-Control: no-store, private, max-age=0` und `Pragma: no-cache`; das
Nginx-Accesslog verwendet ausschließlich den
Pfad ohne Query-String, damit Autorisierungscode und `state` nicht protokolliert
werden. Dasselbe queryfreie Logging ist auch in vorgeschalteten Proxies,
Loadbalancern, APM-Systemen und externen Request-Logs zu konfigurieren.

## 3. Deployment und gestufter Go-live

Vor jedem Schritt muss `php artisan maddraxikon:status --skip-api` ohne Alarm
enden.

1. Anwendung deployen, Migrationen ausführen und Regeln einspielen:

   ```bash
   php artisan migrate --force
   php artisan db:seed --class=BaxxEarningRuleSeeder --force
   php artisan config:cache
   ```

2. OAuth im Maddraxikon gemäß Installationsanleitung aktivieren, Client-ID und
   Secret setzen und zunächst nur `MADDRAXIKON_LINKING_ENABLED=true` aktivieren.
3. Mit einem Testmitglied verbinden, trennen und erneut verbinden. Prüfen, dass
   keine OAuth-Token in Datenbank oder Logs erscheinen.
4. Queue und Scheduler starten. Das Produktions-Compose enthält dafür die
   Services `queue` und `scheduler`. Der Scheduler schreibt jede Minute einen
   Heartbeat.
5. Vor dem Import den tatsächlichen, garantierten Wert von MediaWikis
   `$wgRCMaxAge` prüfen. `MADDRAXIKON_RECENT_CHANGES_RETENTION_DAYS` und
   `MADDRAXIKON_SYNC_MAX_WINDOW_MINUTES` dürfen dieses garantierte Fenster nicht
   überschreiten.
6. `MADDRAXIKON_SYNC_ENABLED=true` aktivieren. Der erste Lauf setzt atomar die
   Go-live-Watermark und importiert absichtlich keine älteren Beiträge.
7. Einen weiteren Sync abwarten und Importstatus, Queue sowie Namespace-Zuordnung
   prüfen:

   ```bash
   php artisan maddraxikon:status
   ```

8. Erst danach `MADDRAXIKON_AWARDS_ENABLED=true` aktivieren.

Bei einem Rollback werden die Schalter in umgekehrter Reihenfolge deaktiviert:
zuerst Auszahlungen, dann Import, zuletzt neue Verknüpfungen. Bestehende Audit-
und Buchungsdaten bleiben dabei unverändert.

## 4. Hintergrundprozesse

Der Scheduler plant:

- jede Minute den Scheduler-Heartbeat;
- alle 15 Minuten einen idempotenten Import;
- stündlich die verzögerte Baxx-Auswertung;
- am ersten Tag jedes Monats die Löschung abgelaufener
  Zuordnungskorrekturprotokolle.

Der Queue-Worker verwendet `--timeout=330`; `DB_QUEUE_RETRY_AFTER` muss mit
mindestens 420 Sekunden darüber liegen. Die Auswertung ist pro Lauf begrenzt,
validiert Revisionen und Seiten in API-Batches von höchstens 50 IDs und bricht
bei einem systemischen API-Fehler den Lauf ab, damit der Queue-Backoff greift.
Ein Import holt höchstens das über `MADDRAXIKON_SYNC_MAX_WINDOW_MINUTES`
konfigurierte Zeitfenster (standardmäßig sechs Stunden) auf. Größere, aber noch
innerhalb der RecentChanges-Aufbewahrung liegende Rückstände werden dadurch in
mehreren begrenzten Scheduler-Läufen ohne unbeschränkte Speicherlast aufgeholt.

## 5. Monitoring und Alarmierung

```bash
php artisan maddraxikon:status
php artisan maddraxikon:status --skip-api
```

Ein Exitcode ungleich null ist ein Betriebsalarm. Geprüft werden unter anderem:

- Importalter und fortlaufende API-Fehler;
- offene Recovery-Lücken;
- überfällige oder technisch blockierte Beiträge;
- Queue-Backlog, Alter des ältesten wartenden Jobs und fehlgeschlagene Jobs;
- Scheduler-Heartbeat;
- erwartete MediaWiki-Namensräume;
- Auszahlungsquote, Ablehnungsgründe und Tageslimits.

Der Produktions-Scheduler hat zusätzlich einen Docker-Healthcheck mit
`maddraxikon:status --skip-api`. Die Container-Plattform muss den Zustand
`unhealthy` an den gewählten Alarmkanal des Vereins weiterleiten.

## 6. Recovery

Wenn die RecentChanges-Aufbewahrung überschritten ist, wird die Watermark nicht
übersprungen. Der Import und die Auszahlung bleiben gesperrt, bis die Lücke
bewusst bearbeitet wurde.

```bash
php artisan maddraxikon:recover \
  --from=2026-07-01T00:00:00Z \
  --until=2026-07-02T00:00:00Z
```

Nur vollständige ISO-8601-Zeitstempel mit `Z` oder explizitem Offset werden
akzeptiert. Recovery ist ausschließlich bei einem offenen Alarm zulässig:
`--from` muss exakt dessen aktuellem Lückenanfang entsprechen und `--until` darf
nicht hinter dem dokumentierten Lückenende liegen. Große Fenster müssen
lückenlos aufgeteilt werden.

`list=usercontribs` enthält keinen verlässlichen Bot-Marker der ursprünglichen
RecentChanges-Zeile. Deshalb werden Recovery-Beiträge revisionsgebunden als
Auditdaten gespeichert, aber mit
`recovery_bot_status_unverifiable` abgelehnt und nicht automatisch vergütet.
Der Command weist vor der Bestätigung ausdrücklich darauf hin. Eine automatische
Nachvergütung darf erst aktiviert werden, wenn das Wiki einen dauerhaften,
revisionsgebundenen Feed mit Bot-Marker bereitstellt.

## 7. Datenschutz und Aufbewahrung

Die technische Aufbewahrungsentscheidung lautet:

- Aktive Verknüpfungen, Beiträge und Baxx-Ereignisse bleiben für die
  Nachvollziehbarkeit des Mitgliedskontos gespeichert und werden bei der
  Löschung des lokalen Benutzerkontos kaskadierend entfernt.
- Gehashte Identitäts-Tombstones bleiben ohne Wiki-Benutzernamen dauerhaft
  erhalten. Sie verhindern, dass eine bereits zurückgezogene oder korrigierte
  Wiki-Identität einem anderen Vereinskonto zugeordnet wird.
- Administrative Zuordnungskorrekturen werden standardmäßig 3.650 Tage
  aufbewahrt und danach monatlich gelöscht. Die Frist ist über
  `MADDRAXIKON_CORRECTION_AUDIT_RETENTION_DAYS` konfigurierbar, jedoch technisch
  auf mindestens 365 Tage begrenzt.
- Freitextbegründungen dürfen keine Zugangsdaten, OAuth-Token, E-Mailadressen
  oder sonstige nicht erforderliche personenbezogene Daten enthalten.

Vor dem Go-live muss der Vorstand diese technische Fristenentscheidung und die
Rechtsgrundlage prüfen und im Verzeichnis der Verarbeitungstätigkeiten
dokumentieren.

Eine Vorschau beziehungsweise sofortige Ausführung der Löschung ist möglich:

```bash
php artisan maddraxikon:prune-audit --pretend
php artisan maddraxikon:prune-audit
```

## 8. Abnahmecheckliste

- OAuth-Callback, PKCE und neutrale Konfliktmeldungen getestet
- eigener HMAC-Pepper gesetzt und gesichert
- Mitglieder-Team heißt exakt `Mitglieder`
- Queue und Scheduler laufen jeweils genau einmal
- Scheduler-Healthcheck ist an einen Alarmkanal angebunden
- Namespace-Check ist grün
- erste Watermark ist dokumentiert
- Verknüpfung, Import, 24-Stunden-Prüfung und Baxx-Buchung im Staging geprüft
- Tageslimit, Idempotenz und Gegenbuchung geprüft
- Backup und Wiederherstellung der neuen Tabellen getestet
- Datenschutztext und Aufbewahrungsfrist freigegeben
