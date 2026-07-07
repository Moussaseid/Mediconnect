<?php
namespace controllers\api;

use models\CentreModel;
use services\JwtService;
use services\MongoLogService;

/**
 * CentreApiController — CRUD centres de santé et d'analyse
 *
 * Le type (sante|analyse) est extrait de l'URL par le routeur.
 *
 * GET    /api/centres/{type}        → liste   (auth requise)
 * GET    /api/centres/{type}/:id    → détail  (auth requise)
 * POST   /api/centres/{type}        → créer   (admin)
 * PUT    /api/centres/{type}/:id    → modifier (admin)
 * DELETE /api/centres/{type}/:id    → supprimer (admin)
 */
class CentreApiController
{
    private const TYPES_VALIDES = ['sante', 'analyse'];

    private CentreModel     $centreModel;
    private JwtService      $jwtService;
    private MongoLogService $mongoLog;

    public function __construct(private \PDO $pdo)
    {
        $this->centreModel = new CentreModel($pdo);
        $this->jwtService  = new JwtService();
        $this->mongoLog    = new MongoLogService();
    }

    // ── GET /api/centres/{type} ──────────────────────────────────────────────
    public function liste(array $params = []): void
    {
        $this->exigerAuth();
        $type = $this->resolveType($params);

        $rows = $this->centreModel->lister($type);
        $this->ok(array_map(fn($r) => $this->format($type, $r), $rows));
    }

    // ── GET /api/centres/{type}/:id ──────────────────────────────────────────
    public function detail(array $params = []): void
    {
        $this->exigerAuth();
        $type   = $this->resolveType($params);
        $centre = $this->centreModel->findById($type, (int) ($params['id'] ?? 0));

        if ($centre === null) $this->erreur('Centre introuvable', 404);
        $this->ok($this->format($type, $centre));
    }

    // ── POST /api/centres/{type} ─────────────────────────────────────────────
    public function creer(array $params = []): void
    {
        $payload = $this->exigerAuth('admin');
        $type    = $this->resolveType($params);
        $data    = $this->lireCorps();
        $erreurs = $this->valider($data);

        if (!empty($erreurs)) $this->erreur('Données invalides', 422, json_encode($erreurs));

        $id     = $this->centreModel->creer($type, $data);
        $centre = $this->centreModel->findById($type, $id);

        $this->mongoLog->logAdmin((int) $payload->sub, "creer_centre_$type", $id, [], ['nom' => $data['nom']]);

        $this->ok($this->format($type, $centre), 201, 'Centre créé');
    }

    // ── PUT /api/centres/{type}/:id ──────────────────────────────────────────
    public function modifier(array $params = []): void
    {
        $payload = $this->exigerAuth('admin');
        $type    = $this->resolveType($params);
        $id      = (int) ($params['id'] ?? 0);
        $centre  = $this->centreModel->findById($type, $id);

        if ($centre === null) $this->erreur('Centre introuvable', 404);

        $data    = $this->lireCorps();
        $erreurs = $this->valider($data);
        if (!empty($erreurs)) $this->erreur('Données invalides', 422, json_encode($erreurs));

        $this->centreModel->modifier($type, $id, $data);
        $this->mongoLog->logAdmin((int) $payload->sub, "modifier_centre_$type", $id,
            ['nom' => $centre['nom']], ['nom' => $data['nom']]);

        $this->ok($this->format($type, $this->centreModel->findById($type, $id)), 200, 'Centre mis à jour');
    }

    // ── DELETE /api/centres/{type}/:id ───────────────────────────────────────
    public function supprimer(array $params = []): void
    {
        $payload = $this->exigerAuth('admin');
        $type    = $this->resolveType($params);
        $id      = (int) ($params['id'] ?? 0);
        $centre  = $this->centreModel->findById($type, $id);

        if ($centre === null) $this->erreur('Centre introuvable', 404);

        $this->centreModel->supprimer($type, $id);
        $this->mongoLog->logAdmin((int) $payload->sub, "supprimer_centre_$type", $id,
            ['nom' => $centre['nom']], []);

        $this->ok(['id' => $id], 200, 'Centre supprimé');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function resolveType(array $params): string
    {
        $type = $params['type'] ?? '';
        if (!in_array($type, self::TYPES_VALIDES, true)) {
            $this->erreur('Type de centre invalide — attendu : sante ou analyse', 400);
        }
        return $type;
    }

    private function exigerAuth(string ...$roles): object
    {
        $token = JwtService::extraireToken();
        if ($token === null) $this->erreur('Token manquant', 401);

        try {
            $payload = $this->jwtService->verifier($token);
        } catch (\RuntimeException $e) {
            $this->erreur($e->getMessage(), 401);
        }

        if (!empty($roles) && !in_array($payload->role ?? '', $roles, true)) {
            $this->erreur('Accès non autorisé pour ce rôle', 403);
        }

        $stmt = $this->pdo->prepare("SELECT statut FROM utilisateurs WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => (int) $payload->sub]);
        if ($stmt->fetchColumn() !== 'actif') $this->erreur('Compte inactif ou suspendu', 403);

        return $payload;
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

    private function format(string $type, array $r): array
    {
        $base = [
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

        if ($type === 'sante') {
            $base['description'] = $r['description'] ?? null;
            $base['specialites'] = $r['specialites'] ?? null;
            $base['services']    = $r['services']    ?? null;
        }

        return $base;
    }

    private function lireCorps(): array
    {
        $raw  = file_get_contents('php://input');
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