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
- Rezensionen aus CSV-Datei in Datenbank importieren: `docker exec maddrax-app bash -c "php artisan books:import"`
- Dateien aus `/tmp/private-files` übertragen in den `private`-Storage: `docker cp /tmp/private-files/. maddrax-app:/var/www/html/storage/app/private/`
- Rechte für `private`-Storage setzen: `docker exec maddrax-app chown -R www-data:www-data /var/www/html/storage/app/private/`