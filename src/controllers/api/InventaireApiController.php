<?php
namespace controllers\api;

use models\InventaireModel;
use models\PharmacieModel;
use services\JwtService;

/**
 * InventaireApiController — Stock d'une pharmacie
 *
 * GET  /api/inventaire/:pharmacieId  → liste le stock (pharmacie ou admin)
 * POST /api/inventaire               → ajoute/incrémente une ligne (pharmacie ou admin)
 * PUT  /api/inventaire/:id           → mise à jour partielle (pharmacie ou admin)
 */
class InventaireApiController
{
    private const ROLES_AUTORISES = ['pharmacie', 'admin'];

    private InventaireModel $inventaireModel;
    private PharmacieModel  $pharmacieModel;
    private JwtService      $jwtService;

    public function __construct(private \PDO $pdo)
    {
        $this->inventaireModel = new InventaireModel($pdo);
        $this->pharmacieModel  = new PharmacieModel($pdo);
        $this->jwtService      = new JwtService();
    }

    // ── GET /api/inventaire/:pharmacieId ────────────────────────────────────
    public function liste(array $params = []): void
    {
        $this->exigerAuth(...self::ROLES_AUTORISES);

        $pharmacieId = (int) ($params['pharmacieId'] ?? 0);
        if ($this->pharmacieModel->findById($pharmacieId) === null) {
            $this->erreur('Pharmacie introuvable', 404);
        }

        $rows = $this->inventaireModel->listerParPharmacie($pharmacieId);
        $this->ok(array_map(fn($r) => $this->format($r), $rows));
    }

    // ── POST /api/inventaire ─────────────────────────────────────────────────
    // Body : { pharmacieId, medicamentId, quantite, prixUnitaire?, datePeremption? }
    public function creer(array $params = []): void
    {
        $this->exigerAuth(...self::ROLES_AUTORISES);

        $data    = $this->lireCorps();
        $erreurs = $this->valider($data, true);
        if (!empty($erreurs)) $this->erreur('Données invalides', 422, json_encode($erreurs));

        $id  = $this->inventaireModel->creer($data);
        $row = $this->inventaireModel->findById($id);

        $this->ok($this->format($row), 201, 'Ligne d\'inventaire créée ou mise à jour');
    }

    // ── PUT /api/inventaire/:id ──────────────────────────────────────────────
    // Body : { quantite?, prixUnitaire?, datePeremption? }
    public function modifier(array $params = []): void
    {
        $this->exigerAuth(...self::ROLES_AUTORISES);

        $id  = (int) ($params['id'] ?? 0);
        $row = $this->inventaireModel->findById($id);
        if ($row === null) $this->erreur('Ligne d\'inventaire introuvable', 404);

        $data    = $this->lireCorps();
        $erreurs = $this->valider($data, false);
        if (!empty($erreurs)) $this->erreur('Données invalides', 422, json_encode($erreurs));

        $this->inventaireModel->modifier($id, $data);
        $this->ok($this->format($this->inventaireModel->findById($id)), 200, 'Stock mis à jour');
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
            $this->erreur('Accès réservé aux gestionnaires de pharmacie et administrateurs', 403);
        }

        $stmt = $this->pdo->prepare("SELECT statut FROM utilisateurs WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => (int) $payload->sub]);
        if ($stmt->fetchColumn() !== 'actif') $this->erreur('Compte inactif ou suspendu', 403);

        return $payload;
    }

    private function valider(array $data, bool $creation): array
    {
        $erreurs = [];

        if ($creation) {
            if (empty($data['pharmacieId']) || (int) $data['pharmacieId'] <= 0) {
                $erreurs['pharmacieId'] = 'pharmacieId requis';
            }
            if (empty($data['medicamentId']) || (int) $data['medicamentId'] <= 0) {
                $erreurs['medicamentId'] = 'medicamentId requis';
            }
            if (!isset($data['quantite']) || (int) $data['quantite'] < 0) {
                $erreurs['quantite'] = 'quantite doit être ≥ 0';
            }
        } else {
            // Mise à jour partielle : au moins un champ requis
            $champsAcceptes = ['quantite', 'prixUnitaire', 'datePeremption'];
            if (empty(array_intersect(array_keys($data), $champsAcceptes))) {
                $erreurs['_'] = 'Au moins un champ parmi : quantite, prixUnitaire, datePeremption';
            }
            if (isset($data['quantite']) && (int) $data['quantite'] < 0) {
                $erreurs['quantite'] = 'quantite doit être ≥ 0';
            }
        }

        if (!empty($data['prixUnitaire']) && (float) $data['prixUnitaire'] < 0) {
            $erreurs['prixUnitaire'] = 'prixUnitaire doit être ≥ 0';
        }

        return $erreurs;
    }

    private function format(array $r): array
    {
        return [
            'id'           => (int)    $r['id'],
            'pharmacieId'  => (int)    $r['pharmacie_id'],
            'medicamentId' => (int)    $r['medicament_id'],
            'quantite'     => (int)    $r['quantite'],
            'prixUnitaire' => isset($r['prix_unitaire']) ? (float) $r['prix_unitaire'] : null,
            'datePeremption' =>        $r['date_peremption'] ?? null,
            'updatedAt'    =>          $r['updated_at']      ?? null,
            'medicament'   => isset($r['medicament_nom']) ? [
                'nom'            => $r['medicament_nom'],
                'description'    => $r['medicament_description'] ?? null,
                'surOrdonnance'  => isset($r['sur_ordonnance']) ? (bool) $r['sur_ordonnance'] : null,
                'forme'          => $r['forme']        ?? null,
                'dosage'         => $r['dosage']       ?? null,
                'laboratoire'    => $r['laboratoire']  ?? null,
            ] : null,
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