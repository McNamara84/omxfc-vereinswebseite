## Befehle
### Dev
- Neuen Controller anlegen: `php artisan make:controller CONTROLLERNAME`
- Neue View anlegen: `php artisan make:view VIEWNAME`
- Build durchführen: `npm install && npm run build`
- Testerver mit Composer starten: `composer run dev`
- Testserver mit artisan starten: `php artisan serve`
- Datenbankstruktur erstellen: `php artisan migrate`
- Rückgängig machen der Datenbankmigration: `php artisan migrate:rollback`
- Cache leeren: `php artisan cache:clear`
- Datenbank vollständig zurücksetzen: `php artisan migrate:fresh`
- Cache für Routes neu schreiben: `php artisan route:clear`
- Neue Romane indexieren für Kompendium: `php artisan romane:index`
- Romane komplett neu indexieren: `php artisan romane:index --fresh`
- Romane aus JSON-Datei in Datenbank importieren: `php artisan books:import`
- Rezensionen aus CSV-Datei in Datenbank importieren: `php artisan reviews:import-old --fresh`

### Build
- Romane aus JSON-Datei in Datenbank importieren: `docker exec maddrax-app bash -c "php artisan books:import"`
- Dateien aus `/tmp/private-files` übertragen in den `private`-Storage: `docker cp /tmp/private-files/. maddrax-app:/var/www/html/storage/app/private/`
- Rechte für `private`-Storage setzen: `docker exec maddrax-app chown -R www-data:www-data /var/www/html/storage/app/private/`
- Unter `/tmp` abgelegte Protokolle verfügbar machen: `docker cp /tmp/2023-05-20-gruendungsversammlung.pdf maddrax-app:/var/www/html/storage/app/private/protokolle/`

### Neue Romande indexieren
1. Ordner auf den Server hochladen nach `/tmp/romane_temp`.
2. Berechtigungen setzen mit `docker exec maddrax-app chown -R www-data:www-data /var/www/html/storage/app/private/romane` und `docker exec maddrax-app chmod -R 775 /var/www/html/storage/app/private/romane`.
3. Indexierung starten entweder mit `docker exec maddrax-app php artisan romane:index` oder `docker exec maddrax-app php artisan romane:index --fresh`.