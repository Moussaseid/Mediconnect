<?php
// Autoload Composer (firebase/php-jwt, etc.)
$vendor = ROOT . '/vendor/autoload.php';
if (file_exists($vendor)) require_once $vendor;

session_start();
require_once ROOT . '/config/database.php'; // définit $pdo
spl_autoload_register(function (string $class): void {
    $file = ROOT . '/src/' . str_replace('\\', '/', $class) . '.php';
    if (file_exists($file)) require_once $file;
});
require_once ROOT . '/routes.php'; // $pdo est dans le scope global, routes.php y accède
