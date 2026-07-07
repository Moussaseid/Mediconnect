<?php
// Endpoint AJAX — retourne TOUJOURS du JSON (section 1.7)
header('Content-Type: application/json');
define('ROOT', dirname(__DIR__));
require_once ROOT . '/config/database.php';

$rayon          = intval($_GET['rayon'] ?? 10);
$specialisation = htmlspecialchars($_GET['specialisation'] ?? '');

if ($rayon <= 0 || $rayon > 100) {
    http_response_code(400);
    echo json_encode(['error' => 'Paramètre rayon invalide.']);
    exit;
}
// TODO : MedecinModel->rechercherParRayon($rayon, $lat, $lon)
echo json_encode(['medecins' => []]);
exit;
