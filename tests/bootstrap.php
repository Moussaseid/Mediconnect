<?php
/**
 * Bootstrap PHPUnit — MediConnect
 *
 * - Définit ROOT pour que les chemins src/* fonctionnent.
 * - Charge l'autoloader Composer (pour PHPUnit lui-même).
 * - Enregistre l'autoloader PSR-like du projet (src/…).
 * - Crée une connexion PDO vers mediconnect_test.
 * - Exécute les fixtures reset + seed avant la suite.
 */

define('ROOT', dirname(__DIR__));

require_once ROOT . '/vendor/autoload.php';

// Autoloader PSR-like du projet (miroir de config/config.php, sans session)
spl_autoload_register(function (string $class): void {
    $file = ROOT . '/src/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// --- Connexion à la base de test ---
$host   = getenv('TEST_DB_HOST')   ?: '127.0.0.1';
$dbname = getenv('TEST_DB_NAME')   ?: 'mediconnect_test';
$user   = getenv('TEST_DB_USER')   ?: 'root';
$pass   = getenv('TEST_DB_PASS')   ?: '';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "[bootstrap] Impossible de se connecter à {$dbname} : " . $e->getMessage() . PHP_EOL);
    exit(1);
}

// --- Reset + seed ---
$fixtures = [
    ROOT . '/tests/fixtures/reset_test.sql',
    ROOT . '/tests/fixtures/seed_test.sql',
];
foreach ($fixtures as $file) {
    $sql = file_get_contents($file);
    foreach (array_filter(explode(';', $sql)) as $stmt) {
        $stmt = trim($stmt);
        if ($stmt !== '') {
            $pdo->exec($stmt);
        }
    }
}

// Expose $pdo globalement pour les tests qui en ont besoin
$GLOBALS['pdo'] = $pdo;
