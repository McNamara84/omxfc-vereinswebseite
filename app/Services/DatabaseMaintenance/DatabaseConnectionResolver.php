<?php

namespace App\Services\DatabaseMaintenance;

class DatabaseConnectionResolver
{
    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $connectionName = (string) config('database.default');
        $connection = config("database.connections.{$connectionName}");

        if (! is_array($connection)) {
            throw new DatabaseMaintenanceException('Die aktuelle Datenbankverbindung konnte nicht gelesen werden.');
        }

        $driver = (string) ($connection['driver'] ?? '');
        $requiresMysqlLike = (bool) config('database-maintenance.require_mysql_like_connection', true);

        if ($requiresMysqlLike && ! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new DatabaseMaintenanceException('Datenbank-Dumps sind nur fuer MySQL/MariaDB-Verbindungen verfuegbar.');
        }

        if ((string) ($connection['database'] ?? '') === '') {
            throw new DatabaseMaintenanceException('Der Datenbankname ist nicht konfiguriert.');
        }

        return array_merge($connection, [
            'connection_name' => $connectionName,
        ]);
    }

    /**
     * @param  array<string, mixed>  $connection
     */
    public function databaseName(array $connection): string
    {
        return (string) $connection['database'];
    }
}
