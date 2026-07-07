<?php
namespace controllers\api;

use services\AuthService;
use services\JwtService;
use services\MongoLogService;

/**
 * AuthApiController — Endpoints REST authentification
 *
 * POST  /api/auth/login    → token JWT + user
 * POST  /api/auth/register → création patient + token JWT
 * GET   /api/auth/me       → user frais depuis BDD (JWT requis)
 * POST  /api/auth/logout   → log MongoDB déconnexion + 200
 * PATCH /api/auth/profil   → mise à jour profil (JWT requis)
 */
class AuthApiController
{
    private AuthService     $authService;
    private JwtService      $jwtService;
    private MongoLogService $mongoLog;

    public function __construct(private \PDO $pdo)
    {
        $this->authService = new AuthService($pdo);
        $this->jwtService  = new JwtService();
        $this->mongoLog    = new MongoLogService();
    }

    // ── POST /api/auth/login ─────────────────────────────────────────────────
    public function login(array $params = []): void
    {
        $body     = $this->lireCorps();
        $email    = trim($body['email']    ?? '');
        $password = $body['password'] ?? '';

        if ($email === '' || $password === '') {
            $this->erreur('email et password sont requis', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->erreur('Adresse email invalide', 400);
        }

        $user = $this->authService->login($email, $password);

        if ($user === null) {
            $this->mongoLog->log('connexion_echouee', null, $email, null, 'echec');
            $this->erreur('Identifiants incorrects', 401);
        }

        $token = $this->jwtService->generer($user);
        $this->mongoLog->log('connexion_reussie', $user['id'], $user['email'], $user['role'], 'succes');

        $this->ok([
            'token'     => $token,
            'expiresIn' => $this->jwtService->getExpiresIn(),
            'user'      => [
                'id'        => $user['id'],
                'nom'       => $user['nom'],
                'prenom'    => $user['prenom'] ?? '',
                'email'     => $user['email'],
                'role'      => $user['role'],
                'photoPath' => $user['photo_path'] ?? null,
            ],
        ], 200, 'Connexion réussie');
    }

    // ── POST /api/auth/register ──────────────────────────────────────────────
    public function register(array $params = []): void
    {
        $body      = $this->lireCorps();
        $nom       = trim($body['nom']       ?? '');
        $prenom    = trim($body['prenom']    ?? '');
        $email     = trim($body['email']     ?? '');
        $password  = $body['password']  ?? '';
        $telephone = trim($body['telephone'] ?? '');
        $adresse   = trim($body['adresse']   ?? '');
        $ville     = trim($body['ville']     ?? '');

        $erreurs = [];
        if (strlen($nom)      < 2) $erreurs['nom']      = 'Le nom doit contenir au moins 2 caractères';
        if (strlen($prenom)   < 2) $erreurs['prenom']   = 'Le prénom doit contenir au moins 2 caractères';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erreurs['email'] = 'Adresse email invalide';
        if (strlen($password) < 8) $erreurs['password'] = 'Le mot de passe doit contenir au moins 8 caractères';

        if (!empty($erreurs)) {
            $this->erreur('Données invalides', 422, json_encode($erreurs, JSON_UNESCAPED_UNICODE));
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM utilisateurs WHERE email = :email');
        $stmt->execute([':email' => $email]);
        if ((int) $stmt->fetchColumn() > 0) {
            $this->erreur('Cette adresse email est déjà utilisée', 409);
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO utilisateurs (nom, prenom, email, mot_de_passe_hash, telephone, adresse, ville, role, statut)
             VALUES (:nom, :prenom, :email, :hash, :telephone, :adresse, :ville, 'patient', 'actif')"
        );
        $stmt->execute([
            ':nom'       => $nom,
            ':prenom'    => $prenom,
            ':email'     => $email,
            ':hash'      => password_hash($password, PASSWORD_BCRYPT),
            ':telephone' => $telephone ?: null,
            ':adresse'   => $adresse   ?: null,
            ':ville'     => $ville     ?: null,
        ]);

        $userId = (int) $this->pdo->lastInsertId();
        $user   = ['id' => $userId, 'nom' => $nom, 'prenom' => $prenom, 'email' => $email, 'role' => 'patient', 'photo_path' => null];
        $token  = $this->jwtService->generer($user);

        $this->mongoLog->log('inscription', $userId, $email, 'patient', 'succes');

        $this->ok([
            'token'     => $token,
            'expiresIn' => $this->jwtService->getExpiresIn(),
            'user'      => ['id' => $userId, 'nom' => $nom, 'prenom' => $prenom, 'email' => $email, 'role' => 'patient', 'photoPath' => null],
        ], 201, 'Compte créé avec succès');
    }

    // ── GET /api/auth/me ─────────────────────────────────────────────────────
    public function me(array $params = []): void
    {
        $payload = $this->verifierJWT();
        $userId  = (int) $payload->sub;

        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.nom, u.prenom, u.email, u.telephone,
                    u.adresse, u.ville, u.role, u.statut,
                    u.created_at, COALESCE(m.photo_path, u.photo_path) AS photo_path
             FROM utilisateurs u
             LEFT JOIN medecins m ON m.utilisateur_id = u.id
             WHERE u.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if (!$user)                      $this->erreur('Utilisateur introuvable', 404);
        if ($user['statut'] !== 'actif') $this->erreur('Compte suspendu ou inactif', 403);

        $this->ok([
            'id'        => (int) $user['id'],
            'nom'       => $user['nom'],
            'prenom'    => $user['prenom'],
            'email'     => $user['email'],
            'telephone' => $user['telephone'],
            'adresse'   => $user['adresse'],
            'ville'     => $user['ville'],
            'role'      => $user['role'],
            'statut'    => $user['statut'],
            'createdAt' => $user['created_at'],
            'photoPath' => $user['photo_path'],
        ]);
    }

    // ── POST /api/auth/logout ────────────────────────────────────────────────
    public function logout(array $params = []): void
    {
        $payload = $this->verifierJWT();

        $this->mongoLog->log(
            'deconnexion',
            (int) $payload->sub,
            $payload->email,
            $payload->role,
            'succes'
        );

        $this->ok(null, 200, 'Déconnexion enregistrée');
    }

    // ── PATCH /api/auth/profil ───────────────────────────────────────────────
    public function mettreAJourProfil(array $params = []): void
    {
        $payload = $this->verifierJWT();
        $userId  = (int) $payload->sub;

        $body      = $this->lireCorps();
        $nom       = trim($body['nom']       ?? '');
        $prenom    = trim($body['prenom']    ?? '');
        $telephone = trim($body['telephone'] ?? '');
        $adresse   = trim($body['adresse']   ?? '');
        $ville     = trim($body['ville']     ?? '');

        $erreurs = [];
        if (strlen($nom)    < 2) $erreurs['nom']    = 'Le nom doit contenir au moins 2 caractères';
        if (strlen($prenom) < 2) $erreurs['prenom'] = 'Le prénom doit contenir au moins 2 caractères';

        if (!empty($erreurs)) {
            $this->erreur('Données invalides', 422, json_encode($erreurs, JSON_UNESCAPED_UNICODE));
        }

        $stmt = $this->pdo->prepare(
            "UPDATE utilisateurs
             SET nom = :nom, prenom = :prenom, telephone = :telephone,
                 adresse = :adresse, ville = :ville
             WHERE id = :id"
        );
        $stmt->execute([
            ':nom'       => $nom,
            ':prenom'    => $prenom,
            ':telephone' => $telephone ?: null,
            ':adresse'   => $adresse   ?: null,
            ':ville'     => $ville     ?: null,
            ':id'        => $userId,
        ]);

        // Recharger les données mises à jour
        $stmt = $this->pdo->prepare(
            "SELECT id, nom, prenom, email, telephone, adresse, ville, role, statut, created_at, photo_path
             FROM utilisateurs WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        $this->mongoLog->log('profil_mis_a_jour', $userId, $user['email'], $user['role'], 'succes');

        $this->ok([
            'id'        => (int) $user['id'],
            'nom'       => $user['nom'],
            'prenom'    => $user['prenom'],
            'email'     => $user['email'],
            'telephone' => $user['telephone'],
            'adresse'   => $user['adresse'],
            'ville'     => $user['ville'],
            'role'      => $user['role'],
            'statut'    => $user['statut'],
            'createdAt' => $user['created_at'],
            'photoPath' => $user['photo_path'],
        ], 200, 'Profil mis à jour');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Vérifie le JWT dans Authorization: Bearer <token>.
     * Retourne le payload décodé, ou termine avec 401.
     */
    private function verifierJWT(): object
    {
        $token = JwtService::extraireToken();
        if ($token === null) {
            $this->erreur('Token manquant', 401);
        }

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
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->erreur('Corps JSON invalide', 400, json_last_error_msg());
        }
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
