<?php
namespace controllers\api;

use models\CentreAnalyseModel;
use services\JwtService;

/**
 * CentreAnalyseApiController — Espace gestionnaire de centre d'analyse
 *
 * Toutes les routes exigent un token JWT avec role = centre_analyse.
 * Le centre_id est résolu depuis gestionnaires_centre_analyse via utilisateur_id.
 *
 * GET    /api/centre-analyse/infos            → infos du centre
 * GET    /api/centre-analyse/analyses         → liste analyses
 * POST   /api/centre-analyse/analyses         → créer analyse
 * PUT    /api/centre-analyse/analyses/:id     → modifier analyse
 * DELETE /api/centre-analyse/analyses/:id     → supprimer analyse
 * PATCH  /api/centre-analyse/analyses/:id/toggle → basculer disponible
 */
class CentreAnalyseApiController
{
    private CentreAnalyseModel $model;
    private JwtService         $jwt;

    public function __construct(private \PDO $pdo)
    {
        $this->model = new CentreAnalyseModel($pdo);
        $this->jwt   = new JwtService();
    }

    // ── GET /api/centre-analyse/infos ────────────────────────────────────────
    public function infos(array $params = []): void
    {
        [$centreId] = $this->authCentre();
        $centre = $this->model->getCentre($centreId);
        if ($centre === null) $this->erreur('Centre introuvable', 404);
        $this->ok($this->formatCentre($centre));
    }

    // ── GET /api/centre-analyse/analyses ─────────────────────────────────────
    public function liste(array $params = []): void
    {
        [$centreId] = $this->authCentre();
        $rows = $this->model->lister($centreId);
        $this->ok(array_map(fn($r) => $this->formatAnalyse($r), $rows));
    }

    // ── POST /api/centre-analyse/analyses ────────────────────────────────────
    public function creer(array $params = []): void
    {
        [$centreId] = $this->authCentre();
        $data = $this->lireCorps();
        $erreurs = $this->valider($data);
        if (!empty($erreurs)) $this->erreur('Données invalides', 422, json_encode($erreurs));

        $id = $this->model->creer($centreId, $data);
        $analyse = $this->model->findById($id, $centreId);
        $this->ok($this->formatAnalyse($analyse), 201, 'Analyse créée');
    }

    // ── PUT /api/centre-analyse/analyses/:id ─────────────────────────────────
    public function modifier(array $params = []): void
    {
        [$centreId] = $this->authCentre();
        $id = (int) ($params['id'] ?? 0);
        if ($this->model->findById($id, $centreId) === null) $this->erreur('Analyse introuvable', 404);

        $data = $this->lireCorps();
        $erreurs = $this->valider($data);
        if (!empty($erreurs)) $this->erreur('Données invalides', 422, json_encode($erreurs));

        $this->model->modifier($id, $centreId, $data);
        $this->ok($this->formatAnalyse($this->model->findById($id, $centreId)), 200, 'Analyse mise à jour');
    }

    // ── DELETE /api/centre-analyse/analyses/:id ──────────────────────────────
    public function supprimer(array $params = []): void
    {
        [$centreId] = $this->authCentre();
        $id = (int) ($params['id'] ?? 0);
        if (!$this->model->supprimer($id, $centreId)) $this->erreur('Analyse introuvable', 404);
        $this->ok(['id' => $id], 200, 'Analyse supprimée');
    }

    // ── PATCH /api/centre-analyse/analyses/:id/toggle ────────────────────────
    public function toggle(array $params = []): void
    {
        [$centreId] = $this->authCentre();
        $id = (int) ($params['id'] ?? 0);
        $newVal = $this->model->toggleDisponible($id, $centreId);
        if ($newVal === null) $this->erreur('Analyse introuvable', 404);
        $this->ok(['id' => $id, 'disponible' => $newVal]);
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

        if (($payload->role ?? '') !== 'centre_analyse') {
            $this->erreur('Accès réservé aux centres d\'analyse', 403);
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
        if (isset($data['prix']) && $data['prix'] < 0) $erreurs['prix'] = 'Le prix doit être positif';
        if (isset($data['dureeMinutes']) && (int) $data['dureeMinutes'] < 1) {
            $erreurs['dureeMinutes'] = 'La durée doit être au moins 1 minute';
        }
        return $erreurs;
    }

    private function formatAnalyse(array $r): array
    {
        return [
            'id'           => (int)   $r['id'],
            'centreId'     => (int)   $r['centre_id'],
            'nom'          =>         $r['nom'],
            'description'  =>         $r['description']   ?? null,
            'prix'         => (float) $r['prix'],
            'dureeMinutes' => (int)   $r['duree_minutes'],
            'disponible'   => (bool)  $r['disponible'],
            'createdAt'    =>         $r['created_at']    ?? null,
        ];
    }

    private function formatCentre(array $r): array
    {
        return [
            'id'        => (int)   $r['id'],
            'nom'       =>         $r['nom'],
            'adresse'   =>         $r['adresse']   ?? null,
            'latitude'  => isset($r['latitude'])  ? (float) $r['latitude']  : null,
            'longitude' => isset($r['longitude']) ? (float) $r['longitude'] : null,
            'telephone' =>         $r['telephone'] ?? null,
            'email'     =>         $r['email']     ?? null,
            'actif'     => (bool)  $r['actif'],
            'createdAt' =>         $r['created_at'] ?? null,
        ];
    }

    private function lireCorps(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) return [];
        $data = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) $this->erreur('Corps JSON invalide', 400, json_last_error_msg());
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
