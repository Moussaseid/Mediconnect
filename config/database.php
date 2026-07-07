<?php
$host     = ($_ENV['DB_HOST'] ?? '') ?: (getenv('DB_HOST') ?: '127.0.0.1');
$dbname   = ($_ENV['DB_NAME'] ?? '') ?: (getenv('DB_NAME') ?: 'mediconnect');
$user     = ($_ENV['DB_USER'] ?? '') ?: (getenv('DB_USER') ?: 'root');
$password = ($_ENV['DB_PASS'] ?? '') ?: (getenv('DB_PASS') ?: '');
$dsn = "mysql:host=$host;port=3306;dbname=$dbname;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
    $pdo = new PDO($dsn, $user, $password, $options);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Erreur de connexion à la base de données.']));
}
