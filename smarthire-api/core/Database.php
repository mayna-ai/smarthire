<?php

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $cfg = require __DIR__ . '/../config/database.php';
            $dsn = "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['dbname']};charset={$cfg['charset']}";

            try {
                self::$instance = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE  => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES    => false,
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode(['error' => 'Database connection failed']);
                exit;
            }
        }

        return self::$instance;
    }

    /**
     * Utilisé uniquement par les tests (PHPUnit) pour injecter une connexion
     * SQLite en mémoire à la place de MySQL. Ne pas appeler en production.
     */
    public static function setConnection(PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    /** Réinitialise le singleton entre deux tests pour éviter les fuites d'état. */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
