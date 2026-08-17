<?php

/**
 * SqliteFixture — construit une base SQLite en mémoire pour les tests
 * d'intégration des repositories, et l'injecte dans le singleton Database
 * (voir Database::setConnection, réservé aux tests).
 *
 * Les repositories utilisent du SQL standard (SELECT/INSERT/UPDATE avec
 * LIMIT/OFFSET) qui fonctionne à l'identique sur SQLite et MySQL ; seul le
 * schéma (tests/fixtures/schema.sqlite.sql) est une version simplifiée du
 * schéma MySQL réel (database/schema.sql), sans ENUM/JSON/index spécifiques.
 */
class SqliteFixture
{
    public static function freshConnection(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $schema = file_get_contents(__DIR__ . '/../fixtures/schema.sqlite.sql');
        $pdo->exec($schema);

        Database::setConnection($pdo);

        return $pdo;
    }
}
