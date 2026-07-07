<?php
namespace controllers\api;

use models\PharmacieModel;
use services\JwtService;
use services\MongoLogService;

/**
 * PharmacieApiController — CRUD pharmacies
 *
 * GET    /api/pharmacies      → liste (auth requise)
 * GET    /api/pharmacies/:id  → détail (auth requise)
 * POST   /api/pharmacies      → créer  (admin)
 * PUT    /api/pharmacies/:id  → modifier (admin)
 * DELETE /api/pharmacies/:id  → supprimer (admin)
 */
class PharmacieApiController
{
    private PharmacieModel  $pharmacieModel;
    private JwtService      $jwtService;
    private MongoLogService $mongoLog;

    public function __construct(private \PDO $pdo)
    {
        $this->pharmacieModel = new PharmacieModel($pdo);
        $this->jwtService     = new JwtService();
        $this->mongoLog       = new MongoLogService();
    }

    // ── GET /api/pharmacies ──────────────────────────────────────────────────
    public function liste(array $params = []): void
    {
        $this->exigerAuth();

        $rows = $this->pharmacieModel->lister();
        $this->ok(array_map(fn($r) => $this->format($r), $rows));
    }

    // ── GET /api/pharmacies/:id ──────────────────────────────────────────────
    public function detail(array $params = []): void
    {
        $this->exigerAuth();

        $pharmacie = $this->pharmacieModel->findById((int) ($params['id'] ?? 0));
        if ($pharmacie === null) $this->erreur('Pharmacie introuvable', 404);

        $this->ok($this->format($pharmacie));
    }

    // ── POST /api/pharmacies ─────────────────────────────────────────────────
    public function creer(array $params = []): void
    {
        $payload = $this->exigerAuth('admin');
        $data    = $this->lireCorps();
        $erreurs = $this->valider($data);

        if (!empty($erreurs)) $this->erreur('Données invalides', 422, json_encode($erreurs));

        $id        = $this->pharmacieModel->creer($data);
        $pharmacie = $this->pharmacieModel->findById($id);

        $this->mongoLog->logAdmin((int) $payload->sub, 'creer_pharmacie', $id, [], ['nom' => $data['nom']]);

        $this->ok($this->format($pharmacie), 201, 'Pharmacie créée');
    }

    // ── PUT /api/pharmacies/:id ──────────────────────────────────────────────
    public function modifier(array $params = []): void
    {
        $payload   = $this->exigerAuth('admin');
        $id        = (int) ($params['id'] ?? 0);
        $pharmacie = $this->pharmacieModel->findById($id);

        if ($pharmacie === null) $this->erreur('Pharmacie introuvable', 404);

        $data    = $this->lireCorps();
        $erreurs = $this->valider($data);
        if (!empty($erreurs)) $this->erreur('Données invalides', 422, json_encode($erreurs));

        $avant = ['nom' => $pharmacie['nom'], 'actif' => $pharmacie['actif']];
        $this->pharmacieModel->modifier($id, $data);

        $this->mongoLog->logAdmin((int) $payload->sub, 'modifier_pharmacie', $id,
            $avant, ['nom' => $data['nom'], 'actif' => $data['actif'] ?? 1]);

        $this->ok($this->format($this->pharmacieModel->findById($id)), 200, 'Pharmacie mise à jour');
    }

    // ── DELETE /api/pharmacies/:id ───────────────────────────────────────────
    public function supprimer(array $params = []): void
    {
        $payload   = $this->exigerAuth('admin');
        $id        = (int) ($params['id'] ?? 0);
        $pharmacie = $this->pharmacieModel->findById($id);

        if ($pharmacie === null) $this->erreur('Pharmacie introuvable', 404);

        $this->pharmacieModel->supprimer($id);
        $this->mongoLog->logAdmin((int) $payload->sub, 'supprimer_pharmacie', $id,
            ['nom' => $pharmacie['nom']], []);

        $this->ok(['id' => $id], 200, 'Pharmacie supprimée');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

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

    private function format(array $r): array
    {
        return [
            'id'         => (int)   $r['id'],
            'nom'        =>         $r['nom'],
            'adresse'    =>         $r['adresse']     ?? null,
            'codePostal' =>         $r['code_postal'] ?? null,
            'ville'      =>         $r['ville']       ?? null,
            'telephone'  =>         $r['telephone']   ?? null,
            'email'      =>         $r['email']       ?? null,
            'latitude'   => isset($r['latitude'])  ? (float) $r['latitude']  : null,
            'longitude'  => isset($r['longitude']) ? (float) $r['longitude'] : null,
            'actif'      => (bool)  $r['actif'],
            'createdAt'  =>         $r['created_at']  ?? null,
            'updatedAt'  =>         $r['updated_at']  ?? null,
        ];
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