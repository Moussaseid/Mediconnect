<?php
namespace controllers\api;

use services\JwtService;

/**
 * UserApiController
 *
 * PUT /api/utilisateurs/me → mise à jour du profil de l'utilisateur connecté
 */
class UserApiController
{
    public function __construct(private \PDO $pdo) {}

    // ── PUT /api/utilisateurs/me ──────────────────────────────────────────────
    public function mettreAJour(array $params = []): void
    {
        $token = JwtService::extraireToken();
        if ($token === null) { $this->erreur('Authentification requise', 401); }
        try { $payload = (new JwtService())->verifier($token); }
        catch (\RuntimeException) { $this->erreur('Token invalide', 401); }

        $userId = (int) $payload->sub;
        $body   = json_decode(file_get_contents('php://input'), true) ?? [];

        $nom    = trim((string) ($body['nom']    ?? ''));
        $prenom = trim((string) ($body['prenom'] ?? ''));

        if ($nom === '' || $prenom === '') {
            $this->erreur('Le nom et le prénom sont requis', 400);
        }
        if (mb_strlen($nom) > 100 || mb_strlen($prenom) > 100) {
            $this->erreur('Nom ou prénom trop long (max 100 caractères)', 400);
        }

        $telephone = isset($body['telephone']) ? trim((string) $body['telephone']) : null;
        $adresse   = isset($body['adresse'])   ? trim((string) $body['adresse'])   : null;
        $ville     = isset($body['ville'])      ? trim((string) $body['ville'])     : null;
        $motDePasse = isset($body['motDePasse']) ? trim((string) $body['motDePasse']) : null;

        // Mise à jour des champs de base
        $sets    = 'nom = :nom, prenom = :prenom, telephone = :telephone, adresse = :adresse, ville = :ville';
        $binds   = [
            ':nom'       => $nom,
            ':prenom'    => $prenom,
            ':telephone' => $telephone !== '' ? $telephone : null,
            ':adresse'   => $adresse   !== '' ? $adresse   : null,
            ':ville'     => $ville     !== '' ? $ville     : null,
            ':id'        => $userId,
        ];

        // Changement de mot de passe optionnel
        if ($motDePasse !== null && $motDePasse !== '') {
            if (mb_strlen($motDePasse) < 8) {
                $this->erreur('Le mot de passe doit contenir au moins 8 caractères', 400);
            }
            $sets .= ', mot_de_passe_hash = :hash';
            $binds[':hash'] = password_hash($motDePasse, PASSWORD_BCRYPT);
        }

        $this->pdo->prepare("UPDATE utilisateurs SET {$sets} WHERE id = :id")
                  ->execute($binds);

        // Recharger et retourner l'utilisateur mis à jour
        $stmt = $this->pdo->prepare(
            'SELECT id, nom, prenom, email, telephone, adresse, ville, role, statut,
                    created_at AS createdAt, photo_path AS photoPath
             FROM utilisateurs WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();

        $this->ok([
            'id'        => (int) $row['id'],
            'nom'       => $row['nom'],
            'prenom'    => $row['prenom'],
            'email'     => $row['email'],
            'telephone' => $row['telephone'],
            'adresse'   => $row['adresse'],
            'ville'     => $row['ville'],
            'role'      => $row['role'],
            'statut'    => $row['statut'],
            'createdAt' => $row['createdAt'],
            'photoPath' => $row['photoPath'],
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private function ok(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function erreur(string $msg, int $code = 400): never
    {
        http_response_code($code);
        echo json_encode(['error' => $msg, 'code' => $code], JSON_UNESCAPED_UNICODE);
        exit;
    }
}