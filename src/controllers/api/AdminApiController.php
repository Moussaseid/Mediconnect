<?php
namespace controllers\api;

use models\AdminModel;
use services\JwtService;
use services\MongoLogService;

/**
 * AdminApiController — Endpoints REST administration (JWT + rôle admin requis)
 *
 * GET    /api/admin/patients              → liste paginée + filtre + tri
 * GET    /api/admin/medecins              → liste paginée des médecins
 * GET    /api/admin/medecins/{id}         → détail médecin
 * PUT    /api/admin/medecins/{id}         → modifier médecin
 * PATCH  /api/admin/medecins/{id}/statut  → suspendre / réactiver
 * DELETE /api/admin/medecins/{id}         → supprimer médecin
 * GET    /api/admin/logs                  → logs connexion MongoDB
 * GET    /api/admin/auth-stats            → statistiques connexions/jour + alertes IP
 */
class AdminApiController
{
    private AdminModel      $adminModel;
    private JwtService      $jwtService;
    private MongoLogService $mongoLog;

    public function __construct(private \PDO $pdo)
    {
        $this->adminModel = new AdminModel($pdo);
        $this->jwtService = new JwtService();
        $this->mongoLog   = new MongoLogService();
    }

    // ── GET /api/admin/patients ──────────────────────────────────────────────
    public function patients(array $params = []): void
    {
        $this->exigerAdmin();

        $page      = max(1, (int) ($_GET['page']   ?? 1));
        $parPage   = min(100, max(5, (int) ($_GET['perPage'] ?? 15)));
        $recherche = trim($_GET['search'] ?? '');
        $tri       = $_GET['sort']      ?? 'created_at';
        $ordre     = strtoupper($_GET['order'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';
        $statut    = $_GET['statut']    ?? '';

        $colsAutorisees = ['nom', 'prenom', 'email', 'ville', 'statut', 'created_at'];
        if (!in_array($tri, $colsAutorisees, true)) $tri = 'created_at';

        $offset = ($page - 1) * $parPage;

        // Construction de la requête dynamique
        $where   = ["role = 'patient'"];
        $binds   = [];

        if ($recherche !== '') {
            $where[] = "(nom LIKE :search OR prenom LIKE :search OR email LIKE :search OR ville LIKE :search)";
            $binds[':search'] = '%' . $recherche . '%';
        }
        if (in_array($statut, ['actif', 'en_attente', 'rejete', 'suspendu'], true)) {
            $where[] = 'statut = :statut';
            $binds[':statut'] = $statut;
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $stmtCount = $this->pdo->prepare(
            "SELECT COUNT(*) FROM utilisateurs $whereClause"
        );
        $stmtCount->execute($binds);
        $total = (int) $stmtCount->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT id, nom, prenom, email, telephone, ville, statut, created_at
             FROM utilisateurs
             $whereClause
             ORDER BY $tri $ordre
             LIMIT :limite OFFSET :offset"
        );
        foreach ($binds as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limite', $parPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  \PDO::PARAM_INT);
        $stmt->execute();
        $patients = $stmt->fetchAll();

        $this->ok([
            'patients'   => array_map(fn($p) => [
                'id'        => (int) $p['id'],
                'nom'       => $p['nom'],
                'prenom'    => $p['prenom'],
                'email'     => $p['email'],
                'telephone' => $p['telephone'],
                'ville'     => $p['ville'],
                'statut'    => $p['statut'],
                'createdAt' => $p['created_at'],
            ], $patients),
            'total'      => $total,
            'page'       => $page,
            'parPage'    => $parPage,
            'totalPages' => (int) ceil($total / $parPage),
        ]);
    }

    // ── GET /api/admin/medecins ──────────────────────────────────────────────
    public function medecins(array $params = []): void
    {
        $this->exigerAdmin();

        $page    = max(1, (int) ($_GET['page']    ?? 1));
        $parPage = min(100, max(5, (int) ($_GET['perPage'] ?? 15)));
        $offset  = ($page - 1) * $parPage;

        $total    = $this->adminModel->compterMedecins();
        $medecins = $this->adminModel->listerMedecins($parPage, $offset);

        $this->ok([
            'medecins'   => array_map(fn($m) => [
                'id'             => (int) $m['id'],
                'medecinId'      => (int) $m['medecin_id'],
                'nom'            => $m['nom'],
                'prenom'         => $m['prenom'],
                'email'          => $m['email'],
                'statut'         => $m['statut'],
                'specialisation' => (int) $m['specialisation'],
                'numeroRpps'     => $m['numero_rpps'],
                'adresseCabinet' => $m['adresse_cabinet'],
            ], $medecins),
            'total'      => $total,
            'page'       => $page,
            'parPage'    => $parPage,
            'totalPages' => (int) ceil($total / $parPage),
        ]);
    }

    // ── GET /api/admin/medecins/{id} ─────────────────────────────────────────
    public function getMedecin(array $params = []): void
    {
        $this->exigerAdmin();
        $userId = (int) ($params['id'] ?? 0);

        $m = $this->adminModel->findMedecinParId($userId);
        if (!$m) $this->erreur('Médecin introuvable', 404);

        $this->ok([
            'id'             => (int) $m['id'],
            'medecinId'      => (int) $m['medecin_id'],
            'nom'            => $m['nom'],
            'prenom'         => $m['prenom'],
            'email'          => $m['email'],
            'telephone'      => $m['telephone'],
            'adresse'        => $m['adresse'],
            'ville'          => $m['ville'],
            'statut'         => $m['statut'],
            'specialisation' => (int) $m['specialisation'],
            'numeroRpps'     => $m['numero_rpps'],
            'adresseCabinet' => $m['adresse_cabinet'],
            'dureeRdv'       => (int) $m['duree_rdv'],
        ]);
    }

    // ── PUT /api/admin/medecins/{id} ─────────────────────────────────────────
    public function modifierMedecin(array $params = []): void
    {
        $this->exigerAdmin();
        $userId = (int) ($params['id'] ?? 0);

        $m = $this->adminModel->findMedecinParId($userId);
        if (!$m) $this->erreur('Médecin introuvable', 404);

        $body = $this->lireCorps();
        $erreurs = [];
        if (strlen(trim($body['nom']    ?? '')) < 2) $erreurs['nom']    = 'Requis (2 car. min)';
        if (strlen(trim($body['prenom'] ?? '')) < 2) $erreurs['prenom'] = 'Requis (2 car. min)';
        if (!filter_var($body['email'] ?? '', FILTER_VALIDATE_EMAIL)) $erreurs['email'] = 'Email invalide';
        if (!empty($erreurs)) $this->erreur('Données invalides', 422, json_encode($erreurs, JSON_UNESCAPED_UNICODE));

        $this->adminModel->modifierMedecin($userId, [
            'nom'            => trim($body['nom']),
            'prenom'         => trim($body['prenom']),
            'email'          => trim($body['email']),
            'telephone'      => trim($body['telephone']      ?? ''),
            'adresse'        => trim($body['adresse']        ?? ''),
            'ville'          => trim($body['ville']          ?? ''),
            'specialisation' => (int) ($body['specialisation'] ?? 0),
            'adresse_cabinet'=> trim($body['adresseCabinet'] ?? ''),
            'duree_rdv'      => max(15, (int) ($body['dureeRdv'] ?? 30)),
        ]);

        $payload = $this->verifierJWT();
        $this->mongoLog->log('admin_modification_medecin', (int) $payload->sub, $payload->email, 'admin', 'succes',
            ['medecin_id' => $userId]);

        $this->ok(null, 200, 'Médecin mis à jour');
    }

    // ── PATCH /api/admin/medecins/{id}/statut ────────────────────────────────
    public function changerStatutMedecin(array $params = []): void
    {
        $this->exigerAdmin();
        $userId = (int) ($params['id'] ?? 0);

        $body   = $this->lireCorps();
        $statut = $body['statut'] ?? '';

        if (!in_array($statut, ['actif', 'suspendu'], true)) {
            $this->erreur("Statut invalide — valeurs acceptées : 'actif', 'suspendu'", 422);
        }

        $m = $this->adminModel->findMedecinParId($userId);
        if (!$m) $this->erreur('Médecin introuvable', 404);

        $this->adminModel->changerStatutUtilisateur($userId, $statut);

        $payload = $this->verifierJWT();
        $this->mongoLog->log(
            $statut === 'suspendu' ? 'admin_suspension_medecin' : 'admin_reactivation_medecin',
            (int) $payload->sub, $payload->email, 'admin', 'succes',
            ['medecin_id' => $userId]
        );

        $this->ok(null, 200, $statut === 'suspendu' ? 'Médecin suspendu' : 'Médecin réactivé');
    }

    // ── DELETE /api/admin/medecins/{id} ──────────────────────────────────────
    public function supprimerMedecin(array $params = []): void
    {
        $this->exigerAdmin();
        $userId = (int) ($params['id'] ?? 0);

        $m = $this->adminModel->findMedecinParId($userId);
        if (!$m) $this->erreur('Médecin introuvable', 404);

        $this->adminModel->supprimerMedecin($userId);

        $payload = $this->verifierJWT();
        $this->mongoLog->log('admin_suppression_medecin', (int) $payload->sub, $payload->email, 'admin', 'succes',
            ['medecin_id' => $userId, 'email_supprime' => $m['email']]);

        $this->ok(null, 200, 'Médecin supprimé');
    }

    // ── GET /api/admin/stats ────────────────────────────────────────────────
    public function stats(array $params = []): void
    {
        $payload = $this->exigerAdmin();

        $q = fn(string $sql, array $b = []) => (int) $this->pdo->prepare($sql)->execute($b) ? 0 : 0;
        $count = function (string $sql, array $b = []): int {
            $s = $this->pdo->prepare($sql);
            $s->execute($b);
            return (int) $s->fetchColumn();
        };

        $moisDebut = date('Y-m-01 00:00:00');

        $alertes = $this->pdo->query(
            "SELECT ph.nom AS pharmacieNom, m.nom AS medicamentNom,
                    i.quantite, i.prix_unitaire AS prixUnitaire
             FROM inventaire i
             JOIN pharmacies  ph ON ph.id_pharmacie  = i.id_pharmacie
             JOIN medicaments m  ON m.id_medicament  = i.id_medicament
             WHERE i.quantite < 5
             ORDER BY i.quantite ASC
             LIMIT 20"
        )->fetchAll();

        $roles = $this->pdo->query(
            "SELECT role, COUNT(*) AS nb FROM utilisateurs GROUP BY role"
        )->fetchAll();

        $this->ok([
            'patients'         => $count("SELECT COUNT(*) FROM utilisateurs WHERE role='patient'"),
            'medecins'         => $count("SELECT COUNT(*) FROM utilisateurs WHERE role='medecin'"),
            'pharmacies'       => $count("SELECT COUNT(*) FROM pharmacies  WHERE actif=1"),
            'centresAnalyse'   => $count("SELECT COUNT(*) FROM centres_analyse WHERE actif=1"),
            'rdvCeMois'        => $count("SELECT COUNT(*) FROM rendez_vous WHERE created_at >= :d", [':d' => $moisDebut]),
            'rdvConfirmes'     => $count("SELECT COUNT(*) FROM rendez_vous WHERE statut='confirme'"),
            'rdvAnnules'       => $count("SELECT COUNT(*) FROM rendez_vous WHERE statut='annule'"),
            'commandesTotales' => $count("SELECT COUNT(*) FROM commandes"),
            'commandesAttente' => $count("SELECT COUNT(*) FROM commandes WHERE statut='en_attente'"),
            'demandesPro'      => $count("SELECT COUNT(*) FROM demandes_professionnels WHERE statut='en_attente'"),
            'alertesStock'     => array_map(fn($r) => [
                'pharmacieNom'  => $r['pharmacieNom'],
                'medicamentNom' => $r['medicamentNom'],
                'quantite'      => (int)   $r['quantite'],
                'prixUnitaire'  => (float) $r['prixUnitaire'],
            ], $alertes),
            'repartitionRoles' => array_map(fn($r) => [
                'role' => $r['role'],
                'nb'   => (int) $r['nb'],
            ], $roles),
        ]);
    }

    // ── GET /api/admin/demandes ──────────────────────────────────────────────
    public function listeDemandes(array $params = []): void
    {
        $this->exigerAdmin();

        $page    = max(1, (int) ($_GET['page']    ?? 1));
        $parPage = min(50, max(5, (int) ($_GET['perPage'] ?? 10)));
        $statut  = $_GET['statut'] ?? '';
        $offset  = ($page - 1) * $parPage;

        $where = [];
        $binds = [];
        if (in_array($statut, ['en_attente', 'approuve', 'rejete'], true)) {
            $where[]          = 'statut = :statut';
            $binds[':statut'] = $statut;
        }
        $wc = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmtN = $this->pdo->prepare("SELECT COUNT(*) FROM demandes_professionnels $wc");
        $stmtN->execute($binds);
        $total = (int) $stmtN->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT * FROM demandes_professionnels $wc
             ORDER BY created_at DESC
             LIMIT :limite OFFSET :offset"
        );
        foreach ($binds as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limite', $parPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $this->ok([
            'demandes'   => array_map(fn($r) => $this->formatDemande($r), $rows),
            'total'      => $total,
            'page'       => $page,
            'parPage'    => $parPage,
            'totalPages' => (int) ceil($total / $parPage),
        ]);
    }

    // ── GET /api/admin/demandes/{id} ─────────────────────────────────────────
    public function demande(array $params = []): void
    {
        $this->exigerAdmin();
        $id = (int) ($params['id'] ?? 0);

        $stmt = $this->pdo->prepare('SELECT * FROM demandes_professionnels WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row  = $stmt->fetch();
        if (!$row) $this->erreur('Demande introuvable', 404);

        $this->ok($this->formatDemande($row));
    }

    // ── PUT /api/admin/demandes/{id} ─────────────────────────────────────────
    public function traiteDemande(array $params = []): void
    {
        $payload = $this->exigerAdmin();
        $id      = (int) ($params['id'] ?? 0);
        $body    = $this->lireCorps();
        $action  = $body['action'] ?? '';

        if (!in_array($action, ['valider', 'rejeter'], true)) {
            $this->erreur("action invalide — valeurs acceptées : 'valider', 'rejeter'", 422);
        }

        $this->_traiterDemandeAction($id, $action, $body['commentaire'] ?? '', (int) $payload->sub);
    }

    // ── POST /api/admin/demandes/{id}/valider ────────────────────────────────
    public function validerDemande(array $params = []): void
    {
        $payload = $this->exigerAdmin();
        $id      = (int) ($params['id'] ?? 0);
        $body    = $this->lireCorps();
        $this->_traiterDemandeAction($id, 'valider', $body['commentaire'] ?? '', (int) $payload->sub);
    }

    // ── POST /api/admin/demandes/{id}/rejeter ────────────────────────────────
    public function rejeterDemande(array $params = []): void
    {
        $payload = $this->exigerAdmin();
        $id      = (int) ($params['id'] ?? 0);
        $body    = $this->lireCorps();
        $this->_traiterDemandeAction($id, 'rejeter', $body['commentaire'] ?? '', (int) $payload->sub);
    }

    // ── GET /api/admin/utilisateurs ──────────────────────────────────────────
    public function listeUtilisateurs(array $params = []): void
    {
        $this->exigerAdmin();

        $page    = max(1, (int) ($_GET['page']    ?? 1));
        $parPage = min(100, max(5, (int) ($_GET['perPage'] ?? 15)));
        $role    = $_GET['role']    ?? '';
        $statut  = $_GET['statut']  ?? '';
        $search  = trim($_GET['search'] ?? '');
        $offset  = ($page - 1) * $parPage;

        $rolesAutorisees  = ['patient', 'medecin', 'admin', 'pharmacie', 'centre_sante', 'centre_analyse'];
        $statutsAutorises = ['actif', 'inactif', 'suspendu'];

        $where = [];
        $binds = [];
        if (in_array($role, $rolesAutorisees, true)) {
            $where[]       = 'role = :role';
            $binds[':role'] = $role;
        }
        if (in_array($statut, $statutsAutorises, true)) {
            $where[]         = 'statut = :statut';
            $binds[':statut'] = $statut;
        }
        if ($search !== '') {
            $where[]         = '(nom LIKE :s OR prenom LIKE :s OR email LIKE :s)';
            $binds[':s']     = '%' . $search . '%';
        }
        $wc = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $stmtN = $this->pdo->prepare("SELECT COUNT(*) FROM utilisateurs $wc");
        $stmtN->execute($binds);
        $total = (int) $stmtN->fetchColumn();

        $stmt = $this->pdo->prepare(
            "SELECT id, nom, prenom, email, telephone, adresse, ville, role, statut, created_at
             FROM utilisateurs $wc
             ORDER BY created_at DESC
             LIMIT :limite OFFSET :offset"
        );
        foreach ($binds as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limite', $parPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $this->ok([
            'utilisateurs' => array_map(fn($u) => [
                'id'        => (int) $u['id'],
                'nom'       => $u['nom'],
                'prenom'    => $u['prenom'],
                'email'     => $u['email'],
                'telephone' => $u['telephone'],
                'adresse'   => $u['adresse'],
                'ville'     => $u['ville'],
                'role'      => $u['role'],
                'statut'    => $u['statut'],
                'createdAt' => $u['created_at'],
            ], $rows),
            'total'      => $total,
            'page'       => $page,
            'parPage'    => $parPage,
            'totalPages' => (int) ceil($total / $parPage),
        ]);
    }

    // ── PUT|PATCH /api/admin/utilisateurs/{id} ───────────────────────────────
    public function modifierUtilisateur(array $params = []): void
    {
        $payload = $this->exigerAdmin();
        $id      = (int) ($params['id'] ?? 0);
        $body    = $this->lireCorps();

        $stmt = $this->pdo->prepare('SELECT id, role FROM utilisateurs WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        if (!$stmt->fetch()) $this->erreur('Utilisateur introuvable', 404);

        $champs = [];
        $binds  = [':id' => $id];

        $textes = ['nom', 'prenom', 'telephone', 'adresse', 'ville'];
        foreach ($textes as $c) {
            if (array_key_exists($c, $body)) {
                $champs[]      = "$c = :$c";
                $binds[":$c"]  = trim((string) $body[$c]);
            }
        }
        if (isset($body['statut']) && in_array($body['statut'], ['actif', 'inactif', 'suspendu'], true)) {
            $champs[]          = 'statut = :statut';
            $binds[':statut']  = $body['statut'];
        }
        if (empty($champs)) $this->erreur('Aucun champ à mettre à jour', 422);

        $this->pdo->prepare(
            'UPDATE utilisateurs SET ' . implode(', ', $champs) . ' WHERE id = :id'
        )->execute($binds);

        $this->mongoLog->log('admin_modification_utilisateur', (int) $payload->sub,
            $payload->email, 'admin', 'succes', ['utilisateur_id' => $id]);

        $this->ok(null, 200, 'Utilisateur mis à jour');
    }

    // ── GET /api/admin/logs ──────────────────────────────────────────────────
    public function logs(array $params = []): void
    {
        $this->exigerAdmin();

        $limite = min(200, max(10, (int) ($_GET['limit'] ?? 50)));
        $action = $_GET['action'] ?? '';

        $entries = $this->mongoLog->lireLogs($limite, $action ?: null);

        $this->ok(['logs' => $entries, 'total' => count($entries)]);
    }

    // ── GET /api/admin/auth-stats ────────────────────────────────────────────
    public function authStats(array $params = []): void
    {
        $this->exigerAdmin();
        $stats = $this->mongoLog->statistiques();
        $this->ok($stats);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function _traiterDemandeAction(int $id, string $action, string $commentaire, int $adminId): void
    {
        $stmt = $this->pdo->prepare('SELECT * FROM demandes_professionnels WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $demande = $stmt->fetch();
        if (!$demande) $this->erreur('Demande introuvable', 404);

        $statut = $action === 'valider' ? 'approuve' : 'rejete';

        $sets  = ['statut = :statut', 'traite_par = :traite_par', 'traite_le = NOW()'];
        $binds = [':statut' => $statut, ':traite_par' => $adminId ?: null, ':id' => $id];
        if ($commentaire !== '') {
            $sets[]              = 'commentaire = :commentaire';
            $binds[':commentaire'] = $commentaire;
        }
        $this->pdo->prepare(
            'UPDATE demandes_professionnels SET ' . implode(', ', $sets) . ' WHERE id = :id'
        )->execute($binds);

        $this->ok(null, 200, $action === 'valider' ? 'Demande approuvée' : 'Demande rejetée');
    }

    private function formatDemande(array $r): array
    {
        return [
            'id'                => (int) $r['id'],
            'typeProfessionnel' => $r['type_professionnel'] ?? null,
            'nom'               => $r['nom'],
            'prenom'            => $r['prenom'],
            'email'             => $r['email'],
            'telephone'         => $r['telephone']         ?? null,
            'numeroPro'         => $r['numero_pro']        ?? ($r['numero_rpps'] ?? null),
            'specialisation'    => $r['specialisation']    ?? null,
            'entiteId'          => isset($r['entite_id'])  ? (int) $r['entite_id'] : null,
            'entiteNom'         => $r['entite_nom']        ?? null,
            'statut'            => $r['statut'],
            'commentaire'       => $r['commentaire']       ?? null,
            'traitePar'         => isset($r['traite_par']) ? (int) $r['traite_par'] : null,
            'traiteLe'          => $r['traite_le']         ?? null,
            'adresseCabinet'    => $r['adresse_cabinet']   ?? null,
            'createdAt'         => $r['created_at'],
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Vérifie JWT + rôle admin, sinon 401/403. */
    private function exigerAdmin(): object
    {
        $payload = $this->verifierJWT();
        if ($payload->role !== 'admin') {
            $this->erreur('Accès réservé aux administrateurs', 403);
        }
        return $payload;
    }

    private function verifierJWT(): object
    {
        $token = JwtService::extraireToken();
        if ($token === null) $this->erreur('Token manquant', 401);
        try {
            return $this->jwtService->verifier($token);
        } catch (\RuntimeException $e) {
            $this->erreur($e->getMessage(), 401);
        }
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
        if ($message) $body['message'] = $message;
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function erreur(string $msg, int $code = 400, ?string $details = null): never
    {
        http_response_code($code);
        $body = ['error' => $msg, 'code' => $code];
        if ($details) $body['details'] = $details;
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
