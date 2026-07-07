<?php
// Script de création de la base de test
// Exécuter : php tests/setup_db.php
$host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
$user = getenv('TEST_DB_USER') ?: 'root';
$pass = getenv('TEST_DB_PASS') ?: '';
$name = getenv('TEST_DB_NAME') ?: 'mediconnect_test';

try {
    $pdo = new PDO("mysql:host={$host};port=3306;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "[OK] Base '{$name}' prête." . PHP_EOL;
} catch (PDOException $e) {
    echo "[ERR] " . $e->getMessage() . PHP_EOL;
    exit(1);
}
