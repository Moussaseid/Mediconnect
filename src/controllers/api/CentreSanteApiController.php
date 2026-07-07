<?php
namespace controllers\api;

use models\CentreSanteModel;
use services\JwtService;

/**
 * CentreSanteApiController — Espace gestionnaire de centre de santé
 *
 * Toutes les routes exigent un token JWT avec role = centre_sante.
 * Le centre_id est résolu depuis gestionnaires_centre_sante via utilisateur_id.
 *
 * GET   /api/centre-sante/infos        → infos du centre
 * PUT   /api/centre-sante/infos        → modifier infos
 * POST  /api/centre-sante/photo        → uploader photo de couverture
 */
class CentreSanteApiController
{
    private CentreSanteModel $model;
    private JwtService       $jwt;

    public function __construct(private \PDO $pdo)
    {
        $this->model = new CentreSanteModel($pdo);
        $this->jwt   = new JwtService();
    }

    // ── GET /api/centre-sante/infos ──────────────────────────────────────────
    public function infos(array $params = []): void
    {
        [$centreId] = $this->authCentre();
        $centre = $this->model->getCentre($centreId);
        if ($centre === null) $this->erreur('Centre introuvable', 404);
        $this->ok($this->format($centre));
    }

    // ── PUT /api/centre-sante/infos ──────────────────────────────────────────
    public function modifierInfos(array $params = []): void
    {
        [$centreId] = $this->authCentre();
        $data = $this->lireCorps();
        $erreurs = $this->valider($data);
        if (!empty($erreurs)) $this->erreur('Données invalides', 422, json_encode($erreurs));

        $this->model->modifier($centreId, $data);
        $centre = $this->model->getCentre($centreId);
        $this->ok($this->format($centre), 200, 'Informations mises à jour');
    }

    // ── POST /api/centre-sante/photo ─────────────────────────────────────────
    public function uploadPhoto(array $params = []): void
    {
        [$centreId] = $this->authCentre();

        if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $this->erreur('Aucun fichier reçu ou erreur d\'upload', 400);
        }

        $file = $_FILES['photo'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $this->erreur('Format non accepté — jpg, jpeg, png, webp uniquement', 422);
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            $this->erreur('Fichier trop volumineux (max 5 Mo)', 422);
        }

        $uploadDir = ROOT . '/public/uploads/centres/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

        $filename = 'centre_sante_' . $centreId . '_' . time() . '.' . $ext;
        $dest     = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            $this->erreur('Échec du déplacement du fichier', 500);
        }

        $path = '/uploads/centres/' . $filename;
        $this->model->setPhoto($centreId, $path);
        $this->ok(['photoPath' => $path], 200, 'Photo mise à jour');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function authCentre(): array
    {
        $token = JwtService::extraireToken();
        if ($token === null) $this->erreur('Token manquant', 401);

        try {
            $payload = $this->jwt->verifier($token);
        } catch (\RuntimeException $e) {
            $this->erreur($e->getMessage(), 401);
        }

        if (($payload->role ?? '') !== 'centre_sante') {
            $this->erreur('Accès réservé aux centres de santé', 403);
        }

        $stmt = $this->pdo->prepare("SELECT statut FROM utilisateurs WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => (int) $payload->sub]);
        if ($stmt->fetchColumn() !== 'actif') $this->erreur('Compte inactif ou suspendu', 403);

        $centreId = $this->model->getCentreIdForUser((int) $payload->sub);
        if ($centreId === null) $this->erreur('Aucun centre associé à ce compte', 403);

        return [$centreId, $payload];
    }

    private function valider(array $data): array
    {
        $erreurs = [];
        if (empty(trim($data['nom'] ?? ''))) $erreurs['nom'] = 'Le nom est requis';
        if (isset($data['email']) && $data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $erreurs['email'] = 'Email invalide';
        }
        return $erreurs;
    }

    private function format(array $r): array
    {
        return [
            'id'          => (int)   $r['id'],
            'nom'         =>         $r['nom'],
            'adresse'     =>         $r['adresse']     ?? null,
            'latitude'    => isset($r['latitude'])  ? (float) $r['latitude']  : null,
            'longitude'   => isset($r['longitude']) ? (float) $r['longitude'] : null,
            'telephone'   =>         $r['telephone']   ?? null,
            'email'       =>         $r['email']       ?? null,
            'description' =>         $r['description'] ?? null,
            'specialites' =>         $r['specialites'] ?? null,
            'services'    =>         $r['services']    ?? null,
            'photoPath'   =>         $r['photo_path']  ?? null,
            'actif'       => (bool)  $r['actif'],
            'createdAt'   =>         $r['created_at']  ?? null,
        ];
    }

    private function lireCorps(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) return [];
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) $this->erreur('Corps JSON invalide', 400);
        return $data;
    }

    private function ok(mixed $data, int $code = 200, ?string $message = null): never
    {
        http_response_code($code);
        $body = ['data' => $data];
        if ($message !== null) $body['message'] = $message;
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function erreur(string $msg, int $code = 400, ?string $details = null): never
    {
        http_response_code($code);
        $body = ['error' => $msg, 'code' => $code];
        if ($details !== null) $body['details'] = $details;
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
