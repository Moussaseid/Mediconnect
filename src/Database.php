<?php

/**
 * Accès à la connexion PDO globale.
 * La variable $pdo est définie dans config/database.php et disponible globalement.
 */
class Database
{
    public static function getConnection(): \PDO
    {
        global $pdo;
        return $pdo;
    }
}
