<?php
// server_router.php — Routeur pour PHP built-in server (dev uniquement)
// Usage : php -S localhost:8080 -t public/ public/server_router.php

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Servir les fichiers statiques directement (assets, favicon, etc.)
if ($uri !== '/' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    // Laisser le serveur built-in servir le fichier
    return false;
}

// PHP built-in server met SCRIPT_NAME = REQUEST_URI, ce qui brise le calcul
// de $base dans routes.php. On le force à simuler un index.php à la racine.
$_SERVER['SCRIPT_NAME'] = '/index.php';

// Tout le reste → index.php (qui charge config → routes)
define('ROOT', dirname(__DIR__));

require_once ROOT . '/config/config.php';
