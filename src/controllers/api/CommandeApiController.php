<?php
namespace controllers\api;

use models\CommandeModel;
use models\PharmacieModel;
use services\JwtService;

/**
 * CommandeApiController — Gestion des commandes médicaments
 *
 * GET  /api/commandes?pharmacieId=X[&statut=Y]
 *      → liste des commandes d'une pharmacie (roles : pharmacie, admin)
 *
 * GET  /api/commandes/:id
 *      → détail d'une commande avec lignes (roles : pharmacie, admin)
 *
 * POST /api/commandes
 *      → créer une commande (tout utilisateur authentifié)
 *
 * PUT  /api/commandes/:id
 *      → mettre à jour le statut (roles : pharmacie, admin)
 *      Body : { "statut": "preparee"|"prete"|"livree"|"annulee" }
 */
class CommandeApiController
{
    private CommandeModel  $commandeModel;
    private PharmacieModel $pharmacieModel;
    private JwtService     $jwtService;

    public function __construct(private \PDO $pdo)
    {
        $this->commandeModel  = new CommandeModel($pdo);
        $this->pharmacieModel = new PharmacieModel($pdo);
        $this->jwtService     = new JwtService();
    }

    // ── GET /api/commandes ────────────────────────────────────────────────────
    public function liste(array $params = []): void
    {
        $this->exigerAuth('pharmacie', 'admin');

        $pharmacieId = (int) ($_GET['pharmacieId'] ?? 0);
        if ($pharmacieId <= 0) {
            $this->erreur('pharmacieId est requis', 422);
        }

        $statut = $_GET['statut'] ?? null;
        $statutsValides = ['en_attente', 'preparee', 'prete', 'livree', 'annulee'];
        if ($statut !== null && !in_array($statut, $statutsValides, true)) {
            $this->erreur('statut invalide', 422);
        }

        $commandes = $this->commandeModel->listerParPharmacie($pharmacieId, $statut ?: null);

        $this->ok(array_map(fn($c) => $this->formatCommande($c), $commandes));
    }

    // ── GET /api/commandes/:id ────────────────────────────────────────────────
    public function detail(array $params = []): void
    {
        $this->exigerAuth('pharmacie', 'admin');

        $id       = (int) ($params['id'] ?? 0);
        $commande = $this->commandeModel->findById($id);

        if ($commande === null) $this->erreur('Commande introuvable', 404);

        $this->ok($this->formatCommande($commande));
    }

    // ── POST /api/commandes ───────────────────────────────────────────────────
    public function creer(array $params = []): void
    {
        $payload = $this->exigerAuth();
        $data    = $this->lireCorps();
        $erreurs = $this->validerCreation($data);

        if (!empty($erreurs)) $this->erreur('Données invalides', 422, json_encode($erreurs));

        $pharmacie = $this->pharmacieModel->findById((int) $data['pharmacieId']);
        if ($pharmacie === null || !$pharmacie['actif']) {
            $this->erreur('Pharmacie introuvable ou inactive', 404);
        }

        $commandeId = $this->commandeModel->creer([
            'patientId'        => (int) $payload->sub,
            'pharmacieId'      => (int) $data['pharmacieId'],
            'modeRetrait'      => $data['modeRetrait'],
            'adresseLivraison' => $data['adresseLivraison'] ?? null,
            'notes'            => $data['notes']            ?? null,
        ], $data['lignes']);

        $this->ok(['id' => $commandeId], 201, 'Commande créée');
    }

    // ── PUT /api/commandes/:id ────────────────────────────────────────────────
    public function modifier(array $params = []): void
    {
        $this->exigerAuth('pharmacie', 'admin');

        $id   = (int) ($params['id'] ?? 0);
        $data = $this->lireCorps();

        $statutsValides = ['preparee', 'prete', 'livree', 'annulee'];
        if (empty($data['statut']) || !in_array($data['statut'], $statutsValides, true)) {
            $this->erreur('statut invalide — valeurs : ' . implode(', ', $statutsValides), 422);
        }

        $erreurTransition = $this->commandeModel->mettreAJourStatut($id, $data['statut']);
        if ($erreurTransition !== null) {
            $this->erreur($erreurTransition, 409);
        }

        $commande = $this->commandeModel->findById($id);
        $this->ok($this->formatCommande($commande), 200, 'Statut mis à jour');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

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

    private function validerCreation(array $data): array
    {
        $erreurs = [];

        if (empty($data['pharmacieId']) || (int) $data['pharmacieId'] <= 0) {
            $erreurs['pharmacieId'] = 'pharmacieId requis';
        }

        $modesValides = ['sur_place', 'livraison'];
        if (!in_array($data['modeRetrait'] ?? '', $modesValides, true)) {
            $erreurs['modeRetrait'] = 'modeRetrait invalide — valeurs : sur_place, livraison';
        }

        if (($data['modeRetrait'] ?? '') === 'livraison' && empty(trim($data['adresseLivraison'] ?? ''))) {
            $erreurs['adresseLivraison'] = 'adresseLivraison requise pour le mode livraison';
        }

        if (empty($data['lignes']) || !is_array($data['lignes'])) {
            $erreurs['lignes'] = 'Au moins une ligne est requise';
        } else {
            foreach ($data['lignes'] as $i => $ligne) {
                if (empty($ligne['medicamentId']) || (int) $ligne['medicamentId'] <= 0) {
                    $erreurs["lignes[$i].medicamentId"] = 'medicamentId requis';
                }
                if (!isset($ligne['quantite']) || (int) $ligne['quantite'] < 1) {
                    $erreurs["lignes[$i].quantite"] = 'quantite doit être ≥ 1';
                }
            }
        }

        return $erreurs;
    }

    private function formatCommande(array $c): array
    {
        return [
            'id'               => (int)    $c['id'],
            'patientId'        => (int)    $c['patient_id'],
            'pharmacieId'      => (int)    $c['pharmacie_id'],
            'modeRetrait'      =>           $c['mode_retrait'],
            'adresseLivraison' =>           $c['adresse_livraison'] ?? null,
            'notes'            =>           $c['notes']             ?? null,
            'statut'           =>           $c['statut'],
            'createdAt'        =>           $c['created_at'],
            'updatedAt'        =>           $c['updated_at'],
            'lignes'           => array_map(
                fn($l) => $this->formatLigne($l),
                $c['lignes'] ?? []
            ),
        ];
    }

    private function formatLigne(array $l): array
    {
        return [
            'id'           => (int)   $l['id'],
            'commandeId'   => (int)   $l['commande_id'],
            'medicamentId' => (int)   $l['medicament_id'],
            'quantite'     => (int)   $l['quantite'],
            'prixAchat'    => (float) $l['prix_achat'],
            'medicament'   => [
                'id'    => (int) $l['medicament_id'],
                'nom'   =>       $l['medicament_nom']   ?? null,
                'forme' =>       $l['medicament_forme']  ?? null,
                'dosage'=>       $l['medicament_dosage'] ?? null,
            ],
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